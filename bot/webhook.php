<?php
/**
 * MTASK - Telegram Bot Webhook
 * --------------------------------------------------------------------------
 * Receives updates from Telegram and handles bot commands:
 *   /start [refcode] - register + open Mini App, track referrals
 *   /help            - command list
 *   /account         - account summary
 *   /balance         - current balance
 *   /referral        - referral link
 *   /withdraw        - quick withdraw info / opens Mini App
 *
 * Registered automatically by the installer. Telegram POSTs JSON here.
 *
 * @package MTASK
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

// ---------------------------------------------------------------------------
// Verify the Telegram secret token (set during webhook registration).
// ---------------------------------------------------------------------------
$expectedSecret = (string) Settings::get('webhook_secret', '');
$receivedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($expectedSecret !== '' && !hash_equals($expectedSecret, $receivedSecret)) {
    http_response_code(403);
    exit;
}

// ---------------------------------------------------------------------------
// Read and decode the incoming update.
// ---------------------------------------------------------------------------
$raw = file_get_contents('php://input');
$update = json_decode((string) $raw, true);
if (!is_array($update)) {
    http_response_code(200); // acknowledge anyway so Telegram stops retrying
    exit;
}

Logger::log('bot', 'INFO', 'Update received', ['id' => $update['update_id'] ?? null]);

$baseUrl     = rtrim((string) config('app.base_url', ''), '/');
$appUrl      = $baseUrl . '/app.php';
$botUsername = Settings::get('telegram_bot_username', '');

/** Inline keyboard button that opens the Mini App. */
function openAppKeyboard(string $appUrl): array
{
    return ['inline_keyboard' => [[['text' => '🚀 Open MTASK App', 'web_app' => ['url' => $appUrl]]]]];
}

// ---------------------------------------------------------------------------
// Handle plain messages / commands.
// ---------------------------------------------------------------------------
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId  = (int) ($message['chat']['id'] ?? 0);
    $text    = trim((string) ($message['text'] ?? ''));
    $from    = $message['from'] ?? [];

    // Build a minimal Telegram user array for User::findOrCreate.
    $tgUser = [
        'id'            => (int) ($from['id'] ?? 0),
        'username'      => $from['username'] ?? null,
        'first_name'    => $from['first_name'] ?? null,
        'last_name'     => $from['last_name'] ?? null,
        'language_code' => $from['language_code'] ?? 'en',
    ];

    // Parse command + argument.
    $parts   = preg_split('/\s+/', $text, 2);
    $command = strtolower($parts[0] ?? '');
    $argument = $parts[1] ?? '';
    // Strip @botusername suffix from commands in groups.
    $command = preg_replace('/@.*/', '', $command);

    $siteName = Settings::get('site_name', 'MTASK');

    switch ($command) {
        case '/start':
            // Register the user (with referral code from the start payload).
            $refCode = $argument !== '' ? $argument : null;
            $user = User::findOrCreate($tgUser, $refCode);

            $welcome = "👋 <b>Welcome to {$siteName}!</b>\n\n"
                . "Earn MT coins by watching ads, completing tasks, daily bonuses and inviting friends. "
                . "Tap the button below to open the app and start earning!\n\n"
                . "💰 Your balance: <b>" . number_format((int) $user['balance']) . " MT</b>\n"
                . "🔗 Your referral code: <code>{$user['referral_code']}</code>";

            Telegram::sendMessage($chatId, $welcome, openAppKeyboard($appUrl));
            break;

        case '/help':
            $help = "🤖 <b>{$siteName} Commands</b>\n\n"
                . "/start - Open the app\n"
                . "/account - Your account summary\n"
                . "/balance - Check your balance\n"
                . "/referral - Get your referral link\n"
                . "/withdraw - Withdraw your earnings\n"
                . "/help - Show this message";
            Telegram::sendMessage($chatId, $help, openAppKeyboard($appUrl));
            break;

        case '/account':
            $user = User::findByTelegramId((int) $tgUser['id']);
            if ($user) {
                $msg = "👤 <b>Your Account</b>\n\n"
                    . "💰 Balance: <b>" . number_format((int) $user['balance']) . " MT</b>\n"
                    . "📈 Total earned: " . number_format((int) $user['total_earned']) . " MT\n"
                    . "👥 Referrals: " . (int) $user['total_referrals'] . "\n"
                    . "🔥 Daily streak: " . (int) $user['daily_streak'] . " days\n"
                    . "🔗 Referral code: <code>{$user['referral_code']}</code>";
            } else {
                $msg = "Please send /start first.";
            }
            Telegram::sendMessage($chatId, $msg, openAppKeyboard($appUrl));
            break;

        case '/balance':
            $user = User::findByTelegramId((int) $tgUser['id']);
            $bal = $user ? number_format((int) $user['balance']) : '0';
            Telegram::sendMessage($chatId, "💰 Your balance: <b>{$bal} MT</b>", openAppKeyboard($appUrl));
            break;

        case '/referral':
            $user = User::findOrCreate($tgUser);
            $link = $botUsername
                ? "https://t.me/{$botUsername}?start={$user['referral_code']}"
                : "{$appUrl}?ref={$user['referral_code']}";
            $reward = Settings::getInt('referral_reward', 1000);
            $msg = "🔗 <b>Invite &amp; Earn</b>\n\n"
                . "Share your link and earn <b>" . number_format($reward) . " MT</b> per friend who joins!\n\n"
                . "Your link:\n{$link}";
            Telegram::sendMessage($chatId, $msg, openAppKeyboard($appUrl));
            break;

        case '/withdraw':
            $min = number_format(Settings::getInt('min_withdraw', 20000));
            $msg = "💸 <b>Withdraw</b>\n\nMinimum withdrawal is <b>{$min} MT</b>. "
                . "Open the app to choose a payment method and request a payout.";
            Telegram::sendMessage($chatId, $msg, openAppKeyboard($appUrl));
            break;

        default:
            // Non-command text: gentle nudge to open the app.
            Telegram::sendMessage($chatId, "Tap below to open <b>{$siteName}</b> and start earning! 👇", openAppKeyboard($appUrl));
            break;
    }
}

// ---------------------------------------------------------------------------
// Handle callback queries (inline button presses), if any.
// ---------------------------------------------------------------------------
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    Telegram::api('answerCallbackQuery', ['callback_query_id' => $cb['id'] ?? '']);
}

// Always acknowledge.
http_response_code(200);
echo 'OK';
