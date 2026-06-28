<?php
/**
 * MTASK - Installation Wizard
 * --------------------------------------------------------------------------
 * A self-contained, dependency-free installer for cPanel shared hosting.
 * Walks the operator through database setup, admin account creation,
 * Telegram bot configuration, webhook + Monetag setup, and finally writes
 * config.php, imports the schema, and seeds default data.
 *
 * Visit /install.php in a browser after uploading the files.
 *
 * @package MTASK
 */

declare(strict_types=1);

session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_PATH', __DIR__);
define('CONFIG_FILE', BASE_PATH . '/config/config.php');
define('LOCK_FILE', BASE_PATH . '/storage/installed.lock');

// ---------------------------------------------------------------------------
// Guard: already installed?
// ---------------------------------------------------------------------------
$alreadyInstalled = file_exists(CONFIG_FILE) && file_exists(LOCK_FILE);
$forceReinstall = isset($_GET['force']) && $_GET['force'] === '1';

$step = (int) ($_GET['step'] ?? 1);
$errors = [];
$notice = '';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $_SESSION['install'][$key] ?? $default));
}

function saveStep(array $data): void
{
    $_SESSION['install'] = array_merge($_SESSION['install'] ?? [], $data);
}

function baseUrlGuess(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . $dir;
}

/** Test a PDO connection with the given credentials. */
function testDb(array $c): array
{
    try {
        $dsn = "mysql:host={$c['host']};port={$c['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Ensure the database exists (create if missing & permitted).
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$c['name']}`");
        return [true, $pdo];
    } catch (Throwable $e) {
        return [false, $e->getMessage()];
    }
}

/** Run the schema + seed data and create the first admin. */
function runInstall(array $i): array
{
    $errors = [];

    // 1) Connect.
    [$ok, $pdoOrErr] = testDb($i);
    if (!$ok) {
        return ['Database connection failed: ' . $pdoOrErr];
    }
    /** @var PDO $pdo */
    $pdo = $pdoOrErr;
    $pdo->exec("USE `{$i['name']}`");

    // 2) Import schema.
    $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
    if ($schema === false) {
        return ['Could not read database/schema.sql'];
    }
    try {
        $pdo->exec($schema);
    } catch (Throwable $e) {
        return ['Schema import failed: ' . $e->getMessage()];
    }

    // 3) Seed defaults.
    $defaults = require BASE_PATH . '/database/defaults.php';

    // Merge wizard values into settings.
    $defaults['settings']['telegram_bot_token']    = $i['bot_token'];
    $defaults['settings']['telegram_bot_username'] = $i['bot_username'];
    $defaults['settings']['webhook_url']           = $i['webhook_url'];
    $defaults['settings']['webhook_secret']        = bin2hex(random_bytes(16));
    $defaults['settings']['monetag_zone_id']       = $i['monetag_zone'];
    $defaults['settings']['site_name']             = $i['site_name'];

    try {
        $stmt = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?)
                               ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        foreach ($defaults['settings'] as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // Daily bonus ladder.
        $dbStmt = $pdo->prepare('INSERT INTO daily_bonus_rewards (`day`,`reward`) VALUES (?,?)
                                 ON DUPLICATE KEY UPDATE `reward` = VALUES(`reward`)');
        foreach ($defaults['daily_bonus'] as $day => $reward) {
            $dbStmt->execute([$day, $reward]);
        }

        // Payment methods (only if table empty).
        $hasPm = (int) $pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn();
        if ($hasPm === 0) {
            $pmStmt = $pdo->prepare('INSERT INTO payment_methods (name,code,icon,min_amount,fields,status,sort_order) VALUES (?,?,?,?,?,?,?)');
            foreach ($defaults['payment_methods'] as $pm) {
                $pmStmt->execute([$pm['name'], $pm['code'], $pm['icon'], $pm['min_amount'], json_encode($pm['fields']), 'active', $pm['sort_order']]);
            }
        }

        // Sample tasks (only if table empty).
        $hasTasks = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
        if ($hasTasks === 0) {
            $tStmt = $pdo->prepare('INSERT INTO tasks (title,description,category,url,reward,wait_time,verify_type,status) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($defaults['tasks'] as $t) {
                $tStmt->execute([$t['title'], $t['description'], $t['category'], $t['url'], $t['reward'], $t['wait_time'], $t['verify_type'], 'active']);
            }
        }

        // 4) Create the admin account.
        $hash = password_hash($i['admin_pass'], PASSWORD_BCRYPT, ['cost' => 12]);
        $aStmt = $pdo->prepare('INSERT INTO admins (username,email,password,role,status) VALUES (?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE password = VALUES(password), email = VALUES(email)');
        $aStmt->execute([$i['admin_user'], $i['admin_email'], $hash, 'super_admin', 'active']);
    } catch (Throwable $e) {
        return ['Seeding failed: ' . $e->getMessage()];
    }

    // 5) Write config.php from the sample template.
    $template = file_get_contents(BASE_PATH . '/config/config.sample.php');
    if ($template === false) {
        return ['Could not read config sample template'];
    }
    $appKey = bin2hex(random_bytes(32));
    $config = strtr($template, [
        '__DB_HOST__'   => addslashes($i['host']),
        '__DB_PORT__'   => addslashes($i['port']),
        '__DB_NAME__'   => addslashes($i['name']),
        '__DB_USER__'   => addslashes($i['user']),
        '__DB_PASS__'   => addslashes($i['pass']),
        '__BASE_URL__'  => addslashes(rtrim($i['base_url'], '/')),
        '__APP_KEY__'   => $appKey,
        '__BOT_TOKEN__' => addslashes($i['bot_token']),
    ]);

    if (!is_dir(BASE_PATH . '/config')) {
        @mkdir(BASE_PATH . '/config', 0755, true);
    }
    if (file_put_contents(CONFIG_FILE, $config) === false) {
        return ['Could not write config/config.php. Check folder permissions (755).'];
    }

    // 6) Set the Telegram webhook (best effort).
    if (!empty($i['bot_token']) && !empty($i['webhook_url'])) {
        $secret = $defaults['settings']['webhook_secret'];
        $url = "https://api.telegram.org/bot{$i['bot_token']}/setWebhook";
        $payload = http_build_query([
            'url' => $i['webhook_url'],
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 15]);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    // 7) Create lock + storage dirs.
    @mkdir(BASE_PATH . '/storage/logs', 0755, true);
    @mkdir(BASE_PATH . '/assets/uploads', 0755, true);
    @file_put_contents(LOCK_FILE, date('c'));

    return [];
}

// ---------------------------------------------------------------------------
// Handle POST per step
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$alreadyInstalled || $forceReinstall)) {
    $current = (int) ($_POST['step'] ?? 1);

    if ($current === 1) {
        $db = [
            'host' => post('host', 'localhost'),
            'port' => post('port', '3306'),
            'name' => post('name'),
            'user' => post('user'),
            'pass' => $_POST['pass'] ?? '',
            'base_url' => post('base_url', baseUrlGuess()),
            'site_name' => post('site_name', 'MTASK'),
        ];
        [$ok, $res] = testDb($db);
        if (!$ok) {
            $errors[] = 'Database connection failed: ' . $res;
        } else {
            saveStep($db);
            header('Location: install.php?step=2');
            exit;
        }
    } elseif ($current === 2) {
        $admin = [
            'admin_user'  => post('admin_user'),
            'admin_email' => post('admin_email'),
            'admin_pass'  => $_POST['admin_pass'] ?? '',
        ];
        if (strlen($admin['admin_user']) < 3) {
            $errors[] = 'Admin username must be at least 3 characters.';
        }
        if (strlen($admin['admin_pass']) < 6) {
            $errors[] = 'Admin password must be at least 6 characters.';
        }
        if (($_POST['admin_pass'] ?? '') !== ($_POST['admin_pass2'] ?? '')) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$errors) {
            saveStep($admin);
            header('Location: install.php?step=3');
            exit;
        }
    } elseif ($current === 3) {
        saveStep([
            'bot_token'    => post('bot_token'),
            'bot_username' => ltrim(post('bot_username'), '@'),
        ]);
        header('Location: install.php?step=4');
        exit;
    } elseif ($current === 4) {
        $base = $_SESSION['install']['base_url'] ?? baseUrlGuess();
        saveStep(['webhook_url' => post('webhook_url', $base . '/bot/webhook.php')]);
        header('Location: install.php?step=5');
        exit;
    } elseif ($current === 5) {
        saveStep(['monetag_zone' => post('monetag_zone', '9660124')]);
        header('Location: install.php?step=6');
        exit;
    } elseif ($current === 6) {
        $errors = runInstall($_SESSION['install'] ?? []);
        if (!$errors) {
            unset($_SESSION['install']);
            header('Location: install.php?step=7');
            exit;
        }
    }
}

$steps = [
    1 => 'Database',
    2 => 'Administrator',
    3 => 'Telegram Bot',
    4 => 'Webhook',
    5 => 'Monetag',
    6 => 'Finish',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MTASK Installer</title>
<style>
    :root { --purple:#7c3aed; --blue:#2563eb; --green:#16a34a; --bg:#f5f4fb; }
    * { box-sizing: border-box; }
    body { margin:0; font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:#1f2937; }
    .wrap { max-width:680px; margin:40px auto; padding:0 16px; }
    .logo { text-align:center; margin-bottom:24px; }
    .logo h1 { background:linear-gradient(135deg,var(--purple),var(--blue)); -webkit-background-clip:text; background-clip:text; color:transparent; font-size:42px; margin:0; letter-spacing:2px; }
    .logo p { color:#6b7280; margin:4px 0 0; }
    .card { background:#fff; border-radius:22px; box-shadow:0 12px 40px rgba(124,58,237,.12); padding:32px; }
    .steps { display:flex; gap:6px; margin-bottom:28px; flex-wrap:wrap; }
    .steps .pill { flex:1; min-width:80px; text-align:center; padding:8px 4px; border-radius:12px; font-size:12px; background:#eef2ff; color:#6b7280; }
    .steps .pill.active { background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; }
    .steps .pill.done { background:var(--green); color:#fff; }
    label { display:block; font-weight:600; margin:14px 0 6px; font-size:14px; }
    input { width:100%; padding:12px 14px; border:1px solid #e5e7eb; border-radius:12px; font-size:15px; }
    input:focus { outline:none; border-color:var(--purple); box-shadow:0 0 0 3px rgba(124,58,237,.15); }
    .hint { font-size:12px; color:#9ca3af; margin-top:4px; }
    .btn { display:inline-block; margin-top:24px; background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; border:none; padding:14px 28px; border-radius:14px; font-size:16px; font-weight:600; cursor:pointer; width:100%; }
    .btn:hover { opacity:.92; }
    .alert { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 16px; border-radius:12px; margin-bottom:16px; font-size:14px; }
    .alert.ok { background:#f0fdf4; border-color:#bbf7d0; color:#15803d; }
    .success { text-align:center; }
    .success .check { width:80px; height:80px; border-radius:50%; background:var(--green); color:#fff; font-size:44px; line-height:80px; margin:0 auto 16px; }
    .row { display:flex; gap:12px; }
    .row > div { flex:1; }
    code { background:#f3f4f6; padding:2px 6px; border-radius:6px; font-size:13px; }
    .links a { color:var(--purple); }
</style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <h1>MTASK</h1>
        <p>Installation Wizard</p>
    </div>
    <div class="card">

    <?php if ($alreadyInstalled && !$forceReinstall): ?>
        <div class="alert ok">MTASK is already installed.</div>
        <p>For security, delete <code>install.php</code> from your server.</p>
        <p class="links">
            Go to the <a href="index.php">Landing Page</a> &middot;
            <a href="app.php">Mini App</a> &middot;
            <a href="admin/index.php">Admin Panel</a>
        </p>
        <p class="hint">To reinstall, visit <code>install.php?force=1</code> (this will overwrite your configuration).</p>

    <?php elseif ($step === 7): ?>
        <div class="success">
            <div class="check">&#10003;</div>
            <h2>Installation Complete!</h2>
            <p>MTASK has been installed successfully.</p>
        </div>
        <div class="alert">For security, <strong>delete install.php</strong> from your server now.</div>
        <p class="links">
            &rarr; <a href="admin/index.php">Open Admin Panel</a><br>
            &rarr; <a href="index.php">View Landing Page</a><br>
            &rarr; <a href="app.php">Open Mini App</a>
        </p>

    <?php else: ?>
        <div class="steps">
            <?php foreach ($steps as $n => $label): ?>
                <div class="pill <?= $n === $step ? 'active' : ($n < $step ? 'done' : '') ?>"><?= htmlspecialchars($label) ?></div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($errors as $e): ?>
            <div class="alert"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <form method="post" action="install.php?step=<?= $step ?>">
            <input type="hidden" name="step" value="<?= $step ?>">

            <?php if ($step === 1): ?>
                <h3>Step 1 &middot; Database Configuration</h3>
                <div class="row">
                    <div>
                        <label>Database Host</label>
                        <input name="host" value="<?= htmlspecialchars(post('host', 'localhost')) ?>" required>
                    </div>
                    <div>
                        <label>Port</label>
                        <input name="port" value="<?= htmlspecialchars(post('port', '3306')) ?>" required>
                    </div>
                </div>
                <label>Database Name</label>
                <input name="name" value="<?= htmlspecialchars(post('name')) ?>" required>
                <label>Database User</label>
                <input name="user" value="<?= htmlspecialchars(post('user')) ?>" required>
                <label>Database Password</label>
                <input type="password" name="pass" value="">
                <label>Site Name</label>
                <input name="site_name" value="<?= htmlspecialchars(post('site_name', 'MTASK')) ?>">
                <label>Base URL</label>
                <input name="base_url" value="<?= htmlspecialchars(post('base_url', baseUrlGuess())) ?>">
                <div class="hint">The public URL where MTASK is installed (no trailing slash).</div>
                <button class="btn" type="submit">Test &amp; Continue &rarr;</button>

            <?php elseif ($step === 2): ?>
                <h3>Step 2 &middot; Administrator Account</h3>
                <label>Admin Username</label>
                <input name="admin_user" value="<?= htmlspecialchars(post('admin_user')) ?>" required>
                <label>Admin Email</label>
                <input type="email" name="admin_email" value="<?= htmlspecialchars(post('admin_email')) ?>">
                <label>Password</label>
                <input type="password" name="admin_pass" required>
                <label>Confirm Password</label>
                <input type="password" name="admin_pass2" required>
                <button class="btn" type="submit">Continue &rarr;</button>

            <?php elseif ($step === 3): ?>
                <h3>Step 3 &middot; Telegram Bot</h3>
                <label>Bot Token</label>
                <input name="bot_token" value="<?= htmlspecialchars(post('bot_token')) ?>" placeholder="123456:ABC-DEF...">
                <div class="hint">Get this from <code>@BotFather</code> on Telegram.</div>
                <label>Bot Username (without @)</label>
                <input name="bot_username" value="<?= htmlspecialchars(post('bot_username')) ?>" placeholder="my_earning_bot">
                <button class="btn" type="submit">Continue &rarr;</button>

            <?php elseif ($step === 4): ?>
                <h3>Step 4 &middot; Webhook URL</h3>
                <label>Webhook URL</label>
                <input name="webhook_url" value="<?= htmlspecialchars(post('webhook_url', ($_SESSION['install']['base_url'] ?? baseUrlGuess()) . '/bot/webhook.php')) ?>">
                <div class="hint">This is registered with Telegram automatically. Must be HTTPS.</div>
                <button class="btn" type="submit">Continue &rarr;</button>

            <?php elseif ($step === 5): ?>
                <h3>Step 5 &middot; Monetag Zone ID</h3>
                <label>Monetag Rewarded Zone ID</label>
                <input name="monetag_zone" value="<?= htmlspecialchars(post('monetag_zone', '9660124')) ?>">
                <div class="hint">Used for the rewarded video popup: <code>show_&lt;zone&gt;('pop')</code>.</div>
                <button class="btn" type="submit">Continue &rarr;</button>

            <?php elseif ($step === 6): ?>
                <h3>Step 6 &middot; Finish Installation</h3>
                <p>Review and complete the installation. This will create your database tables, seed defaults, create your admin account, write the config file and register the Telegram webhook.</p>
                <ul>
                    <li>Database: <code><?= htmlspecialchars($_SESSION['install']['name'] ?? '') ?></code></li>
                    <li>Admin: <code><?= htmlspecialchars($_SESSION['install']['admin_user'] ?? '') ?></code></li>
                    <li>Bot configured: <code><?= !empty($_SESSION['install']['bot_token']) ? 'yes' : 'no' ?></code></li>
                </ul>
                <button class="btn" type="submit">Install MTASK &rarr;</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    </div>
</div>
</body>
</html>
