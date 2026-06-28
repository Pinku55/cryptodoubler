<?php
/**
 * Admin rewarded ads management (Monetag settings + anti-spam).
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $map = [
        'monetag_zone_id' => (string) ($_POST['monetag_zone_id'] ?? ''),
        'ad_reward'       => (int) ($_POST['ad_reward'] ?? 0),
        'ad_cooldown'     => (int) ($_POST['ad_cooldown'] ?? 0),
        'ad_daily_limit'  => (int) ($_POST['ad_daily_limit'] ?? 0),
        'ad_max_earnings' => (int) ($_POST['ad_max_earnings'] ?? 0),
        'ads_enabled'     => isset($_POST['ads_enabled']) ? '1' : '0',
    ];
    foreach ($map as $k => $v) {
        Settings::set($k, $v);
    }
    Logger::audit((int) $admin['id'], 'ads.update', 'Updated ad settings');
    flash('success', 'Ad settings saved.');
    header('Location: index.php?page=ads');
    exit;
}

$todayViews = (int) Database::scalar('SELECT COUNT(*) FROM ad_views WHERE DATE(created_at) = CURDATE()');
$todayMt    = (int) Database::scalar('SELECT COALESCE(SUM(reward),0) FROM ad_views WHERE DATE(created_at) = CURDATE()');
$totalViews = (int) Database::scalar('SELECT COUNT(*) FROM ad_views');

adminHeader('Rewarded Ads', 'ads', $admin);
?>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="stat-box g-purple"><div class="n"><?= number_format($todayViews) ?></div><div class="l">Ads watched today</div></div></div>
    <div class="col-md-4"><div class="stat-box g-green"><div class="n"><?= number_format($todayMt) ?></div><div class="l">MT paid today</div></div></div>
    <div class="col-md-4"><div class="stat-box g-blue"><div class="n"><?= number_format($totalViews) ?></div><div class="l">Total ads watched</div></div></div>
</div>
<div class="card"><div class="card-body">
    <form method="post" action="index.php?page=ads">
        <?= Security::csrfField() ?>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Monetag Zone ID</label><input class="form-control" name="monetag_zone_id" value="<?= Security::e(Settings::get('monetag_zone_id', '')) ?>"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Reward per ad (MT)</label><input class="form-control" type="number" name="ad_reward" value="<?= Settings::getInt('ad_reward') ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Cooldown (seconds)</label><input class="form-control" type="number" name="ad_cooldown" value="<?= Settings::getInt('ad_cooldown') ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Daily limit (0 = ∞)</label><input class="form-control" type="number" name="ad_daily_limit" value="<?= Settings::getInt('ad_daily_limit') ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Max daily earnings (0 = ∞)</label><input class="form-control" type="number" name="ad_max_earnings" value="<?= Settings::getInt('ad_max_earnings') ?>"></div>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="ads_enabled" id="adsEnabled" <?= Settings::getBool('ads_enabled') ? 'checked' : '' ?>>
            <label class="form-check-label" for="adsEnabled">Rewarded ads enabled</label>
        </div>
        <p class="text-muted small"><i class="bi bi-shield-check"></i> Anti-spam is enforced server-side via cooldown, daily limits, a rate limiter and per-view IP logging. Rewards are only credited after the Monetag popup resolves.</p>
        <button class="btn btn-purple">Save Settings</button>
    </form>
</div></div>
<?php adminFooter(); ?>
