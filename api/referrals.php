<?php
/**
 * API: referrals
 * --------------------------------------------------------------------------
 * Referral code/link, statistics and the list of invited users.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$user   = Auth::requireUser();
$userId = (int) $user['id'];

$botUsername = Settings::get('telegram_bot_username', '');
$code = $user['referral_code'];
$link = $botUsername ? "https://t.me/{$botUsername}?start={$code}" : (config('app.base_url', '') . "/app.php?ref={$code}");

// Earnings from referral transactions.
$earned = (int) Database::scalar(
    'SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = ?',
    [$userId, User::TX_REFERRAL]
);

// List of invited users.
$referred = Database::fetchAll(
    'SELECT first_name, username, created_at FROM users WHERE referred_by = ? ORDER BY id DESC LIMIT 50',
    [$userId]
);

Response::success([
    'code'            => $code,
    'link'            => $link,
    'total_referrals' => (int) $user['total_referrals'],
    'total_earned'    => $earned,
    'reward_per_ref'  => Settings::getInt('referral_reward', 1000),
    'referred'        => $referred,
]);
