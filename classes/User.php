<?php
/**
 * User
 * --------------------------------------------------------------------------
 * User model and balance ledger. Encapsulates account creation/lookup,
 * atomic balance mutations, and the transaction history that powers the
 * wallet and activity feeds.
 *
 * Balances are stored in whole MT units (BIGINT) to avoid float drift.
 *
 * @package MTASK
 */

declare(strict_types=1);

final class User
{
    /** Transaction type constants. */
    public const TX_AD          = 'ad';
    public const TX_TASK        = 'task';
    public const TX_DAILY_BONUS = 'daily_bonus';
    public const TX_REFERRAL    = 'referral';
    public const TX_WITHDRAW    = 'withdraw';
    public const TX_ADMIN       = 'admin_adjust';
    public const TX_REFUND      = 'refund';

    private function __construct() {}

    /**
     * Find an existing user by Telegram id or create a new one.
     *
     * @param array       $tg       Decoded Telegram user object.
     * @param string|null $refCode  Referral code from start parameter (optional).
     * @return array The full user row.
     */
    public static function findOrCreate(array $tg, ?string $refCode = null): array
    {
        $telegramId = (int) ($tg['id'] ?? 0);
        $existing = self::findByTelegramId($telegramId);

        if ($existing !== null) {
            // Keep profile fields fresh on each login.
            Database::update('users', [
                'username'   => $tg['username'] ?? $existing['username'],
                'first_name' => $tg['first_name'] ?? $existing['first_name'],
                'last_name'  => $tg['last_name'] ?? $existing['last_name'],
                'language'   => $tg['language_code'] ?? $existing['language'],
                'last_login' => date('Y-m-d H:i:s'),
                'last_ip'    => Security::clientIp(),
            ], 'id = :id', ['id' => $existing['id']]);
            return self::findById((int) $existing['id']);
        }

        // Resolve referrer (if any) before inserting.
        $referrerId = null;
        if ($refCode) {
            $ref = Database::fetch('SELECT id FROM users WHERE referral_code = ?', [strtoupper(trim($refCode))]);
            if ($ref) {
                $referrerId = (int) $ref['id'];
            }
        }

        // Generate a unique referral code.
        do {
            $code = Security::randomCode(8);
        } while (Database::scalar('SELECT 1 FROM users WHERE referral_code = ?', [$code]));

        $id = Database::insert('users', [
            'telegram_id'   => $telegramId,
            'username'      => $tg['username'] ?? null,
            'first_name'    => $tg['first_name'] ?? null,
            'last_name'     => $tg['last_name'] ?? null,
            'language'      => $tg['language_code'] ?? 'en',
            'photo_url'     => $tg['photo_url'] ?? null,
            'referral_code' => $code,
            'referred_by'   => $referrerId,
            'balance'       => 0,
            'status'        => 'active',
            'last_ip'       => Security::clientIp(),
            'last_login'    => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        // Reward the referrer immediately (configurable).
        if ($referrerId) {
            self::handleReferralReward($referrerId, $id);
        }

        return self::findById($id);
    }

    /** Find a user row by primary key. */
    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** Find a user row by Telegram id. */
    public static function findByTelegramId(int $telegramId): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE telegram_id = ?', [$telegramId]);
    }

    /**
     * Atomically adjust a user's balance and record a transaction.
     *
     * @param int    $userId  Target user id.
     * @param int    $amount  Signed amount in MT (positive = credit).
     * @param string $type    One of the TX_* constants.
     * @param string $note    Human readable note.
     * @param array  $meta    Optional metadata (stored as JSON).
     * @return int The new balance.
     */
    public static function adjustBalance(int $userId, int $amount, string $type, string $note = '', array $meta = []): int
    {
        Database::begin();
        try {
            // Lock the row for a consistent read-modify-write.
            $row = Database::fetch('SELECT balance FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if ($row === null) {
                throw new RuntimeException('User not found');
            }
            $current = (int) $row['balance'];
            $new = $current + $amount;
            if ($new < 0) {
                throw new RuntimeException('Insufficient balance');
            }

            Database::update('users', ['balance' => $new], 'id = :id', ['id' => $userId]);

            // Maintain lifetime aggregates.
            if ($amount > 0) {
                Database::query('UPDATE users SET total_earned = total_earned + ? WHERE id = ?', [$amount, $userId]);
            }

            Database::insert('transactions', [
                'user_id'    => $userId,
                'type'       => $type,
                'amount'     => $amount,
                'balance_after' => $new,
                'note'       => $note,
                'meta'       => $meta ? json_encode($meta) : null,
                'status'     => 'completed',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
            return $new;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Reward a referrer when a new user signs up under them.
     */
    private static function handleReferralReward(int $referrerId, int $newUserId): void
    {
        // Respect the configurable maximum referrals per user.
        $maxReferrals = Settings::getInt('referral_max', 0); // 0 = unlimited
        $count = (int) Database::scalar('SELECT COUNT(*) FROM users WHERE referred_by = ?', [$referrerId]);
        if ($maxReferrals > 0 && $count > $maxReferrals) {
            return;
        }

        $reward = Settings::getInt('referral_reward', 1000);
        if ($reward > 0) {
            self::adjustBalance($referrerId, $reward, self::TX_REFERRAL, 'Referral signup bonus', ['referred_user' => $newUserId]);
            Database::query('UPDATE users SET total_referrals = total_referrals + 1 WHERE id = ?', [$referrerId]);

            // Notify the referrer via the bot (best effort).
            $referrer = self::findById($referrerId);
            if ($referrer && !empty($referrer['telegram_id'])) {
                Telegram::sendMessage(
                    (int) $referrer['telegram_id'],
                    "🎉 You earned <b>" . number_format($reward) . " MT</b> from a new referral!"
                );
            }
        }
    }

    /**
     * Paginated transaction history for a user.
     *
     * @param int         $userId User id.
     * @param int         $page   1-based page number.
     * @param int         $limit  Page size.
     * @param string|null $type   Optional type filter.
     */
    public static function transactions(int $userId, int $page = 1, int $limit = 20, ?string $type = null): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        $where = 'user_id = ?';
        $params = [$userId];
        if ($type !== null && $type !== '') {
            $where .= ' AND type = ?';
            $params[] = $type;
        }

        $rows = Database::fetchAll(
            "SELECT * FROM transactions WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        $total = (int) Database::scalar("SELECT COUNT(*) FROM transactions WHERE {$where}", $params);

        return [
            'items' => $rows,
            'page'  => $page,
            'pages' => (int) ceil($total / $limit),
            'total' => $total,
        ];
    }
}
