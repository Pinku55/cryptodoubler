<?php
/**
 * Logger
 * --------------------------------------------------------------------------
 * Lightweight file + database logging. Application events go to rotating
 * log files in /storage/logs; security-relevant admin actions are also
 * mirrored into the `admin_logs` table for the audit trail.
 *
 * @package MTASK
 */

declare(strict_types=1);

final class Logger
{
    private function __construct() {}

    /**
     * Write a line to a named log channel file.
     *
     * @param string $channel e.g. "app", "security", "bot"
     * @param string $level   INFO | WARN | ERROR
     * @param string $message Message text.
     * @param array  $context Extra context appended as JSON.
     */
    public static function log(string $channel, string $level, string $message, array $context = []): void
    {
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0755, true);
        }
        $line = sprintf(
            "[%s] [%s] %s %s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : '',
            PHP_EOL
        );
        @file_put_contents(LOGS_PATH . '/' . preg_replace('/[^a-z0-9_-]/i', '', $channel) . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** Convenience: info-level app log. */
    public static function info(string $message, array $context = []): void
    {
        self::log('app', 'INFO', $message, $context);
    }

    /** Convenience: error-level app log. */
    public static function error(string $message, array $context = []): void
    {
        self::log('app', 'ERROR', $message, $context);
    }

    /** Convenience: security channel log. */
    public static function security(string $message, array $context = []): void
    {
        self::log('security', 'WARN', $message, $context);
    }

    /**
     * Record an admin action in the audit trail (DB) and the security log.
     *
     * @param int|null $adminId Admin user id performing the action.
     * @param string   $action  Short action key, e.g. "user.ban".
     * @param string   $details Human readable details.
     */
    public static function audit(?int $adminId, string $action, string $details = ''): void
    {
        try {
            Database::insert('admin_logs', [
                'admin_id'   => $adminId,
                'action'     => $action,
                'details'    => $details,
                'ip_address' => Security::clientIp(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            self::error('Audit log failed: ' . $e->getMessage());
        }
        self::security($action, ['admin' => $adminId, 'details' => $details]);
    }
}
