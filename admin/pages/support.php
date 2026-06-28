<?php
/**
 * Admin support: message a single user or broadcast via the Telegram bot.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($message === '') {
        flash('error', 'Message cannot be empty.');
    } elseif ($action === 'direct') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $u = User::findById($uid);
        if ($u) {
            $res = Telegram::sendMessage((int) $u['telegram_id'], $message);
            Database::insert('notifications', ['user_id' => $uid, 'title' => 'Message from support', 'body' => $message, 'created_at' => date('Y-m-d H:i:s')]);
            Logger::audit((int) $admin['id'], 'support.direct', "DM to user #{$uid}");
            flash($res['ok'] ?? false ? 'success' : 'error', $res['ok'] ?? false ? 'Message sent.' : 'Failed to send (check bot token).');
        } else {
            flash('error', 'User not found.');
        }
    } elseif ($action === 'broadcast') {
        // Batched broadcast to avoid timeouts on shared hosting.
        $batch = max(1, min(200, (int) ($_POST['batch'] ?? 100)));
        $afterId = (int) ($_POST['after_id'] ?? 0);
        $users = Database::fetchAll('SELECT id, telegram_id FROM users WHERE status = "active" AND id > ? ORDER BY id ASC LIMIT ' . $batch, [$afterId]);
        $sent = 0; $last = $afterId;
        foreach ($users as $u) {
            Telegram::sendMessage((int) $u['telegram_id'], $message);
            $sent++;
            $last = (int) $u['id'];
            usleep(40000); // ~25 msgs/sec, within Telegram limits
        }
        Database::insert('notifications', ['user_id' => null, 'title' => 'Broadcast', 'body' => $message, 'created_at' => date('Y-m-d H:i:s')]);
        Logger::audit((int) $admin['id'], 'support.broadcast', "Broadcast batch sent: {$sent}");
        if ($sent === $batch) {
            flash('info', "Sent to {$sent} users. Continue the broadcast below (resume from ID {$last}).");
            $_SESSION['flash_resume'] = ['after_id' => $last, 'message' => $message];
        } else {
            flash('success', "Broadcast complete. Sent to {$sent} users in this batch.");
        }
    }
    header('Location: index.php?page=support');
    exit;
}

$totalUsers = (int) Database::scalar('SELECT COUNT(*) FROM users WHERE status = "active"');
$recent = Database::fetchAll('SELECT * FROM notifications ORDER BY id DESC LIMIT 15');
$resume = $_SESSION['flash_resume'] ?? null;
unset($_SESSION['flash_resume']);

adminHeader('Support', 'support', $admin);
?>
<div class="row g-3">
    <div class="col-lg-6"><div class="card"><div class="card-body">
        <h6 class="fw-bold mb-3">Direct Message</h6>
        <form method="post" action="index.php?page=support">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="direct">
            <div class="mb-2"><label class="form-label">User ID</label><input class="form-control" name="user_id" type="number" required></div>
            <div class="mb-2"><label class="form-label">Message (HTML allowed)</label><textarea class="form-control" name="message" rows="4" required></textarea></div>
            <button class="btn btn-purple">Send</button>
        </form>
    </div></div></div>

    <div class="col-lg-6"><div class="card"><div class="card-body">
        <h6 class="fw-bold mb-3">Broadcast <small class="text-muted">(<?= number_format($totalUsers) ?> active users)</small></h6>
        <form method="post" action="index.php?page=support">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="broadcast">
            <input type="hidden" name="after_id" value="<?= (int) ($resume['after_id'] ?? 0) ?>">
            <div class="mb-2"><label class="form-label">Batch size</label><input class="form-control" name="batch" type="number" value="100"></div>
            <div class="mb-2"><label class="form-label">Message (HTML allowed)</label><textarea class="form-control" name="message" rows="4" required><?= Security::e($resume['message'] ?? '') ?></textarea></div>
            <button class="btn btn-purple"><?= $resume ? 'Continue Broadcast' : 'Start Broadcast' ?></button>
            <small class="d-block text-muted mt-2">Large audiences are sent in batches to stay within shared-hosting limits. Re-submit to continue.</small>
        </form>
    </div></div></div>
</div>

<div class="card mt-3"><div class="card-body">
    <h6 class="fw-bold mb-3">Recent Notifications</h6>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>Type</th><th>Title</th><th>Message</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $n): ?>
            <tr>
                <td><?= $n['user_id'] ? 'Direct' : 'Broadcast' ?></td>
                <td><?= Security::e($n['title']) ?></td>
                <td><small><?= Security::e(mb_strimwidth((string) $n['body'], 0, 80, '…')) ?></small></td>
                <td><small><?= Security::e(date('M j, H:i', strtotime($n['created_at']))) ?></small></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="4" class="text-muted">No notifications sent.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?php adminFooter(); ?>
