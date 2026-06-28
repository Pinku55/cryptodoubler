<?php
/**
 * Admin settings: site, economy, Telegram, integrations, maintenance.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'set_webhook') {
        $url = trim((string) ($_POST['webhook_url'] ?? ''));
        Settings::set('webhook_url', $url);
        $secret = Settings::get('webhook_secret', '');
        if ($secret === '') { $secret = bin2hex(random_bytes(16)); Settings::set('webhook_secret', $secret); }
        $res = Telegram::setWebhook($url, $secret);
        Logger::audit((int) $admin['id'], 'settings.webhook', 'Set webhook to ' . $url);
        flash($res['ok'] ?? false ? 'success' : 'error', $res['ok'] ?? false ? 'Webhook registered with Telegram.' : ('Webhook failed: ' . ($res['description'] ?? 'unknown')));
        header('Location: index.php?page=settings');
        exit;
    }

    // Plain text settings.
    $textKeys = [
        'site_name', 'currency_symbol', 'theme_color', 'mt_per_usd', 'min_withdraw',
        'telegram_bot_token', 'telegram_bot_username', 'webhook_url', 'monetag_zone_id',
        'support_username', 'privacy_url', 'terms_url', 'announcement', 'admin_chat_id',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
    ];
    foreach ($textKeys as $k) {
        if (array_key_exists($k, $_POST)) {
            Settings::set($k, trim((string) $_POST[$k]));
        }
    }
    Settings::set('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
    Settings::set('one_account_per_ip', isset($_POST['one_account_per_ip']) ? '1' : '0');

    // Logo / favicon upload.
    foreach (['logo', 'favicon'] as $imgKey) {
        if (!empty($_FILES[$imgKey]['tmp_name']) && is_uploaded_file($_FILES[$imgKey]['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES[$imgKey]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'ico'], true)) {
                if (!is_dir(UPLOADS_PATH)) { @mkdir(UPLOADS_PATH, 0755, true); }
                $fname = $imgKey . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES[$imgKey]['tmp_name'], UPLOADS_PATH . '/' . $fname)) {
                    Settings::set($imgKey, 'assets/uploads/' . $fname);
                }
            }
        }
    }

    Logger::audit((int) $admin['id'], 'settings.update', 'Updated settings');
    flash('success', 'Settings saved.');
    header('Location: index.php?page=settings');
    exit;
}

$g = fn(string $k, string $d = '') => Security::e(Settings::get($k, $d));
adminHeader('Settings', 'settings', $admin);
?>
<form method="post" action="index.php?page=settings" enctype="multipart/form-data">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="save">
    <div class="row g-3">
        <div class="col-lg-6"><div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">General</h6>
            <div class="mb-2"><label class="form-label">Website Name</label><input class="form-control" name="site_name" value="<?= $g('site_name') ?>"></div>
            <div class="row">
                <div class="col-6 mb-2"><label class="form-label">Currency Symbol</label><input class="form-control" name="currency_symbol" value="<?= $g('currency_symbol', 'MT') ?>"></div>
                <div class="col-6 mb-2"><label class="form-label">Theme Color</label><input class="form-control" type="color" name="theme_color" value="<?= $g('theme_color', '#7c3aed') ?>"></div>
            </div>
            <div class="mb-2"><label class="form-label">Logo</label><input class="form-control" type="file" name="logo" accept="image/*"><?php if (Settings::get('logo')): ?><small class="text-muted">Current: <?= $g('logo') ?></small><?php endif; ?></div>
            <div class="mb-2"><label class="form-label">Favicon</label><input class="form-control" type="file" name="favicon" accept="image/*"></div>
        </div></div></div>

        <div class="col-lg-6"><div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">Economy</h6>
            <div class="mb-2"><label class="form-label">MT per 1 USD</label><input class="form-control" type="number" name="mt_per_usd" value="<?= $g('mt_per_usd', '10000') ?>"></div>
            <div class="mb-2"><label class="form-label">Minimum Withdrawal (MT)</label><input class="form-control" type="number" name="min_withdraw" value="<?= $g('min_withdraw', '20000') ?>"></div>
            <p class="text-muted small mb-0">Tip: configure rewards per feature on their dedicated pages (Ads, Bonus, Referrals).</p>
        </div></div></div>

        <div class="col-lg-6"><div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">Telegram</h6>
            <div class="mb-2"><label class="form-label">Bot Token</label><input class="form-control" name="telegram_bot_token" value="<?= $g('telegram_bot_token') ?>"></div>
            <div class="mb-2"><label class="form-label">Bot Username (no @)</label><input class="form-control" name="telegram_bot_username" value="<?= $g('telegram_bot_username') ?>"></div>
            <div class="mb-2"><label class="form-label">Admin Chat ID (for alerts)</label><input class="form-control" name="admin_chat_id" value="<?= $g('admin_chat_id') ?>"></div>
        </div></div></div>

        <div class="col-lg-6"><div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">Integrations</h6>
            <div class="mb-2"><label class="form-label">Monetag Zone ID</label><input class="form-control" name="monetag_zone_id" value="<?= $g('monetag_zone_id') ?>"></div>
            <div class="mb-2"><label class="form-label">Support Username (no @)</label><input class="form-control" name="support_username" value="<?= $g('support_username') ?>"></div>
            <div class="row">
                <div class="col-6 mb-2"><label class="form-label">Privacy URL</label><input class="form-control" name="privacy_url" value="<?= $g('privacy_url') ?>"></div>
                <div class="col-6 mb-2"><label class="form-label">Terms URL</label><input class="form-control" name="terms_url" value="<?= $g('terms_url') ?>"></div>
            </div>
        </div></div></div>

        <div class="col-lg-6"><div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">SMTP (optional email)</h6>
            <div class="row">
                <div class="col-8 mb-2"><label class="form-label">Host</label><input class="form-control" name="smtp_host" value="<?= $g('smtp_host') ?>"></div>
                <div class="col-4 mb-2"><label class="form-label">Port</label><input class="form-control" name="smtp_port" value="<?= $g('smtp_port') ?>"></div>
            </div>
            <div class="mb-2"><label class="form-label">Username</label><input class="form-control" name="smtp_user" value="<?= $g('smtp_user') ?>"></div>
            <div class="mb-2"><label class="form-label">Password</label><input class="form-control" type="password" name="smtp_pass" value="<?= $g('smtp_pass') ?>"></div>
            <div class="mb-2"><label class="form-label">From Address</label><input class="form-control" name="smtp_from" value="<?= $g('smtp_from') ?>"></div>
        </div></div></div>

        <div class="col-lg-6"><div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">Maintenance &amp; Announcement</h6>
            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="maintenance_mode" id="mm" <?= Settings::getBool('maintenance_mode') ? 'checked' : '' ?>><label class="form-check-label" for="mm">Maintenance mode (blocks the Mini App)</label></div>
            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="one_account_per_ip" id="oapi" <?= Settings::getBool('one_account_per_ip', true) ? 'checked' : '' ?>><label class="form-check-label" for="oapi"><i class="bi bi-shield-lock"></i> Allow only one account per IP address</label></div>
            <div class="mb-2"><label class="form-label">Announcement / maintenance message</label><textarea class="form-control" name="announcement" rows="3"><?= $g('announcement') ?></textarea></div>
        </div></div></div>
    </div>
    <button class="btn btn-purple mt-3 mb-4">Save All Settings</button>
</form>

<div class="card mb-4"><div class="card-body">
    <h6 class="fw-bold mb-3">Webhook</h6>
    <form method="post" action="index.php?page=settings" class="row g-2 align-items-end">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="set_webhook">
        <div class="col-md-9"><label class="form-label">Webhook URL</label><input class="form-control" name="webhook_url" value="<?= $g('webhook_url') ?>"></div>
        <div class="col-md-3"><button class="btn btn-purple w-100">Register Webhook</button></div>
    </form>
    <small class="text-muted">Registers this URL with Telegram for your bot. Must be publicly reachable over HTTPS.</small>
</div></div>
<?php adminFooter(); ?>
