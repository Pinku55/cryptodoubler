<?php
/**
 * Admin transactions browser: filter by type / user, paginated, CSV export.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

$type   = $_GET['type'] ?? '';
$userId = (int) ($_GET['user_id'] ?? 0);

$where = '1=1';
$params = [];
if ($type !== '') { $where .= ' AND t.type = ?'; $params[] = $type; }
if ($userId > 0) { $where .= ' AND t.user_id = ?'; $params[] = $userId; }

if (($_GET['export'] ?? '') === 'csv') {
    $rows = Database::fetchAll("SELECT t.*, u.username FROM transactions t JOIN users u ON u.id = t.user_id WHERE {$where} ORDER BY t.id DESC", $params);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User', 'Type', 'Amount', 'Balance After', 'Note', 'Status', 'Date']);
    foreach ($rows as $r) { fputcsv($out, [$r['id'], $r['username'], $r['type'], $r['amount'], $r['balance_after'], $r['note'], $r['status'], $r['created_at']]); }
    fclose($out);
    exit;
}

$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 30;
$offset = ($pageNum - 1) * $perPage;
$total = (int) Database::scalar("SELECT COUNT(*) FROM transactions t WHERE {$where}", $params);
$rows = Database::fetchAll("SELECT t.*, u.username, u.first_name FROM transactions t JOIN users u ON u.id = t.user_id WHERE {$where} ORDER BY t.id DESC LIMIT {$perPage} OFFSET {$offset}", $params);
$pages = max(1, (int) ceil($total / $perPage));

adminHeader('Transactions', 'transactions', $admin);
?>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <form class="d-flex gap-2" method="get" action="index.php">
        <input type="hidden" name="page" value="transactions">
        <?php if ($userId): ?><input type="hidden" name="user_id" value="<?= $userId ?>"><?php endif; ?>
        <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
            <option value="">All types</option>
            <?php foreach (['ad', 'task', 'daily_bonus', 'referral', 'withdraw', 'admin_adjust', 'refund'] as $t): ?>
                <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($userId): ?><a class="btn btn-sm btn-light" href="index.php?page=transactions">Clear user filter</a><?php endif; ?>
    </form>
    <a class="btn btn-sm btn-outline-success" href="index.php?page=transactions&type=<?= urlencode($type) ?>&user_id=<?= $userId ?>&export=csv"><i class="bi bi-download"></i> Export CSV</a>
</div></div>

<div class="card"><div class="card-body">
    <h6 class="fw-bold mb-3">Transactions (<?= number_format($total) ?>)</h6>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>#</th><th>User</th><th>Type</th><th>Amount</th><th>Balance</th><th>Note</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td><?= Security::e($r['first_name'] ?: ('@' . $r['username'])) ?></td>
                <td><span class="badge bg-light text-dark"><?= Security::e($r['type']) ?></span></td>
                <td class="<?= (int) $r['amount'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= ((int) $r['amount'] >= 0 ? '+' : '') . number_format((int) $r['amount']) ?></td>
                <td><?= number_format((int) $r['balance_after']) ?></td>
                <td><small><?= Security::e($r['note']) ?></small></td>
                <td><small><?= Security::e(date('M j, H:i', strtotime($r['created_at']))) ?></small></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">No transactions.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($pages > 1): ?>
    <nav><ul class="pagination">
        <?php for ($i = max(1, $pageNum - 3); $i <= min($pages, $pageNum + 3); $i++): ?>
            <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>"><a class="page-link" href="index.php?page=transactions&p=<?= $i ?>&type=<?= urlencode($type) ?>&user_id=<?= $userId ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div></div>
<?php adminFooter(); ?>
