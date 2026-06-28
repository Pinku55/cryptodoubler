<?php
/**
 * Telegram
 * --------------------------------------------------------------------------
 * Telegram WebApp initData verification (HMAC) and Bot API client.
 *
 * Verification follows Telegram's documented algorithm:
 *   secret_key = HMAC_SHA256(bot_token, "WebAppData")
 *   hash       = HMAC_SHA256(data_check_string, secret_key)
 *
 * @package MTASK
 */

declare(strict_types=1);

final class Telegram
{
    private function __construct() {}

    /** Resolve the configured bot token (DB settings first, then config). */
    public static function botToken(): string
    {
        $token = (string) Settings::get('telegram_bot_token', '');
        if ($token === '') {
            $token = (string) config('telegram.bot_token', '');
        }
        return $token;
    }

    /**
     * Verify Telegram WebApp initData and return the parsed fields on success.
     *
     * @param string $initData Raw initData query string from window.Telegram.WebApp.
     * @param int    $maxAge   Maximum allowed auth_date age in seconds (anti-replay).
     * @return array|null Parsed data (incl. decoded `user`) or null when invalid.
     */
    public static function verifyInitData(string $initData, int $maxAge = 86400): ?array
    {
        if ($initData === '') {
            return null;
        }

        parse_str($initData, $params);
        if (empty($params['hash'])) {
            return null;
        }

        $providedHash = (string) $params['hash'];
        unset($params['hash']);

        // Build the data-check-string: keys sorted alphabetically, "key=value"
        // joined by line feeds.
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $pairs);

        $token = self::botToken();
        if ($token === '') {
            return null;
        }

        $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($calculatedHash, $providedHash)) {
            Logger::security('Telegram initData hash mismatch');
            return null;
        }

        // Anti-replay: reject stale auth_date values.
        if (!empty($params['auth_date'])) {
            $age = time() - (int) $params['auth_date'];
            if ($age > $maxAge) {
                Logger::security('Telegram initData expired', ['age' => $age]);
                return null;
            }
        }

        // Decode the embedded user JSON if present.
        if (!empty($params['user'])) {
            $user = json_decode((string) $params['user'], true);
            if (is_array($user)) {
                $params['user'] = $user;
            }
        }

        return $params;
    }

    // -----------------------------------------------------------------
    // Bot API
    // -----------------------------------------------------------------

    /**
     * Call a Bot API method.
     *
     * @param string $method Bot API method, e.g. "sendMessage".
     * @param array  $params Method parameters.
     * @return array Decoded API response (or ['ok'=>false] on transport error).
     */
    public static function api(string $method, array $params = []): array
    {
        $token = self::botToken();
        if ($token === '') {
            return ['ok' => false, 'description' => 'Bot token not configured'];
        }

        $url = "https://api.telegram.org/bot{$token}/{$method}";

        // Prefer cURL (available on virtually all cPanel hosts).
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($params),
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                Logger::error('Telegram API cURL error: ' . $err);
                return ['ok' => false, 'description' => $err];
            }
        } else {
            // Fallback to file_get_contents with a stream context.
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => http_build_query($params),
                    'timeout' => 15,
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                return ['ok' => false, 'description' => 'Transport error'];
            }
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : ['ok' => false];
    }

    /** Send a text message (HTML parse mode) to a chat. */
    public static function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): array
    {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        return self::api('sendMessage', $params);
    }

    /** Register the webhook URL with Telegram. */
    public static function setWebhook(string $url, string $secretToken = ''): array
    {
        $params = ['url' => $url, 'allowed_updates' => json_encode(['message', 'callback_query'])];
        if ($secretToken !== '') {
            $params['secret_token'] = $secretToken;
        }
        return self::api('setWebhook', $params);
    }
}
