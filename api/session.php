<?php
/**
 * API: session
 * --------------------------------------------------------------------------
 * Authenticates the Mini App user from Telegram initData and returns the
 * combined bootstrap payload: the user, public settings and home stats.
 *
 * POST: initData (required), ref (optional referral code)
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$user = Auth::requireUser();

// Public (non-secret) settings the front-end needs.
$public = [
    'site_name'        => Settings::get('site_name', 'MTASK'),
    'currency_symbol'  => Settings::get('currency_symbol', 'MT'),
    'theme_color'      => Settings::get('theme_color', '#7c3aed'),
    'mt_per_usd'       => Settings::getInt('mt_per_usd', 10000),
    'min_withdraw'     => Settings::getInt('min_withdraw', 20000),
    'monetag_zone_id'  => Settings::get('monetag_zone_id', '9660124'),
    'ads_enabled'      => Settings::getBool('ads_enabled', true),
    'ad_reward'        => Settings::getInt('ad_reward', 50),
    'ad_cooldown'      => Settings::getInt('ad_cooldown', 30),
    'ad_daily_limit'   => Settings::getInt('ad_daily_limit', 50),
    'daily_bonus_enabled' => Settings::getBool('daily_bonus_enabled', true),
    'referral_reward'  => Settings::getInt('referral_reward', 1000),
    'bot_username'     => Settings::get('telegram_bot_username', ''),
    'support_username' => Settings::get('support_username', ''),
    'privacy_url'      => Settings::get('privacy_url', ''),
    'terms_url'        => Settings::get('terms_url', ''),
    'announcement'     => Settings::get('announcement', ''),
];

// Recent activity (latest 10 transactions).
$recent = Database::fetchAll(
    'SELECT type, amount, note, status, created_at FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10',
    [(int) $user['id']]
);

$mtPerUsd = max(1, $public['mt_per_usd']);

Response::success([
    'user' => [
        'id'              => (int) $user['id'],
        'telegram_id'     => (int) $user['telegram_id'],
        'username'        => $user['username'],
        'first_name'      => $user['first_name'],
        'last_name'       => $user['last_name'],
        'photo_url'       => $user['photo_url'],
        'referral_code'   => $user['referral_code'],
        'balance'         => (int) $user['balance'],
        'balance_usd'     => round((int) $user['balance'] / $mtPerUsd, 4),
        'total_earned'    => (int) $user['total_earned'],
        'total_withdrawn' => (int) $user['total_withdrawn'],
        'total_referrals' => (int) $user['total_referrals'],
        'daily_streak'    => (int) $user['daily_streak'],
        'language'        => $user['language'],
        'created_at'      => $user['created_at'],
    ],
    'settings' => $public,
    'recent'   => $recent,
], 'Authenticated');
