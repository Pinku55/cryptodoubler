<?php
/**
 * Admin audit logs viewer.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 40;
$offset = ($pageNum - 1) * $perPage;
$total = (int) Database::scalar('SELECT COUNT(*) FROM admin_logs');
$logs = Database::fetchAll(
    'SELECT l.*, a.username FROM admin_logs l LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$pages = max(1, (int) ceil($total / $perPage));

adminHeader('Admin Logs', 'logs', $admin);
?>
<div class="card"><div class="card-body">
    <h6 class="fw-bold mb-3">Audit Trail (<?= number_format($total) ?>)</h6>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>#</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= (int) $l['id'] ?></td>
                <td><?= Security::e($l['username'] ?? 'system') ?></td>
                <td><span class="badge bg-light text-dark"><?= Security::e($l['action']) ?></span></td>
                <td><small><?= Security::e($l['details']) ?></small></td>
                <td><small><?= Security::e($l['ip_address']) ?></small></td>
                <td><small><?= Security::e(date('M j, H:i:s', strtotime($l['created_at']))) ?></small></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="6" class="text-muted">No log entries.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($pages > 1): ?>
    <nav><ul class="pagination">
        <?php for ($i = max(1, $pageNum - 3); $i <= min($pages, $pageNum + 3); $i++): ?>
            <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>"><a class="page-link" href="index.php?page=logs&p=<?= $i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div></div>
<?php adminFooter(); ?>
