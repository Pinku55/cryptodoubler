<?php
/**
 * Admin user management: search, filter, pagination + per-user actions.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

// ----- Handle POST actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid = (int) ($_POST['user_id'] ?? 0);
    $user = User::findById($uid);

    if ($user) {
        switch ($action) {
            case 'ban':
                Database::update('users', ['status' => 'banned'], 'id = :id', ['id' => $uid]);
                Logger::audit((int) $admin['id'], 'user.ban', "Banned user #{$uid}");
                flash('success', 'User banned.');
                break;
            case 'unban':
                Database::update('users', ['status' => 'active'], 'id = :id', ['id' => $uid]);
                Logger::audit((int) $admin['id'], 'user.unban', "Unbanned user #{$uid}");
                flash('success', 'User unbanned.');
                break;
            case 'adjust':
                $amount = (int) ($_POST['amount'] ?? 0);
                if ($amount !== 0) {
                    try {
                        User::adjustBalance($uid, $amount, User::TX_ADMIN, 'Admin balance adjustment', ['by' => $admin['id']]);
                        Logger::audit((int) $admin['id'], 'user.adjust', "Adjusted #{$uid} by {$amount} MT");
                        flash('success', 'Balance adjusted by ' . number_format($amount) . ' MT.');
                    } catch (Throwable $e) {
                        flash('error', $e->getMessage());
                    }
                }
                break;
            case 'reset_bonus':
                Database::update('users', ['daily_streak' => 0, 'last_bonus_date' => null], 'id = :id', ['id' => $uid]);
                Logger::audit((int) $admin['id'], 'user.reset_bonus', "Reset bonus #{$uid}");
                flash('success', 'Daily bonus reset.');
                break;
            case 'reset_referral':
                Database::update('users', ['total_referrals' => 0], 'id = :id', ['id' => $uid]);
                Logger::audit((int) $admin['id'], 'user.reset_referral', "Reset referrals #{$uid}");
                flash('success', 'Referral count reset.');
                break;
            case 'delete':
                Database::query('DELETE FROM users WHERE id = ?', [$uid]);
                Logger::audit((int) $admin['id'], 'user.delete', "Deleted user #{$uid}");
                flash('warn', 'User deleted.');
                break;
        }
    }
    header('Location: index.php?page=users&' . http_build_query(['q' => $_POST['q'] ?? '', 'status' => $_POST['status'] ?? '']));
    exit;
}

// ----- Listing -----
$q       = trim((string) ($_GET['q'] ?? ''));
$status  = $_GET['status'] ?? '';
$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($pageNum - 1) * $perPage;

$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (username LIKE ? OR first_name LIKE ? OR CAST(telegram_id AS CHAR) LIKE ? OR referral_code LIKE ?)';
    $like = "%{$q}%";
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, ['active', 'banned'], true)) {
    $where .= ' AND status = ?';
    $params[] = $status;
}

$total = (int) Database::scalar("SELECT COUNT(*) FROM users WHERE {$where}", $params);
$users = Database::fetchAll("SELECT * FROM users WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}", $params);
$pages = max(1, (int) ceil($total / $perPage));

adminHeader('Users', 'users', $admin);
?>
<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get" action="index.php">
        <input type="hidden" name="page" value="users">
        <div class="col-md-6"><input class="form-control" name="q" value="<?= Security::e($q) ?>" placeholder="Search username, name, Telegram ID or referral code"></div>
        <div class="col-md-3">
            <select class="form-select" name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="banned" <?= $status === 'banned' ? 'selected' : '' ?>>Banned</option>
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-purple w-100"><i class="bi bi-search"></i> Search</button></div>
    </form>
</div></div>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between mb-2"><h6 class="fw-bold mb-0">Users (<?= number_format($total) ?>)</h6></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>ID</th><th>User</th><th>Balance</th><th>Earned</th><th>Refs</th><th>Status</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int) $u['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= Security::e($u['first_name'] ?: 'User') ?></div>
                        <small class="text-muted"><?= $u['username'] ? '@' . Security::e($u['username']) : '' ?> · TG <?= (int) $u['telegram_id'] ?></small>
                    </td>
                    <td><?= mt((int) $u['balance']) ?></td>
                    <td><?= mt((int) $u['total_earned']) ?></td>
                    <td><?= (int) $u['total_referrals'] ?></td>
                    <td><span class="badge-st st-<?= Security::e($u['status']) ?>"><?= Security::e($u['status']) ?></span></td>
                    <td><small><?= Security::e(date('M j, Y', strtotime($u['created_at']))) ?></small></td>
                    <td>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#m<?= (int) $u['id'] ?>"><i class="bi bi-three-dots"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="8" class="text-muted">No users found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <nav><ul class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>"><a class="page-link" href="index.php?page=users&p=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div></div>

<!-- Per-user action modals -->
<?php foreach ($users as $u): $id = (int) $u['id']; ?>
<div class="modal fade" id="m<?= $id ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Manage <?= Security::e($u['first_name'] ?: ('#' . $id)) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="mb-1"><b>Balance:</b> <?= mt((int) $u['balance']) ?> · <b>Referral:</b> <code><?= Security::e($u['referral_code']) ?></code></p>
            <hr>
            <form method="post" action="index.php?page=users" class="mb-3">
                <?= Security::csrfField() ?>
                <input type="hidden" name="user_id" value="<?= $id ?>">
                <input type="hidden" name="action" value="adjust">
                <label class="form-label">Adjust balance (use negative to deduct)</label>
                <div class="input-group">
                    <input class="form-control" type="number" name="amount" placeholder="e.g. 5000 or -2000" required>
                    <button class="btn btn-purple">Apply</button>
                </div>
            </form>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($u['status'] === 'active'): ?>
                    <form method="post" action="index.php?page=users"><?= Security::csrfField() ?><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="action" value="ban"><button class="btn btn-sm btn-danger">Ban</button></form>
                <?php else: ?>
                    <form method="post" action="index.php?page=users"><?= Security::csrfField() ?><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="action" value="unban"><button class="btn btn-sm btn-success">Unban</button></form>
                <?php endif; ?>
                <form method="post" action="index.php?page=users"><?= Security::csrfField() ?><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="action" value="reset_bonus"><button class="btn btn-sm btn-light">Reset Bonus</button></form>
                <form method="post" action="index.php?page=users"><?= Security::csrfField() ?><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="action" value="reset_referral"><button class="btn btn-sm btn-light">Reset Referrals</button></form>
                <a class="btn btn-sm btn-outline-secondary" href="index.php?page=transactions&user_id=<?= $id ?>">History</a>
                <form method="post" action="index.php?page=users" onsubmit="return confirm('Delete this user permanently?')"><?= Security::csrfField() ?><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
            </div>
        </div>
    </div></div>
</div>
<?php endforeach; ?>

<?php adminFooter(); ?>
