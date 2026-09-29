<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

/**
 * Database singleton with optional read replica support.
 *
 * Write connection: DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS (always used for INSERT/UPDATE/DELETE)
 * Read connection:  DB_READ_HOST (optional). When set, SELECT-heavy paths (reports, exports) can
 *                   call Database::getReadConnection() to route reads to a replica.
 *                   Falls back to the write connection when DB_READ_HOST is not configured.
 */
final class Database
{
    private static ?PDO $instance = null;
    private static ?PDO $readInstance = null;

    private function __construct()
    {
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createPdo(
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                (int)($_ENV['DB_PORT'] ?? 3306),
                $_ENV['DB_NAME'] ?? 'crm_db',
                $_ENV['DB_USER'] ?? 'root',
                $_ENV['DB_PASS'] ?? ''
            );
        }

        return self::$instance;
    }

    /**
     * Returns a read-only replica connection if DB_READ_HOST is configured,
     * otherwise falls back to the primary write connection.
     *
     * ponytail: single method, no ConnectionManager class. Add when >1 read replica needed.
     */
    public static function getReadConnection(): PDO
    {
        $readHost = $_ENV['DB_READ_HOST'] ?? '';
        if ($readHost === '') {
            return self::getConnection();
        }

        if (self::$readInstance === null) {
            self::$readInstance = self::createPdo(
                $readHost,
                (int)($_ENV['DB_READ_PORT'] ?? $_ENV['DB_PORT'] ?? 3306),
                $_ENV['DB_NAME'] ?? 'crm_db',
                $_ENV['DB_READ_USER'] ?? $_ENV['DB_USER'] ?? 'root',
                $_ENV['DB_READ_PASS'] ?? $_ENV['DB_PASS'] ?? ''
            );
        }

        return self::$readInstance;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function setReadConnection(?PDO $pdo): void
    {
        self::$readInstance = $pdo;
    }

    private static function createPdo(string $host, int $port, string $database, string $username, string $password): PDO
    {
        $configFile = dirname(__DIR__, 2) . '/config/database.php';
        $config = file_exists($configFile) ? require $configFile : [];

        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $username, $password, $options);
    }
}
