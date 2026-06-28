<?php
/**
 * Database
 * --------------------------------------------------------------------------
 * Thin PDO wrapper exposing a single shared connection and convenience
 * helpers built exclusively on prepared statements.
 *
 * Usage:
 *   Database::init($config['db']);
 *   $row  = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
 *   $rows = Database::fetchAll('SELECT * FROM users');
 *   $id   = Database::insert('users', ['telegram_id' => 1, 'username' => 'x']);
 *
 * @package MTASK
 */

declare(strict_types=1);

final class Database
{
    /** @var PDO|null Shared PDO connection. */
    private static ?PDO $pdo = null;

    /** @var array Connection configuration. */
    private static array $config = [];

    /** Prevent instantiation. */
    private function __construct() {}

    /**
     * Initialise the connection settings (connection is lazy).
     *
     * @param array $config db config (host, port, name, user, pass, charset)
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get (and lazily create) the shared PDO connection.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c = self::$config;
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'] ?? '127.0.0.1',
            $c['port'] ?? '3306',
            $c['name'] ?? '',
            $c['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // Never leak credentials/SQL details to the client.
            error_log('[MTASK][DB] ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection error.');
        }

        return self::$pdo;
    }

    /**
     * Run a prepared statement and return the PDOStatement.
     *
     * @param string $sql    SQL with positional or named placeholders.
     * @param array  $params Bound parameters.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch a single row (associative array) or null. */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows as an array of associative arrays. */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** Fetch a single scalar value from the first column. */
    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetchColumn();
    }

    /**
     * Insert a row from an associative array and return the new id.
     *
     * @param string $table   Table name (trusted, never user input).
     * @param array  $data    column => value pairs.
     */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(static fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            '`' . implode('`,`', $cols) . '`',
            implode(',', $placeholders)
        );
        self::query($sql, self::namedParams($data));
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Update rows matching a WHERE clause.
     *
     * @param string $table  Table name (trusted).
     * @param array  $data   column => value pairs to set.
     * @param string $where  WHERE clause with named placeholders.
     * @param array  $params Parameters for the WHERE clause.
     * @return int Affected rows.
     */
    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "`{$col}` = :set_{$col}";
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);

        $bind = [];
        foreach ($data as $col => $val) {
            $bind["set_{$col}"] = $val;
        }
        foreach ($params as $k => $v) {
            $bind[ltrim((string) $k, ':')] = $v;
        }
        return self::query($sql, $bind)->rowCount();
    }

    /** Begin a transaction. */
    public static function begin(): void
    {
        self::pdo()->beginTransaction();
    }

    /** Commit the active transaction. */
    public static function commit(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    /** Roll back the active transaction. */
    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /** Convert an associative array into :name => value bound params. */
    private static function namedParams(array $data): array
    {
        $bind = [];
        foreach ($data as $k => $v) {
            $bind[$k] = $v;
        }
        return $bind;
    }
}
