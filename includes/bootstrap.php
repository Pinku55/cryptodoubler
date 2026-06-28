<?php
/**
 * MTASK - Application Bootstrap
 * --------------------------------------------------------------------------
 * Central entry point loaded by every PHP script (front-end, API, admin,
 * bot webhook). It:
 *   - defines path constants
 *   - loads configuration
 *   - registers a PSR-4-ish autoloader for the /classes directory
 *   - starts a hardened session
 *   - configures error handling and timezone
 *
 * @package MTASK
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Path constants
// ---------------------------------------------------------------------------
define('MTASK_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('CLASSES_PATH', BASE_PATH . '/classes');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LOGS_PATH', STORAGE_PATH . '/logs');
define('UPLOADS_PATH', BASE_PATH . '/assets/uploads');

// ---------------------------------------------------------------------------
// Installation guard
// ---------------------------------------------------------------------------
$configFile = CONFIG_PATH . '/config.php';

if (!file_exists($configFile)) {
    // Not installed yet. Let the installer handle it; everything else 503s.
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        if (PHP_SAPI !== 'cli') {
            header('Location: install.php');
        }
        exit;
    }
    return; // installer continues without a config
}

/** @var array $CONFIG Global application configuration. */
$CONFIG = require $configFile;

// ---------------------------------------------------------------------------
// Environment & error handling
// ---------------------------------------------------------------------------
date_default_timezone_set($CONFIG['app']['timezone'] ?? 'UTC');

if (($CONFIG['app']['environment'] ?? 'production') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

if (!is_dir(LOGS_PATH)) {
    @mkdir(LOGS_PATH, 0755, true);
}
ini_set('log_errors', '1');
ini_set('error_log', LOGS_PATH . '/php_errors.log');

// ---------------------------------------------------------------------------
// Autoloader for /classes (each class lives in classes/<ClassName>.php)
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $file = CLASSES_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ---------------------------------------------------------------------------
// Boot core services
// ---------------------------------------------------------------------------
Database::init($CONFIG['db']);
Settings::init();

// ---------------------------------------------------------------------------
// Hardened session
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    session_name($CONFIG['security']['session_name'] ?? 'mtask_session');
    session_set_cookie_params([
        'lifetime' => (int) ($CONFIG['security']['session_lifetime'] ?? 7200),
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Make the config available globally without using $GLOBALS everywhere.
$GLOBALS['MTASK_CONFIG'] = $CONFIG;

/**
 * Helper: fetch a configuration value with dot notation.
 *
 * @param string $key     Dot path, e.g. "security.app_key".
 * @param mixed  $default Fallback when the key does not exist.
 * @return mixed
 */
function config(string $key, mixed $default = null): mixed
{
    $segments = explode('.', $key);
    $value = $GLOBALS['MTASK_CONFIG'] ?? [];
    foreach ($segments as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
            $value = $value[$segment];
        } else {
            return $default;
        }
    }
    return $value;
}
