<?php
/**
 * Settings
 * --------------------------------------------------------------------------
 * Key/value application settings stored in the `settings` table and cached
 * in memory for the lifetime of the request. All admin-editable values
 * (rewards, limits, tokens, theme, etc.) live here.
 *
 * @package MTASK
 */

declare(strict_types=1);

final class Settings
{
    /** @var array<string,string> In-memory cache of all settings. */
    private static array $cache = [];

    /** @var bool Whether settings have been loaded from the DB. */
    private static bool $loaded = false;

    private function __construct() {}

    /** Eagerly load all settings into the cache. */
    public static function init(): void
    {
        if (self::$loaded) {
            return;
        }
        try {
            $rows = Database::fetchAll('SELECT `key`, `value` FROM settings');
            foreach ($rows as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
            self::$loaded = true;
        } catch (Throwable) {
            // Table may not exist yet (e.g. during install). Fail soft.
            self::$loaded = false;
        }
    }

    /**
     * Get a setting value with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::init();
        }
        return self::$cache[$key] ?? $default;
    }

    /** Get a setting cast to integer. */
    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::get($key, null);
        return $v === null ? $default : (int) $v;
    }

    /** Get a setting cast to float. */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        $v = self::get($key, null);
        return $v === null ? $default : (float) $v;
    }

    /** Get a boolean setting ("1"/"0", "true"/"false"). */
    public static function getBool(string $key, bool $default = false): bool
    {
        $v = self::get($key, null);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Persist a setting value (upsert) and refresh the cache.
     */
    public static function set(string $key, mixed $value): void
    {
        $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        Database::query(
            'INSERT INTO settings (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = :v2, updated_at = NOW()',
            ['k' => $key, 'v' => $value, 'v2' => $value]
        );
        self::$cache[$key] = $value;
    }

    /** Return every setting as an associative array. */
    public static function all(): array
    {
        if (!self::$loaded) {
            self::init();
        }
        return self::$cache;
    }
}
