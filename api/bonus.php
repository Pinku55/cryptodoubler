<?php
/**
 * API: bonus (daily bonus)
 * --------------------------------------------------------------------------
 * 7-day streak daily bonus. A missed day resets the streak to day 1.
 *
 * POST action=status : ladder + current streak + claim availability.
 * POST action=claim  : claim today's bonus and advance/refresh the streak.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$user   = Auth::requireUser();
$userId = (int) $user['id'];
$action = input('action', 'status');

if (!Settings::getBool('daily_bonus_enabled', true)) {
    Response::error('Daily bonus is currently disabled.', 403);
}

// Load the configurable 7-day ladder.
$ladderRows = Database::fetchAll('SELECT day, reward FROM daily_bonus_rewards ORDER BY day ASC');
$ladder = [];
foreach ($ladderRows as $r) {
    $ladder[(int) $r['day']] = (int) $r['reward'];
}
$maxDay = $ladder ? max(array_keys($ladder)) : 7;

$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$lastDate  = $user['last_bonus_date'];
$streak    = (int) $user['daily_streak'];

// Determine which day the user would be claiming next.
$claimedToday = ($lastDate === $today);
if ($claimedToday) {
    $nextDay = $streak; // already claimed today
} elseif ($lastDate === $yesterday) {
    $nextDay = $streak + 1;          // continue streak
    if ($nextDay > $maxDay) {
        $nextDay = 1;                // ladder wraps after completing a cycle
    }
} else {
    $nextDay = 1;                    // missed a day (or first time) -> reset
}

if ($action === 'status') {
    Response::success([
        'ladder'        => $ladder,
        'current_day'   => $streak,
        'next_day'      => $nextDay,
        'claimed_today' => $claimedToday,
        'next_reward'   => $ladder[$nextDay] ?? 0,
    ]);
}

if ($action === 'claim') {
    if ($claimedToday) {
        Response::error('You already claimed today\'s bonus. Come back tomorrow!', 409);
    }
    if (!Security::rateLimit('bonus:' . $userId, 3, 60)) {
        Response::error('Too many attempts. Please wait a moment.', 429);
    }

    $reward = $ladder[$nextDay] ?? 0;
    if ($reward <= 0) {
        Response::error('No bonus configured for today.', 400);
    }

    Database::update('users', [
        'daily_streak'    => $nextDay,
        'last_bonus_date' => $today,
    ], 'id = :id', ['id' => $userId]);

    $newBalance = User::adjustBalance($userId, $reward, User::TX_DAILY_BONUS, "Daily bonus day {$nextDay}", ['day' => $nextDay]);

    Response::success([
        'reward'      => $reward,
        'day'         => $nextDay,
        'balance'     => $newBalance,
    ], 'Day ' . $nextDay . ' bonus claimed: +' . number_format($reward) . ' MT!');
}

Response::error('Unknown action.', 400);
