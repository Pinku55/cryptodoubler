<?php
/**
 * MTASK - Telegram Mini App shell
 * --------------------------------------------------------------------------
 * Single-page application loaded inside Telegram. Renders the app skeleton,
 * loads the Telegram WebApp SDK and the Monetag rewarded SDK, then hands off
 * to assets/js/app.js which talks to the /api endpoints.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$siteName    = Settings::get('site_name', 'MTASK');
$themeColor  = Settings::get('theme_color', '#7c3aed');
$monetagZone = Settings::get('monetag_zone_id', '11211905');
$maintenance = Settings::getBool('maintenance_mode', false);
$logo        = Settings::get('logo', '');
$ver         = '1.1.2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="<?= Security::e($themeColor) ?>">
<title><?= Security::e($siteName) ?></title>

<!-- Telegram WebApp SDK -->
<script src="https://telegram.org/js/telegram-web-app.js"></script>

<!-- Bootstrap 5 + Icons + Font Awesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/app.css?v=<?= $ver ?>" rel="stylesheet">

<!-- Monetag Rewarded SDK (provides show_<zone>()) -->
<script src="//libtl.com/sdk.js" data-zone="<?= Security::e($monetagZone) ?>" data-sdk="show_<?= Security::e($monetagZone) ?>"></script>
</head>
<body>

<!-- App boot config exposed to JS -->
<script>
    window.MTASK = {
        apiBase: 'api/',
        monetagZone: <?= json_encode((string) $monetagZone) ?>,
        version: <?= json_encode($ver) ?>,
        maintenance: <?= $maintenance ? 'true' : 'false' ?>
    };
</script>

<!-- Top Bar -->
<header class="topbar glass">
    <button class="icon-btn" id="menuBtn" aria-label="Menu"><i class="bi bi-list"></i></button>
    <div class="brand">
        <?php if ($logo): ?>
            <img src="<?= Security::e($logo) ?>" alt="logo" class="brand-logo">
        <?php else: ?>
            <span class="brand-mark"><?= Security::e(mb_substr($siteName, 0, 1)) ?></span>
        <?php endif; ?>
        <span class="brand-name"><?= Security::e($siteName) ?></span>
    </div>
    <button class="icon-btn" id="notifBtn" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <span class="notif-dot" id="notifDot" hidden></span>
    </button>
</header>

<!-- Side menu (hamburger) -->
<div class="side-menu" id="sideMenu">
    <div class="side-head">
        <div class="side-avatar" id="menuAvatar"></div>
        <div>
            <div class="side-name" id="menuName">—</div>
            <div class="side-id" id="menuId"></div>
        </div>
    </div>
    <nav class="side-links">
        <a data-go="home"><i class="bi bi-house"></i> Home</a>
        <a data-go="earn"><i class="bi bi-play-btn"></i> Earn</a>
        <a data-go="wallet"><i class="bi bi-wallet2"></i> Wallet</a>
        <a data-go="referrals"><i class="bi bi-people"></i> Referrals</a>
        <a data-go="profile"><i class="bi bi-person"></i> Profile</a>
        <a data-go="bonus"><i class="bi bi-gift"></i> Daily Bonus</a>
        <a data-go="tasks"><i class="bi bi-list-check"></i> Tasks</a>
        <a data-go="withdraw"><i class="bi bi-cash-coin"></i> Withdraw</a>
    </nav>
</div>
<div class="overlay" id="overlay"></div>

<!-- Main content (pages injected here) -->
<main class="app-main" id="app">
    <!-- Initial skeleton -->
    <div class="skeleton-wrap">
        <div class="skeleton skeleton-card"></div>
        <div class="skeleton skeleton-row"></div>
        <div class="skeleton skeleton-row"></div>
    </div>
</main>

<!-- Bottom Navigation -->
<nav class="bottom-nav glass">
    <button class="nav-item active" data-go="home"><i class="bi bi-house-door"></i><span>Home</span></button>
    <button class="nav-item" data-go="earn"><i class="bi bi-play-circle"></i><span>Earn</span></button>
    <button class="nav-item" data-go="wallet"><i class="bi bi-wallet2"></i><span>Wallet</span></button>
    <button class="nav-item" data-go="referrals"><i class="bi bi-people"></i><span>Refer</span></button>
    <button class="nav-item" data-go="profile"><i class="bi bi-person"></i><span>Profile</span></button>
</nav>

<!-- Toast container -->
<div class="toast-host" id="toastHost"></div>

<!-- Modal host -->
<div class="modal-host" id="modalHost" hidden>
    <div class="modal-card glass" id="modalCard"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=<?= $ver ?>"></script>
</body>
</html>
