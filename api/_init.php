<?php
/**
 * MTASK - API bootstrap
 * --------------------------------------------------------------------------
 * Shared include for every endpoint under /api. Boots the application,
 * forces a JSON content type, enforces maintenance mode, and exposes the
 * request helpers used across endpoints.
 *
 * Endpoints authenticate statelessly: each request carries the Telegram
 * WebApp `initData`, which Auth::requireUser() verifies via HMAC.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

// All API output is JSON.
header('Content-Type: application/json; charset=utf-8');

/**
 * Read a request input (POST first, then GET) as a trimmed string.
 */
function input(string $key, ?string $default = null): ?string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

/** Read an integer request input. */
function inputInt(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $_GET[$key] ?? $default);
}

/**
 * Enforce maintenance mode for non-admin users.
 * Admins (with a valid session) bypass the gate.
 */
function enforceMaintenance(): void
{
    if (Settings::getBool('maintenance_mode', false) && Auth::admin() === null) {
        Response::error(
            Settings::get('announcement', 'The app is under maintenance. Please check back soon.'),
            503
        );
    }
}

enforceMaintenance();
