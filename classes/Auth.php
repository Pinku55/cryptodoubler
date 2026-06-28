<?php
/**
 * Auth
 * --------------------------------------------------------------------------
 * Authentication for two audiences:
 *   1. Mini App users  - authenticated via verified Telegram WebApp initData.
 *   2. Admin panel      - authenticated via username/password sessions.
 *
 * @package MTASK
 */

declare(strict_types=1);

final class Auth
{
    private function __construct() {}

    // -----------------------------------------------------------------
    // Mini App user authentication
    // -----------------------------------------------------------------

    /**
     * Authenticate a Mini App request from verified initData and return the
     * user row. Establishes/refreshes the PHP session binding.
     *
     * @param string      $initData Raw Telegram WebApp initData.
     * @param string|null $refCode  Optional referral code (start_param).
     * @return array|null The user row, or null when verification fails.
     */
    public static function authenticateWebApp(string $initData, ?string $refCode = null): ?array
    {
        $verified = Telegram::verifyInitData($initData);
        if ($verified === null || empty($verified['user']['id'])) {
            return null;
        }

        // start_param can carry a referral code.
        if ($refCode === null && !empty($verified['start_param'])) {
            $refCode = (string) $verified['start_param'];
        }

        $user = User::findOrCreate($verified['user'], $refCode);

        if (($user['status'] ?? 'active') === 'banned') {
            return null; // banned users cannot authenticate
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_tg'] = (int) $user['telegram_id'];

        return $user;
    }

    /** Return the currently authenticated Mini App user, or null. */
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $user = User::findById((int) $_SESSION['user_id']);
        if ($user === null || ($user['status'] ?? '') === 'banned') {
            unset($_SESSION['user_id'], $_SESSION['user_tg']);
            return null;
        }
        return $user;
    }

    /**
     * Require an authenticated Mini App user. For API endpoints, callers may
     * re-authenticate using initData supplied on each request.
     *
     * @return array The authenticated user row.
     */
    public static function requireUser(): array
    {
        // Allow stateless re-auth via initData on each API call.
        $initData = $_POST['initData'] ?? $_SERVER['HTTP_X_TELEGRAM_INITDATA'] ?? '';
        if (is_string($initData) && $initData !== '') {
            $user = self::authenticateWebApp($initData, $_POST['ref'] ?? null);
            if ($user !== null) {
                return $user;
            }
        }

        $user = self::user();
        if ($user === null) {
            Response::error('Unauthorized', 401);
        }
        return $user;
    }

    // -----------------------------------------------------------------
    // Admin authentication
    // -----------------------------------------------------------------

    /**
     * Attempt an admin login.
     *
     * @return array|null The admin row on success, null on failure.
     */
    public static function adminLogin(string $username, string $password): ?array
    {
        // Throttle brute force attempts per IP + username.
        $bucket = 'adminlogin:' . Security::clientIp() . ':' . strtolower($username);
        if (!Security::rateLimit($bucket, 8, 600)) {
            Logger::security('Admin login rate limited', ['user' => $username]);
            return null;
        }

        $admin = Database::fetch('SELECT * FROM admins WHERE username = ? AND status = "active"', [$username]);
        if ($admin && Security::verifyPassword($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            Database::update('admins', ['last_login' => date('Y-m-d H:i:s'), 'last_ip' => Security::clientIp()], 'id = :id', ['id' => $admin['id']]);
            Logger::audit((int) $admin['id'], 'admin.login', 'Successful login');
            return $admin;
        }

        Logger::security('Failed admin login', ['user' => $username, 'ip' => Security::clientIp()]);
        return null;
    }

    /** Return the currently authenticated admin row, or null. */
    public static function admin(): ?array
    {
        if (empty($_SESSION['admin_id'])) {
            return null;
        }
        return Database::fetch('SELECT * FROM admins WHERE id = ? AND status = "active"', [(int) $_SESSION['admin_id']]);
    }

    /** Require an admin session or redirect to the login page. */
    public static function requireAdmin(): array
    {
        $admin = self::admin();
        if ($admin === null) {
            header('Location: index.php?page=login');
            exit;
        }
        return $admin;
    }

    /** Destroy the admin session. */
    public static function adminLogout(): void
    {
        if (!empty($_SESSION['admin_id'])) {
            Logger::audit((int) $_SESSION['admin_id'], 'admin.logout', 'Logged out');
        }
        unset($_SESSION['admin_id'], $_SESSION['admin_role']);
    }
}
