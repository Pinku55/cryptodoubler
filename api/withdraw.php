<?php
/**
 * API: withdraw
 * --------------------------------------------------------------------------
 * Withdrawal methods, request submission and per-user history.
 *
 * POST action=methods : active payment methods + dynamic fields + minimums.
 * POST action=request : validate + deduct balance + create a pending request.
 * POST action=history : the user's withdrawal history.
 *
 * On request the MT amount is debited immediately and held; if an admin
 * later rejects the request the amount is refunded.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$user   = Auth::requireUser();
$userId = (int) $user['id'];
$action = input('action', 'methods');

$mtPerUsd     = max(1, Settings::getInt('mt_per_usd', 10000));
$globalMinimum = Settings::getInt('min_withdraw', 20000);

if ($action === 'methods') {
    $methods = Database::fetchAll(
        'SELECT id, name, code, icon, min_amount, fields FROM payment_methods WHERE status = "active" ORDER BY sort_order ASC, id ASC'
    );
    foreach ($methods as &$m) {
        $m['min_amount'] = max((int) $m['min_amount'], $globalMinimum);
        $m['fields'] = $m['fields'] ? json_decode($m['fields'], true) : [];
    }
    unset($m);

    Response::success([
        'methods'      => $methods,
        'min_withdraw' => $globalMinimum,
        'mt_per_usd'   => $mtPerUsd,
        'balance'      => (int) $user['balance'],
    ]);
}

if ($action === 'history') {
    $rows = Database::fetchAll(
        'SELECT id, method_name, amount_mt, amount_usd, status, admin_note, created_at, processed_at
         FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 50',
        [$userId]
    );
    Response::success(['withdrawals' => $rows]);
}

if ($action === 'request') {
    if (!Security::rateLimit('withdraw:' . $userId, 5, 600)) {
        Response::error('Too many withdrawal attempts. Please wait a few minutes.', 429);
    }

    $methodId = inputInt('method_id');
    $amount   = inputInt('amount');

    $method = Database::fetch('SELECT * FROM payment_methods WHERE id = ? AND status = "active"', [$methodId]);
    if ($method === null) {
        Response::error('Invalid payment method.', 400);
    }

    $methodMin = max((int) $method['min_amount'], $globalMinimum);
    if ($amount < $methodMin) {
        Response::error('Minimum withdrawal for this method is ' . number_format($methodMin) . ' MT.', 400);
    }
    if ($amount > (int) $user['balance']) {
        Response::error('Insufficient balance.', 400);
    }

    // Validate dynamic account fields.
    $fields = $method['fields'] ? json_decode($method['fields'], true) : [];
    $accountDetails = [];
    foreach ($fields as $field) {
        $val = trim((string) ($_POST['field_' . $field['name']] ?? ''));
        if (!empty($field['required']) && $val === '') {
            Response::error('Please fill in: ' . ($field['label'] ?? $field['name']), 400);
        }
        $accountDetails[$field['name']] = $val;
    }

    $amountUsd = round($amount / $mtPerUsd, 4);

    Database::begin();
    try {
        // Debit balance immediately (held until processed).
        User::adjustBalance($userId, -$amount, User::TX_WITHDRAW, 'Withdrawal request via ' . $method['name'], ['method' => $method['code']]);

        $wid = Database::insert('withdrawals', [
            'user_id'         => $userId,
            'method_id'       => $methodId,
            'method_name'     => $method['name'],
            'amount_mt'       => $amount,
            'amount_usd'      => $amountUsd,
            'account_details' => json_encode($accountDetails),
            'status'          => 'pending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        Database::commit();
    } catch (Throwable $e) {
        Database::rollback();
        Response::error('Could not submit withdrawal. ' . ($e->getMessage() === 'Insufficient balance' ? 'Insufficient balance.' : 'Please try again.'), 400);
    }

    // Notify admin chat (best effort) if configured.
    $adminChat = Settings::get('admin_chat_id', '');
    if ($adminChat) {
        Telegram::sendMessage((int) $adminChat, "💸 New withdrawal #{$wid}\nUser: {$user['first_name']} (#{$userId})\nAmount: " . number_format($amount) . " MT (\${$amountUsd})\nMethod: {$method['name']}");
    }

    Response::success(['withdrawal_id' => $wid], 'Withdrawal request submitted! It is now pending review.');
}

Response::error('Unknown action.', 400);
