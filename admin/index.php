<?php
/**
 * MTASK Admin - Front controller
 * --------------------------------------------------------------------------
 * Handles admin authentication and routes ?page=<slug> to the matching
 * file in admin/pages. All state-changing POSTs are CSRF-protected.
 *
 * @package MTASK\Admin
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$page = preg_replace('/[^a-z_]/', '', (string) ($_GET['page'] ?? 'dashboard'));

// ---------------------------------------------------------------------------
// Logout
// ---------------------------------------------------------------------------
if ($page === 'logout') {
    Auth::adminLogout();
    header('Location: index.php?page=login');
    exit;
}

// ---------------------------------------------------------------------------
// Login (no auth required)
// ---------------------------------------------------------------------------
if ($page === 'login') {
    if (Auth::admin() !== null) {
        header('Location: index.php?page=dashboard');
        exit;
    }
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Security::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $error = 'Invalid session token. Please try again.';
        } else {
            $admin = Auth::adminLogin(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
            if ($admin) {
                header('Location: index.php?page=dashboard');
                exit;
            }
            $error = 'Invalid credentials or too many attempts.';
        }
    }
    require __DIR__ . '/pages/login.php';
    exit;
}

// ---------------------------------------------------------------------------
// Everything below requires an authenticated admin.
// ---------------------------------------------------------------------------
$admin = Auth::requireAdmin();

// CSRF gate for all POST mutations.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Security::verifyCsrf($_POST['csrf_token'] ?? null)) {
    flash('error', 'Security token mismatch. Action cancelled.');
    header('Location: index.php?page=' . $page);
    exit;
}

// Route to the page handler.
$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!is_file($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
}

require $pageFile;
