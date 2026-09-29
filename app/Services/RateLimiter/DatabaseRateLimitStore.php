<?php

declare(strict_types=1);

namespace App\Services\RateLimiter;

use App\Core\Database;
use PDO;

class DatabaseRateLimitStore implements RateLimitStoreInterface
{
    private PDO $pdo;
    private string $table;

    public function __construct(?PDO $pdo = null, string $table = 'rate_limits')
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->table = $table;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $now = time();
        $resetAt = date('Y-m-d H:i:s', $now + $decaySeconds);

        // Fetch existing rate limit record
        $stmt = $this->pdo->prepare("SELECT `hits`, `reset_at` FROM `{$this->table}` WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row !== false) {
            $rowResetTime = strtotime((string)$row['reset_at']);
            if ($rowResetTime <= $now) {
                // Key has expired; reset count to 1 and update expiration
                $updateStmt = $this->pdo->prepare("UPDATE `{$this->table}` SET `hits` = 1, `reset_at` = ? WHERE `key` = ?");
                $updateStmt->execute([$resetAt, $key]);
                return 1;
            }

            // Still within decay window; increment hits
            $newHits = (int)$row['hits'] + 1;
            $updateStmt = $this->pdo->prepare("UPDATE `{$this->table}` SET `hits` = ? WHERE `key` = ?");
            $updateStmt->execute([$newHits, $key]);
            return $newHits;
        }

        // Key doesn't exist; insert new record
        $insertStmt = $this->pdo->prepare("INSERT INTO `{$this->table}` (`key`, `hits`, `reset_at`) VALUES (?, 1, ?)");
        $insertStmt->execute([$key, $resetAt]);
        return 1;
    }

    public function attempts(string $key): int
    {
        $stmt = $this->pdo->prepare("SELECT `hits`, `reset_at` FROM `{$this->table}` WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row === false) {
            return 0;
        }

        if (strtotime((string)$row['reset_at']) <= time()) {
            return 0;
        }

        return (int)$row['hits'];
    }

    public function availableIn(string $key): int
    {
        $stmt = $this->pdo->prepare("SELECT `reset_at` FROM `{$this->table}` WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row === false) {
            return 0;
        }

        $diff = strtotime((string)$row['reset_at']) - time();
        return max(0, $diff);
    }

    public function reset(string $key): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `key` = ?");
        $stmt->execute([$key]);
    }

    /**
     * Purge all expired rate limit records.
     */
    public function cleanExpired(): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `reset_at` <= ?");
        $stmt->execute([$now]);
        return $stmt->rowCount();
    }
}
