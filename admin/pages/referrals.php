<?php
/**
 * Admin referral management (reward, limits, fraud protection) + top list.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ([
        'referral_reward'       => (int) ($_POST['referral_reward'] ?? 0),
        'referral_max'          => (int) ($_POST['referral_max'] ?? 0),
        'referral_min_activity' => (int) ($_POST['referral_min_activity'] ?? 0),
    ] as $k => $v) {
        Settings::set($k, $v);
    }
    Logger::audit((int) $admin['id'], 'referral.update', 'Updated referral settings');
    flash('success', 'Referral settings saved.');
    header('Location: index.php?page=referrals');
    exit;
}

$totalRefUsers = (int) Database::scalar('SELECT COUNT(*) FROM users WHERE referred_by IS NOT NULL');
$totalRefPaid  = (int) Database::scalar('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = "referral"');
$top = Database::fetchAll('SELECT first_name, username, total_referrals, total_earned FROM users WHERE total_referrals > 0 ORDER BY total_referrals DESC LIMIT 20');

adminHeader('Referrals', 'referrals', $admin);
?>
<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="stat-box g-blue"><div class="n"><?= number_format($totalRefUsers) ?></div><div class="l">Referred users</div></div></div>
    <div class="col-md-6"><div class="stat-box g-green"><div class="n"><?= number_format($totalRefPaid) ?></div><div class="l">MT paid in referrals</div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><div class="card-body">
        <h6 class="fw-bold mb-3">Settings</h6>
        <form method="post" action="index.php?page=referrals">
            <?= Security::csrfField() ?>
            <div class="mb-3"><label class="form-label">Reward per referral (MT)</label><input class="form-control" type="number" name="referral_reward" value="<?= Settings::getInt('referral_reward') ?>"></div>
            <div class="mb-3"><label class="form-label">Max referrals per user (0 = ∞)</label><input class="form-control" type="number" name="referral_max" value="<?= Settings::getInt('referral_max') ?>"></div>
            <div class="mb-3"><label class="form-label">Min activity before reward (reserved)</label><input class="form-control" type="number" name="referral_min_activity" value="<?= Settings::getInt('referral_min_activity') ?>"></div>
            <p class="text-muted small"><i class="bi bi-shield-check"></i> Fraud protection: self-referral is blocked (a user cannot refer themselves), IPs are logged and duplicate Telegram accounts are prevented by a unique constraint.</p>
            <button class="btn btn-purple">Save</button>
        </form>
    </div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-body">
        <h6 class="fw-bold mb-3">Top Referrers</h6>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>User</th><th>Referrals</th><th>Total Earned</th></tr></thead>
            <tbody>
            <?php foreach ($top as $u): ?>
                <tr><td><?= Security::e($u['first_name'] ?: ('@' . $u['username'])) ?></td><td><?= (int) $u['total_referrals'] ?></td><td><?= mt((int) $u['total_earned']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$top): ?><tr><td colspan="3" class="text-muted">No referrers yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div></div></div>
</div>
<?php adminFooter(); ?>
