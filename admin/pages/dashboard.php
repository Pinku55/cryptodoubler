<?php
/**
 * Admin dashboard: KPIs, earnings chart, latest withdrawals, top referrers.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

$totalUsers    = (int) Database::scalar('SELECT COUNT(*) FROM users');
$todayUsers    = (int) Database::scalar('SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()');
$onlineUsers   = (int) Database::scalar('SELECT COUNT(*) FROM users WHERE last_login >= (NOW() - INTERVAL 5 MINUTE)');
$totalEarnings = (int) Database::scalar('SELECT COALESCE(SUM(total_earned),0) FROM users');
$totalPaid     = (int) Database::scalar('SELECT COALESCE(SUM(amount_mt),0) FROM withdrawals WHERE status = "paid"');
$pendingWd     = (int) Database::scalar('SELECT COUNT(*) FROM withdrawals WHERE status = "pending"');

// 14-day earnings series (credits only).
$rows = Database::fetchAll(
    "SELECT DATE(created_at) d, COALESCE(SUM(amount),0) total
     FROM transactions WHERE amount > 0 AND created_at >= (CURDATE() - INTERVAL 13 DAY)
     GROUP BY DATE(created_at) ORDER BY d ASC"
);
$series = [];
for ($i = 13; $i >= 0; $i--) {
    $series[date('Y-m-d', strtotime("-{$i} day"))] = 0;
}
foreach ($rows as $r) {
    $series[$r['d']] = (int) $r['total'];
}

$latestWd = Database::fetchAll(
    'SELECT w.*, u.first_name, u.username FROM withdrawals w JOIN users u ON u.id = w.user_id ORDER BY w.id DESC LIMIT 8'
);
$topReferrers = Database::fetchAll(
    'SELECT first_name, username, total_referrals, total_earned FROM users WHERE total_referrals > 0 ORDER BY total_referrals DESC LIMIT 8'
);

adminHeader('Dashboard', 'dashboard', $admin);
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-2"><div class="stat-box g-purple"><div class="n"><?= number_format($totalUsers) ?></div><div class="l">Total Users</div></div></div>
    <div class="col-6 col-lg-2"><div class="stat-box g-blue"><div class="n"><?= number_format($todayUsers) ?></div><div class="l">Today's Users</div></div></div>
    <div class="col-6 col-lg-2"><div class="stat-box g-green"><div class="n"><?= number_format($onlineUsers) ?></div><div class="l">Online Now</div></div></div>
    <div class="col-6 col-lg-2"><div class="stat-box g-orange"><div class="n"><?= number_format($totalEarnings) ?></div><div class="l">Total Earned</div></div></div>
    <div class="col-6 col-lg-2"><div class="stat-box g-dark"><div class="n"><?= number_format($totalPaid) ?></div><div class="l">Total Paid</div></div></div>
    <div class="col-6 col-lg-2"><div class="stat-box g-red"><div class="n"><?= number_format($pendingWd) ?></div><div class="l">Pending Payouts</div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Earnings (last 14 days)</h6>
        <canvas id="earningsChart" height="90"></canvas>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Latest Withdrawals</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>User</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($latestWd as $w): ?>
                            <tr>
                                <td><?= Security::e($w['first_name'] ?: ('@' . $w['username'])) ?></td>
                                <td><?= mt((int) $w['amount_mt']) ?></td>
                                <td><?= Security::e($w['method_name']) ?></td>
                                <td><span class="badge-st st-<?= Security::e($w['status']) ?>"><?= Security::e($w['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$latestWd): ?><tr><td colspan="4" class="text-muted">No withdrawals yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Top Referrers</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>User</th><th>Refs</th><th>Earned</th></tr></thead>
                        <tbody>
                        <?php foreach ($topReferrers as $u): ?>
                            <tr><td><?= Security::e($u['first_name'] ?: ('@' . $u['username'])) ?></td><td><?= (int) $u['total_referrals'] ?></td><td><?= mt((int) $u['total_earned']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$topReferrers): ?><tr><td colspan="3" class="text-muted">No referrers yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    new Chart(document.getElementById('earningsChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('M j', strtotime($d)), array_keys($series))) ?>,
            datasets: [{
                label: 'MT Earned',
                data: <?= json_encode(array_values($series)) ?>,
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124,58,237,.12)',
                fill: true, tension: .35, pointRadius: 3
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
</script>
<?php adminFooter(); ?>
