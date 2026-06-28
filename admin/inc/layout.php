<?php
/**
 * MTASK Admin - Layout helpers
 * --------------------------------------------------------------------------
 * Shared rendering helpers for the admin panel: page header (with sidebar)
 * and footer, flash messages and small formatting utilities.
 *
 * @package MTASK\Admin
 */

declare(strict_types=1);

/** Set a one-time flash message. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Render and clear queued flash messages. */
function renderFlash(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $cls = ['success' => 'success', 'error' => 'danger', 'warn' => 'warning', 'info' => 'info'][$f['type']] ?? 'secondary';
        $out .= '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
            . Security::e($f['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/** Format an MT amount. */
function mt(int|float|string $n): string
{
    return number_format((float) $n) . ' MT';
}

/** Active class helper for sidebar links. */
function navActive(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

/**
 * Render the admin layout header (opening HTML + sidebar + topbar).
 *
 * @param string $title   Page title.
 * @param string $current Current page slug for nav highlighting.
 * @param array  $admin   The authenticated admin row.
 */
function adminHeader(string $title, string $current, array $admin): void
{
    $siteName = Settings::get('site_name', 'MTASK');
    $nav = [
        'dashboard'    => ['Dashboard', 'bi-speedometer2'],
        'users'        => ['Users', 'bi-people'],
        'tasks'        => ['Tasks', 'bi-list-check'],
        'ads'          => ['Rewarded Ads', 'bi-play-btn'],
        'bonus'        => ['Daily Bonus', 'bi-gift'],
        'referrals'    => ['Referrals', 'bi-share'],
        'withdrawals'  => ['Withdrawals', 'bi-cash-coin'],
        'payments'     => ['Payment Methods', 'bi-credit-card'],
        'transactions' => ['Transactions', 'bi-receipt'],
        'reports'      => ['Reports', 'bi-graph-up'],
        'settings'     => ['Settings', 'bi-gear'],
        'logs'         => ['Admin Logs', 'bi-shield-lock'],
        'support'      => ['Support', 'bi-headset'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Security::e($title) ?> · <?= Security::e($siteName) ?> Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
    :root{ --purple:#7c3aed; --blue:#2563eb; }
    body{ background:#f3f4fb; font-family:'Inter','Segoe UI',sans-serif; }
    .sidebar{ position:fixed; top:0; left:0; bottom:0; width:248px; background:#15131f; color:#cbd5e1; padding:18px 12px; overflow-y:auto; }
    .sidebar .brand{ color:#fff; font-weight:800; font-size:20px; padding:8px 12px 18px; display:flex; align-items:center; gap:10px; }
    .sidebar .brand .mark{ width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,var(--purple),var(--blue)); display:grid; place-items:center; }
    .sidebar a{ display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:11px; color:#cbd5e1; text-decoration:none; font-size:14px; font-weight:500; margin-bottom:2px; }
    .sidebar a:hover{ background:rgba(255,255,255,.06); color:#fff; }
    .sidebar a.active{ background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; }
    .sidebar a i{ font-size:18px; }
    .content{ margin-left:248px; padding:24px 28px; }
    .topbar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
    .topbar h1{ font-size:24px; font-weight:800; margin:0; }
    .card{ border:none; border-radius:18px; box-shadow:0 8px 24px rgba(31,41,55,.06); }
    .stat-box{ border-radius:18px; padding:20px; color:#fff; }
    .stat-box .n{ font-size:26px; font-weight:800; } .stat-box .l{ opacity:.9; font-size:13px; }
    .g-purple{ background:linear-gradient(135deg,#7c3aed,#a855f7);} .g-blue{background:linear-gradient(135deg,#2563eb,#38bdf8);} .g-green{background:linear-gradient(135deg,#16a34a,#4ade80);} .g-orange{background:linear-gradient(135deg,#f59e0b,#fb923c);} .g-red{background:linear-gradient(135deg,#ef4444,#f87171);} .g-dark{background:linear-gradient(135deg,#334155,#64748b);}
    table td,table th{ vertical-align:middle; }
    .badge-st{ padding:5px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .st-pending{ background:#fef3c7; color:#b45309;} .st-approved{ background:#dbeafe; color:#1d4ed8;} .st-paid,.st-completed,.st-active{ background:#dcfce7; color:#15803d;} .st-rejected,.st-banned,.st-disabled{ background:#fee2e2; color:#b91c1c;}
    .btn-purple{ background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; }
    .btn-purple:hover{ color:#fff; opacity:.92; }
    @media(max-width:900px){ .sidebar{ transform:translateX(-100%); transition:.3s; z-index:1000; } .sidebar.show{ transform:none; } .content{ margin-left:0; } }
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="brand"><span class="mark">⚡</span> <?= Security::e($siteName) ?></div>
    <?php foreach ($nav as $slug => $item): ?>
        <a class="<?= navActive($slug, $current) ?>" href="index.php?page=<?= $slug ?>">
            <i class="bi <?= $item[1] ?>"></i> <?= Security::e($item[0]) ?>
        </a>
    <?php endforeach; ?>
    <a href="index.php?page=logout" style="color:#fca5a5"><i class="bi bi-box-arrow-right"></i> Logout</a>
</aside>
<div class="content">
    <div class="topbar">
        <h1><?= Security::e($title) ?></h1>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted d-none d-md-inline"><i class="bi bi-person-circle"></i> <?= Security::e($admin['username']) ?> <small class="badge bg-secondary"><?= Security::e($admin['role']) ?></small></span>
            <button class="btn btn-sm btn-light d-md-none" onclick="document.getElementById('sidebar').classList.toggle('show')"><i class="bi bi-list"></i></button>
        </div>
    </div>
    <?= renderFlash() ?>
    <?php
}

/** Render the admin layout footer (closing HTML). */
function adminFooter(): void
{
    ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}
