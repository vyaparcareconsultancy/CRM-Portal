<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use SessionHandlerInterface;

/**
 * Database-backed session handler.
 * Sessions stored in `sessions` table. Used when SESSION_DRIVER=database.
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetime;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->lifetime = ((int)($_ENV['SESSION_LIFETIME'] ?? 120)) * 60;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT payload FROM sessions WHERE id = ? AND last_activity > ? LIMIT 1'
        );
        $stmt->execute([$id, time() - $this->lifetime]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)$row['payload'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'REPLACE INTO sessions (id, payload, last_activity) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_activity < ?');
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
