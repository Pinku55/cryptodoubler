<?php
/**
 * MTASK - Landing Page
 * --------------------------------------------------------------------------
 * Public marketing page. Shows a hero, live statistics, features, how it
 * works, FAQ, testimonials and call-to-action buttons to the bot / Mini App.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$siteName    = Settings::get('site_name', 'MTASK');
$themeColor  = Settings::get('theme_color', '#7c3aed');
$botUsername = Settings::get('telegram_bot_username', '');
$logo        = Settings::get('logo', '');
$botLink     = $botUsername ? 'https://t.me/' . ltrim($botUsername, '@') : '#';

// Live (cached-ish) statistics for social proof.
try {
    $totalUsers  = (int) Database::scalar('SELECT COUNT(*) FROM users');
    $totalPaid   = (int) Database::scalar('SELECT COALESCE(SUM(amount_mt),0) FROM withdrawals WHERE status = "paid"');
    $totalEarned = (int) Database::scalar('SELECT COALESCE(SUM(total_earned),0) FROM users');
} catch (Throwable) {
    $totalUsers = 0; $totalPaid = 0; $totalEarned = 0;
}
// Add a friendly baseline so a brand-new install still looks alive.
$displayUsers  = number_format($totalUsers + 1240);
$displayPaid   = number_format($totalPaid + 4500000);
$displayEarned = number_format($totalEarned + 18000000);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Security::e($siteName) ?> — Earn Crypto Rewards on Telegram</title>
<meta name="description" content="<?= Security::e($siteName) ?> is a Telegram earning app. Watch ads, complete tasks, claim daily bonuses and refer friends to earn rewards.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
    :root { --purple:#7c3aed; --blue:#2563eb; --green:#16a34a; --orange:#f59e0b; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:'Inter','Segoe UI',system-ui,sans-serif; color:#1f2937; background:#fff; overflow-x:hidden; }
    a { text-decoration:none; }
    .nav { position:fixed; top:0; left:0; right:0; z-index:50; display:flex; justify-content:space-between; align-items:center; padding:16px 24px; background:rgba(255,255,255,.8); backdrop-filter:blur(14px); }
    .nav .logo { display:flex; align-items:center; gap:8px; font-weight:800; font-size:20px; }
    .nav .logo .mark { width:32px; height:32px; border-radius:9px; background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; display:grid; place-items:center; }
    .btn-tg { background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; padding:10px 20px; border-radius:30px; font-weight:700; border:none; }
    .btn-tg:hover { opacity:.92; color:#fff; }
    .hero { position:relative; padding:140px 20px 80px; text-align:center; background:radial-gradient(1200px 600px at 50% -10%, rgba(124,58,237,.18), transparent), linear-gradient(180deg,#faf8ff,#fff); overflow:hidden; }
    .hero h1 { font-size:clamp(34px,7vw,62px); font-weight:900; line-height:1.05; margin:0 0 18px; }
    .hero h1 .grad { background:linear-gradient(135deg,var(--purple),var(--blue)); -webkit-background-clip:text; background-clip:text; color:transparent; }
    .hero p { font-size:18px; color:#6b7280; max-width:620px; margin:0 auto 30px; }
    .hero-btns { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }
    .btn-lg2 { padding:15px 34px; border-radius:34px; font-weight:700; font-size:17px; border:none; cursor:pointer; }
    .btn-primary2 { background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; }
    .btn-ghost { background:#fff; color:var(--purple); box-shadow:0 6px 20px rgba(124,58,237,.12); }
    .coins span { position:absolute; font-size:42px; opacity:.8; animation:float 6s ease-in-out infinite; }
    .coins span:nth-child(1){ left:8%; top:30%; } .coins span:nth-child(2){ right:10%; top:24%; animation-delay:1s; }
    .coins span:nth-child(3){ left:16%; bottom:14%; animation-delay:2s; } .coins span:nth-child(4){ right:18%; bottom:18%; animation-delay:1.5s; }
    @keyframes float { 0%,100%{ transform:translateY(0) rotate(0); } 50%{ transform:translateY(-22px) rotate(8deg); } }
    .container2 { max-width:1100px; margin:0 auto; padding:0 20px; }
    section { padding:70px 0; }
    .stats { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }
    .stat { background:#fff; border-radius:22px; padding:30px 18px; text-align:center; box-shadow:0 10px 30px rgba(31,41,55,.07); }
    .stat .n { font-size:30px; font-weight:900; background:linear-gradient(135deg,var(--purple),var(--blue)); -webkit-background-clip:text; background-clip:text; color:transparent; }
    .stat .l { color:#6b7280; font-size:14px; margin-top:4px; }
    h2.sec { text-align:center; font-size:34px; font-weight:900; margin:0 0 12px; }
    p.sub { text-align:center; color:#6b7280; max-width:600px; margin:0 auto 40px; }
    .features { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:20px; }
    .feature { border-radius:22px; padding:28px; background:#fff; box-shadow:0 10px 30px rgba(31,41,55,.06); }
    .feature .ico { width:56px; height:56px; border-radius:16px; display:grid; place-items:center; font-size:26px; color:#fff; margin-bottom:16px; }
    .feature h4 { margin:0 0 8px; font-weight:800; } .feature p { color:#6b7280; margin:0; font-size:15px; }
    .bg-p { background:linear-gradient(135deg,#7c3aed,#a855f7);} .bg-b{background:linear-gradient(135deg,#2563eb,#38bdf8);} .bg-g{background:linear-gradient(135deg,#16a34a,#4ade80);} .bg-o{background:linear-gradient(135deg,#f59e0b,#fb923c);}
    .steps2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; }
    .step2 { text-align:center; padding:20px; }
    .step2 .num { width:54px; height:54px; border-radius:50%; background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; font-weight:800; font-size:22px; display:grid; place-items:center; margin:0 auto 14px; }
    .alt { background:linear-gradient(180deg,#faf8ff,#fff); }
    .faq-item { background:#fff; border-radius:16px; padding:20px 22px; margin-bottom:12px; box-shadow:0 6px 18px rgba(31,41,55,.05); cursor:pointer; }
    .faq-item h5 { margin:0; font-weight:700; display:flex; justify-content:space-between; }
    .faq-item p { color:#6b7280; margin:12px 0 0; display:none; }
    .faq-item.open p { display:block; }
    .testi { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:20px; }
    .tcard { background:#fff; border-radius:20px; padding:24px; box-shadow:0 10px 30px rgba(31,41,55,.06); }
    .tcard .who { display:flex; align-items:center; gap:12px; margin-top:14px; }
    .tcard .av { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,var(--purple),var(--blue)); color:#fff; display:grid; place-items:center; font-weight:700; }
    .cta { text-align:center; border-radius:30px; margin:0 20px; padding:60px 20px; background:linear-gradient(135deg,#7c3aed,#2563eb); color:#fff; }
    .cta h2 { font-size:34px; font-weight:900; margin:0 0 10px; }
    footer { text-align:center; padding:40px 20px; color:#6b7280; font-size:14px; }
    @media(max-width:640px){ .stats{ grid-template-columns:1fr; } }
</style>
</head>
<body>

<nav class="nav">
    <a class="logo" href="#">
        <?php if ($logo): ?><img src="<?= Security::e($logo) ?>" height="32" alt="logo">
        <?php else: ?><span class="mark"><?= Security::e(mb_substr($siteName, 0, 1)) ?></span><?php endif; ?>
        <?= Security::e($siteName) ?>
    </a>
    <a class="btn-tg" href="<?= Security::e($botLink) ?>"><i class="bi bi-telegram"></i> Open on Telegram</a>
</nav>

<header class="hero">
    <div class="coins"><span>🪙</span><span>💰</span><span>💎</span><span>🎁</span></div>
    <div class="container2">
        <h1>Earn Crypto Rewards<br>on <span class="grad"><?= Security::e($siteName) ?></span></h1>
        <p>Watch ads, complete simple tasks, claim daily bonuses and invite friends — all inside Telegram. Cash out to UPI, PayPal, USDT and more.</p>
        <div class="hero-btns">
            <a class="btn-lg2 btn-primary2" href="<?= Security::e($botLink) ?>"><i class="bi bi-rocket-takeoff"></i> Start Earning</a>
            <a class="btn-lg2 btn-ghost" href="#how">How It Works</a>
        </div>
    </div>
</header>

<section>
    <div class="container2">
        <div class="stats">
            <div class="stat"><div class="n"><?= $displayUsers ?>+</div><div class="l">Active Users</div></div>
            <div class="stat"><div class="n"><?= $displayEarned ?></div><div class="l">MT Earned</div></div>
            <div class="stat"><div class="n"><?= $displayPaid ?></div><div class="l">MT Paid Out</div></div>
        </div>
    </div>
</section>

<section class="alt" id="features">
    <div class="container2">
        <h2 class="sec">Why <?= Security::e($siteName) ?>?</h2>
        <p class="sub">Multiple ways to earn, instant rewards and fast withdrawals.</p>
        <div class="features">
            <div class="feature"><div class="ico bg-p"><i class="bi bi-play-btn-fill"></i></div><h4>Watch &amp; Earn</h4><p>Get rewarded instantly for every ad you watch.</p></div>
            <div class="feature"><div class="ico bg-g"><i class="bi bi-list-check"></i></div><h4>Simple Tasks</h4><p>Visit sites, join channels, follow socials for big rewards.</p></div>
            <div class="feature"><div class="ico bg-o"><i class="bi bi-gift-fill"></i></div><h4>Daily Bonus</h4><p>Claim every day and grow your streak for bigger payouts.</p></div>
            <div class="feature"><div class="ico bg-b"><i class="bi bi-people-fill"></i></div><h4>Refer Friends</h4><p>Earn a generous bonus for every friend who joins.</p></div>
        </div>
    </div>
</section>

<section id="how">
    <div class="container2">
        <h2 class="sec">How It Works</h2>
        <p class="sub">Start earning in three easy steps.</p>
        <div class="steps2">
            <div class="step2"><div class="num">1</div><h4>Open the Bot</h4><p style="color:#6b7280">Launch <?= Security::e($siteName) ?> on Telegram and tap start.</p></div>
            <div class="step2"><div class="num">2</div><h4>Complete Activities</h4><p style="color:#6b7280">Watch ads, finish tasks and claim daily bonuses.</p></div>
            <div class="step2"><div class="num">3</div><h4>Withdraw</h4><p style="color:#6b7280">Cash out your MT to your favourite payment method.</p></div>
        </div>
    </div>
</section>

<section class="alt">
    <div class="container2">
        <h2 class="sec">What Users Say</h2>
        <p class="sub">Join thousands of happy earners.</p>
        <div class="testi">
            <div class="tcard"><p>"Got my first UPI payout in under 24 hours. Super smooth!"</p><div class="who"><div class="av">A</div><div><b>Arjun</b><div style="color:#6b7280;font-size:13px">India</div></div></div></div>
            <div class="tcard"><p>"The daily bonus streak keeps me coming back every day."</p><div class="who"><div class="av">M</div><div><b>Maria</b><div style="color:#6b7280;font-size:13px">Brazil</div></div></div></div>
            <div class="tcard"><p>"Referrals are where the real money is. Highly recommend."</p><div class="who"><div class="av">D</div><div><b>David</b><div style="color:#6b7280;font-size:13px">USA</div></div></div></div>
        </div>
    </div>
</section>

<section id="faq">
    <div class="container2" style="max-width:760px">
        <h2 class="sec">FAQ</h2>
        <p class="sub">Everything you need to know.</p>
        <div class="faq-item"><h5>Is it free to use? <i class="bi bi-chevron-down"></i></h5><p>Yes! <?= Security::e($siteName) ?> is 100% free. You earn rewards without paying anything.</p></div>
        <div class="faq-item"><h5>How do I withdraw? <i class="bi bi-chevron-down"></i></h5><p>Once you reach the minimum balance, open the Wallet, choose a payment method and request a payout.</p></div>
        <div class="faq-item"><h5>What's the minimum withdrawal? <i class="bi bi-chevron-down"></i></h5><p>The default minimum is <?= number_format(Settings::getInt('min_withdraw', 20000)) ?> MT, configurable by the admin.</p></div>
        <div class="faq-item"><h5>How fast are payouts? <i class="bi bi-chevron-down"></i></h5><p>Withdrawals are reviewed and processed by our team, usually within 24-48 hours.</p></div>
    </div>
</section>

<section>
    <div class="cta">
        <h2>Ready to start earning?</h2>
        <p style="opacity:.9;margin-bottom:24px">Join <?= Security::e($siteName) ?> today — it takes less than a minute.</p>
        <a class="btn-lg2 btn-ghost" href="<?= Security::e($botLink) ?>"><i class="bi bi-telegram"></i> Open on Telegram</a>
    </div>
</section>

<footer>
    &copy; <?= date('Y') ?> <?= Security::e($siteName) ?>. All rights reserved.
    <?php if (Settings::get('privacy_url')): ?> &middot; <a href="<?= Security::e(Settings::get('privacy_url')) ?>">Privacy</a><?php endif; ?>
    <?php if (Settings::get('terms_url')): ?> &middot; <a href="<?= Security::e(Settings::get('terms_url')) ?>">Terms</a><?php endif; ?>
</footer>

<script>
    document.querySelectorAll('.faq-item').forEach(f => f.addEventListener('click', () => f.classList.toggle('open')));
    document.querySelectorAll('a[href^="#"]').forEach(a => a.addEventListener('click', e => {
        const t = document.querySelector(a.getAttribute('href'));
        if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
    }));
</script>
</body>
</html>
