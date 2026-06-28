<?php
/**
 * Admin login page.
 * @package MTASK\Admin
 * @var string $error
 */
declare(strict_types=1);
$siteName = Settings::get('site_name', 'MTASK');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login · <?= Security::e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body{ background:linear-gradient(135deg,#7c3aed,#2563eb); min-height:100vh; display:grid; place-items:center; font-family:'Inter','Segoe UI',sans-serif; margin:0; }
    .login-card{ background:#fff; border-radius:24px; padding:40px; width:380px; max-width:92vw; box-shadow:0 20px 60px rgba(0,0,0,.25); }
    .logo{ text-align:center; margin-bottom:24px; }
    .logo .mark{ width:60px; height:60px; border-radius:18px; background:linear-gradient(135deg,#7c3aed,#2563eb); color:#fff; font-size:30px; display:grid; place-items:center; margin:0 auto 12px; }
    .form-control{ padding:13px; border-radius:13px; }
    .btn-login{ background:linear-gradient(135deg,#7c3aed,#2563eb); color:#fff; padding:13px; border-radius:13px; font-weight:700; width:100%; border:none; }
</style>
</head>
<body>
<div class="login-card">
    <div class="logo">
        <div class="mark">⚡</div>
        <h4 class="fw-bold mb-0"><?= Security::e($siteName) ?> Admin</h4>
        <small class="text-muted">Sign in to your dashboard</small>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= Security::e($error) ?></div><?php endif; ?>
    <form method="post" action="index.php?page=login">
        <?= Security::csrfField() ?>
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" required autofocus>
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn-login" type="submit">Sign In</button>
    </form>
</div>
</body>
</html>
