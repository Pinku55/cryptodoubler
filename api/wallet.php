<?php
/**
 * API: wallet
 * --------------------------------------------------------------------------
 * Wallet balances and paginated, filterable transaction history.
 *
 * POST page, limit, type (optional filter)
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$user   = Auth::requireUser();
$userId = (int) $user['id'];

$page  = max(1, inputInt('page', 1));
$limit = min(50, max(5, inputInt('limit', 20)));
$type  = input('type');

$history = User::transactions($userId, $page, $limit, $type ?: null);

$mtPerUsd = max(1, Settings::getInt('mt_per_usd', 10000));

// Pending balance = sum of pending/approved withdrawals still in flight.
$pending = (int) Database::scalar(
    'SELECT COALESCE(SUM(amount_mt),0) FROM withdrawals WHERE user_id = ? AND status IN ("pending","approved")',
    [$userId]
);

Response::success([
    'balance'           => (int) $user['balance'],
    'balance_usd'       => round((int) $user['balance'] / $mtPerUsd, 4),
    'pending'           => $pending,
    'lifetime_earnings' => (int) $user['total_earned'],
    'lifetime_withdraw' => (int) $user['total_withdrawn'],
    'transactions'      => $history,
]);
