<?php
/**
 * API: tasks
 * --------------------------------------------------------------------------
 * Task wall listing and completion flow.
 *
 * POST action=list  : active tasks with the user's per-task status.
 * POST action=start : mark a task as started (records start time + IP).
 * POST action=claim : verify (timer / telegram membership) and reward.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$user   = Auth::requireUser();
$userId = (int) $user['id'];
$action = input('action', 'list');

if ($action === 'list') {
    $tasks = Database::fetchAll(
        'SELECT id, title, description, category, url, image, reward, wait_time, verify_type
         FROM tasks WHERE status = "active" ORDER BY sort_order ASC, id DESC'
    );

    // Attach completion status per task.
    $completed = Database::fetchAll(
        'SELECT task_id, status, started_at FROM task_completions WHERE user_id = ?',
        [$userId]
    );
    $map = [];
    foreach ($completed as $c) {
        $map[(int) $c['task_id']] = $c;
    }

    foreach ($tasks as &$t) {
        $tid = (int) $t['id'];
        $t['reward'] = (int) $t['reward'];
        $t['wait_time'] = (int) $t['wait_time'];
        $t['user_status'] = $map[$tid]['status'] ?? 'new';
        $t['started_at'] = $map[$tid]['started_at'] ?? null;
    }
    unset($t);

    Response::success(['tasks' => $tasks]);
}

$taskId = inputInt('task_id');
$task = Database::fetch('SELECT * FROM tasks WHERE id = ? AND status = "active"', [$taskId]);
if ($task === null) {
    Response::error('Task not found or inactive.', 404);
}

// Already completed?
$existing = Database::fetch(
    'SELECT * FROM task_completions WHERE user_id = ? AND task_id = ?',
    [$userId, $taskId]
);
if ($existing && $existing['status'] === 'completed') {
    Response::error('You have already completed this task.', 409);
}

if ($action === 'start') {
    if ($existing) {
        Database::query(
            'UPDATE task_completions SET started_at = NOW(), status = "started" WHERE id = ?',
            [$existing['id']]
        );
    } else {
        Database::query(
            'INSERT INTO task_completions (task_id, user_id, status, reward, started_at, ip_address)
             VALUES (?, ?, "started", ?, NOW(), ?)',
            [$taskId, $userId, (int) $task['reward'], Security::clientIp()]
        );
    }
    Response::success(['wait_time' => (int) $task['wait_time']], 'Task started.');
}

if ($action === 'claim') {
    if (!$existing || $existing['status'] !== 'started') {
        Response::error('Please start the task first.', 400);
    }

    // Timer verification: ensure the wait time elapsed since start. Computed in
    // SQL so started_at and NOW() share one clock (timezone-safe).
    $waited = (int) Database::scalar(
        'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW())) FROM task_completions WHERE id = ?',
        [$existing['id']]
    );
    if ($waited < (int) $task['wait_time']) {
        Response::error('Please wait ' . ((int) $task['wait_time'] - $waited) . 's more before claiming.', 425);
    }

    // Telegram membership verification (optional).
    if ($task['verify_type'] === 'telegram_member' && !empty($task['verify_target'])) {
        $res = Telegram::api('getChatMember', [
            'chat_id' => $task['verify_target'],
            'user_id' => (int) $user['telegram_id'],
        ]);
        $status = $res['result']['status'] ?? 'left';
        if (!in_array($status, ['member', 'administrator', 'creator'], true)) {
            Response::error('You must join to claim this reward.', 403);
        }
    }

    $reward = (int) $task['reward'];
    Database::update('task_completions', [
        'status'       => 'completed',
        'completed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $existing['id']]);
    Database::query('UPDATE tasks SET completed_count = completed_count + 1 WHERE id = ?', [$taskId]);

    $newBalance = User::adjustBalance($userId, $reward, User::TX_TASK, 'Task: ' . $task['title'], ['task_id' => $taskId]);

    Response::success(['reward' => $reward, 'balance' => $newBalance], 'You earned ' . number_format($reward) . ' MT!');
}

Response::error('Unknown action.', 400);
