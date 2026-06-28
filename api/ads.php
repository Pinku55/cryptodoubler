<?php
/**
 * API: ads
 * --------------------------------------------------------------------------
 * Rewarded video (Monetag) status and reward claiming.
 *
 * POST action=status : returns reward config + today's progress + cooldown.
 * POST action=claim  : credits the ad reward after the front-end confirms
 *                      the Monetag popup completed (show_<zone>('pop').then).
 *
 * Anti-abuse: per-user daily limit, cooldown between ads, rate limiter and
 * an IP recorded on every view.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$user   = Auth::requireUser();
$userId = (int) $user['id'];
$action = input('action', 'status');

if (!Settings::getBool('ads_enabled', true)) {
    Response::error('Rewarded ads are currently disabled.', 403);
}

$reward     = Settings::getInt('ad_reward', 50);
$cooldown   = Settings::getInt('ad_cooldown', 30);
$dailyLimit = Settings::getInt('ad_daily_limit', 50);
$maxEarn    = Settings::getInt('ad_max_earnings', 0); // 0 = unlimited

/** Build the current ad status for the user. */
function adStatus(int $userId, int $reward, int $cooldown, int $dailyLimit): array
{
    $today = date('Y-m-d');
    $todayCount = (int) Database::scalar(
        'SELECT COUNT(*) FROM ad_views WHERE user_id = ? AND DATE(created_at) = ?',
        [$userId, $today]
    );
    $lastView = Database::scalar(
        'SELECT UNIX_TIMESTAMP(created_at) FROM ad_views WHERE user_id = ? ORDER BY id DESC LIMIT 1',
        [$userId]
    );
    $secondsSince = $lastView ? (time() - (int) $lastView) : PHP_INT_MAX;
    $cooldownLeft = max(0, $cooldown - $secondsSince);

    return [
        'reward'        => $reward,
        'daily_limit'   => $dailyLimit,
        'today_count'   => $todayCount,
        'remaining'     => max(0, $dailyLimit - $todayCount),
        'cooldown'      => $cooldown,
        'cooldown_left' => $cooldownLeft,
        'can_watch'     => ($dailyLimit === 0 || $todayCount < $dailyLimit) && $cooldownLeft === 0,
        'monetag_zone'  => Settings::get('monetag_zone_id', '9660124'),
    ];
}

if ($action === 'status') {
    Response::success(adStatus($userId, $reward, $cooldown, $dailyLimit));
}

if ($action === 'claim') {
    // Hard rate limit as a backstop against tampering (max 1 per cooldown).
    if (!Security::rateLimit('ad:' . $userId, 1, max(5, $cooldown))) {
        Response::error('Please wait for the cooldown before watching another ad.', 429);
    }

    $today = date('Y-m-d');
    $todayCount = (int) Database::scalar(
        'SELECT COUNT(*) FROM ad_views WHERE user_id = ? AND DATE(created_at) = ?',
        [$userId, $today]
    );
    if ($dailyLimit > 0 && $todayCount >= $dailyLimit) {
        Response::error('Daily ad limit reached. Come back tomorrow!', 429);
    }

    // Optional daily earnings cap from ads.
    if ($maxEarn > 0) {
        $earnedToday = (int) Database::scalar(
            'SELECT COALESCE(SUM(reward),0) FROM ad_views WHERE user_id = ? AND DATE(created_at) = ?',
            [$userId, $today]
        );
        if ($earnedToday + $reward > $maxEarn) {
            Response::error('You reached the maximum ad earnings for today.', 429);
        }
    }

    // Record the view and credit the reward.
    Database::insert('ad_views', [
        'user_id'    => $userId,
        'reward'     => $reward,
        'ip_address' => Security::clientIp(),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    $newBalance = User::adjustBalance($userId, $reward, User::TX_AD, 'Rewarded ad', ['ip' => Security::clientIp()]);

    Response::success([
        'reward'      => $reward,
        'balance'     => $newBalance,
        'status'      => adStatus($userId, $reward, $cooldown, $dailyLimit),
    ], 'You earned ' . number_format($reward) . ' MT!');
}

Response::error('Unknown action.', 400);
