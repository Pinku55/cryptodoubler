<?php
/**
 * API: profile
 * --------------------------------------------------------------------------
 * Returns the user's profile details and support links.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$user = Auth::requireUser();

Response::success([
    'id'            => (int) $user['id'],
    'telegram_id'   => (int) $user['telegram_id'],
    'username'      => $user['username'],
    'first_name'    => $user['first_name'],
    'last_name'     => $user['last_name'],
    'photo_url'     => $user['photo_url'],
    'referral_code' => $user['referral_code'],
    'language'      => $user['language'],
    'created_at'    => $user['created_at'],
    'support'       => [
        'support_username' => Settings::get('support_username', ''),
        'privacy_url'      => Settings::get('privacy_url', ''),
        'terms_url'        => Settings::get('terms_url', ''),
    ],
]);
