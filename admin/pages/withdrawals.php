<?php
/**
 * Admin withdrawal management: approve / reject / mark paid, filter, export.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

// ----- POST actions (approve/reject/paid) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wid = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim((string) ($_POST['note'] ?? ''));
    $wd = Database::fetch('SELECT * FROM withdrawals WHERE id = ?', [$wid]);

    if ($wd) {
        if ($action === 'approve' && $wd['status'] === 'pending') {
            Database::update('withdrawals', ['status' => 'approved', 'admin_note' => $note, 'processed_by' => $admin['id'], 'processed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $wid]);
            Logger::audit((int) $admin['id'], 'withdraw.approve', "Approved #{$wid}");
            flash('success', "Withdrawal #{$wid} approved.");
        } elseif ($action === 'paid' && in_array($wd['status'], ['pending', 'approved'], true)) {
            Database::begin();
            try {
                Database::update('withdrawals', ['status' => 'paid', 'admin_note' => $note, 'processed_by' => $admin['id'], 'processed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $wid]);
                Database::query('UPDATE users SET total_withdrawn = total_withdrawn + ? WHERE id = ?', [(int) $wd['amount_mt'], (int) $wd['user_id']]);
                Database::commit();
            } catch (Throwable $e) {
                Database::rollback();
                flash('error', 'Could not mark paid.');
            }
            Logger::audit((int) $admin['id'], 'withdraw.paid', "Paid #{$wid}");
            flash('success', "Withdrawal #{$wid} marked as paid.");
            $u = User::findById((int) $wd['user_id']);
            if ($u) { Telegram::sendMessage((int) $u['telegram_id'], "✅ Your withdrawal of <b>" . number_format((int) $wd['amount_mt']) . " MT</b> has been paid!"); }
        } elseif ($action === 'reject' && in_array($wd['status'], ['pending', 'approved'], true)) {
            // Refund the held balance.
            Database::begin();
            try {
                User::adjustBalance((int) $wd['user_id'], (int) $wd['amount_mt'], User::TX_REFUND, "Withdrawal #{$wid} rejected refund");
                Database::update('withdrawals', ['status' => 'rejected', 'admin_note' => $note, 'processed_by' => $admin['id'], 'processed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $wid]);
                Database::commit();
            } catch (Throwable $e) {
                Database::rollback();
                flash('error', 'Could not reject.');
            }
            Logger::audit((int) $admin['id'], 'withdraw.reject', "Rejected #{$wid}");
            flash('warn', "Withdrawal #{$wid} rejected and refunded.");
            $u = User::findById((int) $wd['user_id']);
            if ($u) { Telegram::sendMessage((int) $u['telegram_id'], "❌ Your withdrawal of " . number_format((int) $wd['amount_mt']) . " MT was rejected. The amount has been refunded to your balance."); }
        }
    }
    header('Location: index.php?page=withdrawals&status=' . urlencode($_POST['filter_status'] ?? ''));
    exit;
}

$status = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if (in_array($status, ['pending', 'approved', 'rejected', 'paid'], true)) {
    $where .= ' AND w.status = ?';
    $params[] = $status;
}

// ----- CSV export -----
if (($_GET['export'] ?? '') === 'csv') {
    $rows = Database::fetchAll("SELECT w.*, u.username, u.telegram_id FROM withdrawals w JOIN users u ON u.id = w.user_id WHERE {$where} ORDER BY w.id DESC", $params);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="withdrawals.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User', 'TelegramID', 'Amount MT', 'Amount USD', 'Method', 'Status', 'Details', 'Created', 'Processed']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['username'], $r['telegram_id'], $r['amount_mt'], $r['amount_usd'], $r['method_name'], $r['status'], $r['account_details'], $r['created_at'], $r['processed_at']]);
    }
    fclose($out);
    exit;
}

$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($pageNum - 1) * $perPage;
$total = (int) Database::scalar("SELECT COUNT(*) FROM withdrawals w WHERE {$where}", $params);
$list = Database::fetchAll("SELECT w.*, u.first_name, u.username, u.telegram_id FROM withdrawals w JOIN users u ON u.id = w.user_id WHERE {$where} ORDER BY w.id DESC LIMIT {$perPage} OFFSET {$offset}", $params);
$pages = max(1, (int) ceil($total / $perPage));

adminHeader('Withdrawals', 'withdrawals', $admin);
?>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 justify-content-between">
    <div class="btn-group">
        <?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $k => $v): ?>
            <a class="btn btn-sm <?= $status === $k ? 'btn-purple' : 'btn-light' ?>" href="index.php?page=withdrawals&status=<?= urlencode($k) ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
    <a class="btn btn-sm btn-outline-success" href="index.php?page=withdrawals&status=<?= urlencode($status) ?>&export=csv"><i class="bi bi-download"></i> Export CSV</a>
</div></div>

<div class="card"><div class="card-body">
    <h6 class="fw-bold mb-3">Withdrawals (<?= number_format($total) ?>)</h6>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>#</th><th>User</th><th>Amount</th><th>Method</th><th>Details</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($list as $w): $details = $w['account_details'] ? json_decode($w['account_details'], true) : []; ?>
                <tr>
                    <td><?= (int) $w['id'] ?></td>
                    <td><?= Security::e($w['first_name'] ?: ('@' . $w['username'])) ?><br><small class="text-muted">TG <?= (int) $w['telegram_id'] ?></small></td>
                    <td><?= mt((int) $w['amount_mt']) ?><br><small class="text-muted">$<?= Security::e($w['amount_usd']) ?></small></td>
                    <td><?= Security::e($w['method_name']) ?></td>
                    <td><small><?php foreach ($details as $k => $v): ?><?= Security::e($k) ?>: <b><?= Security::e($v) ?></b><br><?php endforeach; ?></small></td>
                    <td><span class="badge-st st-<?= Security::e($w['status']) ?>"><?= Security::e($w['status']) ?></span></td>
                    <td><small><?= Security::e(date('M j, H:i', strtotime($w['created_at']))) ?></small></td>
                    <td class="text-nowrap">
                        <?php if (in_array($w['status'], ['pending', 'approved'], true)): ?>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#w<?= (int) $w['id'] ?>"><i class="bi bi-gear"></i> Process</button>
                        <?php else: ?>
                            <small class="text-muted"><?= Security::e($w['admin_note'] ?: '—') ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="8" class="text-muted">No withdrawals.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav><ul class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>"><a class="page-link" href="index.php?page=withdrawals&p=<?= $i ?>&status=<?= urlencode($status) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div></div>

<?php foreach ($list as $w): if (!in_array($w['status'], ['pending', 'approved'], true)) continue; $id = (int) $w['id']; ?>
<div class="modal fade" id="w<?= $id ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Process Withdrawal #<?= $id ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="index.php?page=withdrawals"><div class="modal-body">
        <?= Security::csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="filter_status" value="<?= Security::e($status) ?>">
        <p><b><?= mt((int) $w['amount_mt']) ?></b> via <b><?= Security::e($w['method_name']) ?></b></p>
        <div class="mb-3"><label class="form-label">Note (optional)</label><input class="form-control" name="note" value="<?= Security::e($w['admin_note'] ?? '') ?>"></div>
        <div class="d-flex gap-2">
            <button class="btn btn-success" name="action" value="paid">Mark Paid</button>
            <button class="btn btn-primary" name="action" value="approve">Approve</button>
            <button class="btn btn-danger" name="action" value="reject" onclick="return confirm('Reject and refund?')">Reject &amp; Refund</button>
        </div>
    </div></form>
</div></div></div>
<?php endforeach; ?>
<?php adminFooter(); ?>
