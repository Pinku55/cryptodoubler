<?php
/**
 * Admin reports: daily/weekly/monthly aggregates for users, earnings,
 * withdrawals, tasks and referrals, with CSV export.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

$range = $_GET['range'] ?? 'daily';
$intervals = ['daily' => 'INTERVAL 30 DAY', 'weekly' => 'INTERVAL 12 WEEK', 'monthly' => 'INTERVAL 12 MONTH'];
$groupFmt  = ['daily' => '%Y-%m-%d', 'weekly' => '%x-W%v', 'monthly' => '%Y-%m'];
$interval  = $intervals[$range] ?? $intervals['daily'];
$fmt       = $groupFmt[$range] ?? $groupFmt['daily'];

// New users over time.
$users = Database::fetchAll("SELECT DATE_FORMAT(created_at, '{$fmt}') g, COUNT(*) c FROM users WHERE created_at >= (CURDATE() - {$interval}) GROUP BY g ORDER BY g ASC");
// Earnings (credits) over time.
$earn = Database::fetchAll("SELECT DATE_FORMAT(created_at, '{$fmt}') g, COALESCE(SUM(amount),0) c FROM transactions WHERE amount > 0 AND created_at >= (CURDATE() - {$interval}) GROUP BY g ORDER BY g ASC");
// Withdrawals paid over time.
$wd = Database::fetchAll("SELECT DATE_FORMAT(processed_at, '{$fmt}') g, COALESCE(SUM(amount_mt),0) c FROM withdrawals WHERE status='paid' AND processed_at >= (CURDATE() - {$interval}) GROUP BY g ORDER BY g ASC");

// Summary counters.
$sum = [
    'users'       => (int) Database::scalar('SELECT COUNT(*) FROM users'),
    'tasks_done'  => (int) Database::scalar('SELECT COUNT(*) FROM task_completions WHERE status="completed"'),
    'ads'         => (int) Database::scalar('SELECT COUNT(*) FROM ad_views'),
    'referrals'   => (int) Database::scalar('SELECT COUNT(*) FROM users WHERE referred_by IS NOT NULL'),
    'paid'        => (int) Database::scalar('SELECT COALESCE(SUM(amount_mt),0) FROM withdrawals WHERE status="paid"'),
];

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $range . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Period', 'New Users', 'MT Earned', 'MT Paid']);
    $merge = [];
    foreach ($users as $r) { $merge[$r['g']]['u'] = $r['c']; }
    foreach ($earn as $r) { $merge[$r['g']]['e'] = $r['c']; }
    foreach ($wd as $r) { $merge[$r['g']]['w'] = $r['c']; }
    ksort($merge);
    foreach ($merge as $g => $v) { fputcsv($out, [$g, $v['u'] ?? 0, $v['e'] ?? 0, $v['w'] ?? 0]); }
    fclose($out);
    exit;
}

$toSeries = function (array $rows) {
    $labels = []; $data = [];
    foreach ($rows as $r) { $labels[] = $r['g']; $data[] = (int) $r['c']; }
    return [$labels, $data];
};
[$uL, $uD] = $toSeries($users);
[$eL, $eD] = $toSeries($earn);
[$wL, $wD] = $toSeries($wd);

adminHeader('Reports', 'reports', $admin);
?>
<div class="card mb-3"><div class="card-body d-flex justify-content-between flex-wrap gap-2">
    <div class="btn-group">
        <?php foreach (['daily', 'weekly', 'monthly'] as $r): ?>
            <a class="btn btn-sm <?= $range === $r ? 'btn-purple' : 'btn-light' ?>" href="index.php?page=reports&range=<?= $r ?>"><?= ucfirst($r) ?></a>
        <?php endforeach; ?>
    </div>
    <a class="btn btn-sm btn-outline-success" href="index.php?page=reports&range=<?= $range ?>&export=csv"><i class="bi bi-download"></i> Export CSV</a>
</div></div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md"><div class="stat-box g-purple"><div class="n"><?= number_format($sum['users']) ?></div><div class="l">Users</div></div></div>
    <div class="col-6 col-md"><div class="stat-box g-green"><div class="n"><?= number_format($sum['tasks_done']) ?></div><div class="l">Tasks Done</div></div></div>
    <div class="col-6 col-md"><div class="stat-box g-blue"><div class="n"><?= number_format($sum['ads']) ?></div><div class="l">Ads Watched</div></div></div>
    <div class="col-6 col-md"><div class="stat-box g-orange"><div class="n"><?= number_format($sum['referrals']) ?></div><div class="l">Referrals</div></div></div>
    <div class="col-12 col-md"><div class="stat-box g-dark"><div class="n"><?= number_format($sum['paid']) ?></div><div class="l">MT Paid</div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-4"><div class="card"><div class="card-body"><h6 class="fw-bold">New Users</h6><canvas id="cU" height="160"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body"><h6 class="fw-bold">MT Earned</h6><canvas id="cE" height="160"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body"><h6 class="fw-bold">MT Paid Out</h6><canvas id="cW" height="160"></canvas></div></div></div>
</div>

<script>
    const mk = (id, labels, data, color) => new Chart(document.getElementById(id), {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: color, borderRadius: 6 }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    mk('cU', <?= json_encode($uL) ?>, <?= json_encode($uD) ?>, '#7c3aed');
    mk('cE', <?= json_encode($eL) ?>, <?= json_encode($eD) ?>, '#16a34a');
    mk('cW', <?= json_encode($wL) ?>, <?= json_encode($wD) ?>, '#f59e0b');
</script>
<?php adminFooter(); ?>
