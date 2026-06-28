<?php
/**
 * Security
 * --------------------------------------------------------------------------
 * Cross-cutting security helpers: CSRF tokens, output escaping (XSS),
 * a database-backed rate limiter, client IP resolution, and password
 * hashing for admin accounts.
 *
 * @package MTASK
 */

declare(strict_types=1);

final class Security
{
    private function __construct() {}

    // -----------------------------------------------------------------
    // CSRF protection
    // -----------------------------------------------------------------

    /** Get (creating if needed) the current session CSRF token. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Validate a submitted CSRF token using a constant-time comparison. */
    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Render a hidden CSRF input field for HTML forms. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::e(self::csrfToken()) . '">';
    }

    // -----------------------------------------------------------------
    // XSS / output escaping
    // -----------------------------------------------------------------

    /** HTML-escape a value for safe output. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Recursively sanitize an input array (trim + strip nulls). */
    public static function clean(array $input): array
    {
        $out = [];
        foreach ($input as $k => $v) {
            $out[$k] = is_array($v) ? self::clean($v) : trim((string) $v);
        }
        return $out;
    }

    // -----------------------------------------------------------------
    // Rate limiting (DB backed, works on shared hosting without Redis)
    // -----------------------------------------------------------------

    /**
     * Returns true if the action is allowed, false if the limit is exceeded.
     *
     * @param string $key        Unique bucket key (e.g. "ad:<userId>").
     * @param int    $maxHits    Max allowed hits in the window.
     * @param int    $windowSecs Window size in seconds.
     */
    public static function rateLimit(string $key, int $maxHits, int $windowSecs): bool
    {
        $now = time();
        $row = Database::fetch('SELECT hits, reset_at FROM rate_limits WHERE bucket = ?', [$key]);

        if ($row === null) {
            Database::insert('rate_limits', [
                'bucket'   => $key,
                'hits'     => 1,
                'reset_at' => $now + $windowSecs,
            ]);
            return true;
        }

        if ((int) $row['reset_at'] <= $now) {
            Database::update('rate_limits', ['hits' => 1, 'reset_at' => $now + $windowSecs], 'bucket = :b', ['b' => $key]);
            return true;
        }

        if ((int) $row['hits'] >= $maxHits) {
            return false;
        }

        Database::update('rate_limits', ['hits' => (int) $row['hits'] + 1], 'bucket = :b', ['b' => $key]);
        return true;
    }

    // -----------------------------------------------------------------
    // Client / request metadata
    // -----------------------------------------------------------------

    /** Resolve the best-guess client IP, honouring common proxy headers. */
    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /** Return the raw User-Agent string (trimmed to a sane length). */
    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    // -----------------------------------------------------------------
    // Password hashing (admin accounts)
    // -----------------------------------------------------------------

    /** Hash a plaintext password with bcrypt. */
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** Verify a plaintext password against a stored hash. */
    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /** Generate a cryptographically secure random alphanumeric code. */
    public static function randomCode(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
