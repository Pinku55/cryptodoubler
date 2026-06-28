<?php
/**
 * Response
 * --------------------------------------------------------------------------
 * Standardised JSON responses for the AJAX API. Every endpoint returns the
 * shape: { "ok": bool, "data": mixed, "message": string }.
 *
 * @package MTASK
 */

declare(strict_types=1);

final class Response
{
    private function __construct() {}

    /** Send a success JSON payload and terminate. */
    public static function success(mixed $data = null, string $message = 'OK', int $code = 200): never
    {
        self::send(true, $data, $message, $code);
    }

    /** Send an error JSON payload and terminate. */
    public static function error(string $message = 'Error', int $code = 400, mixed $data = null): never
    {
        self::send(false, $data, $message, $code);
    }

    /** Internal: emit the JSON envelope. */
    private static function send(bool $ok, mixed $data, string $message, int $code): never
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }
        echo json_encode([
            'ok'      => $ok,
            'data'    => $data,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
