<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * Simple database-backed job queue.
 *
 * Dispatch jobs from request handlers; process them via cron/worker.php.
 * ponytail: no daemon, no Redis, no message broker — cron polls the table.
 */
class JobQueue
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Push a job onto the queue.
     *
     * @param string               $handler     e.g. 'App\Services\MailService::sendPasswordReset'
     * @param array<string, mixed> $payload     Arguments passed to the handler
     * @param string               $queue       Queue name for priority separation
     * @param int                  $delaySeconds Delay before job becomes available
     * @param int                  $maxAttempts  Maximum retry attempts
     */
    public function dispatch(
        string $handler,
        array $payload = [],
        string $queue = 'default',
        int $delaySeconds = 0,
        int $maxAttempts = 3
    ): int {
        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);

        $stmt = $this->pdo->prepare(
            'INSERT INTO jobs (queue, handler, payload, max_attempts, available_at, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $queue,
            $handler,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $maxAttempts,
            $availableAt,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Claim and return the next available job from the queue.
     * Uses SELECT ... FOR UPDATE SKIP LOCKED for safe concurrency.
     *
     * @return array<string, mixed>|null
     */
    public function pop(string $queue = 'default'): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM jobs
                 WHERE queue = ?
                   AND reserved_at IS NULL
                   AND failed_at IS NULL
                   AND available_at <= NOW()
                   AND attempts < max_attempts
                 ORDER BY id ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED'
            );
            $stmt->execute([$queue]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->pdo->rollBack();
                return null;
            }

            $update = $this->pdo->prepare(
                'UPDATE jobs SET reserved_at = NOW(), attempts = attempts + 1 WHERE id = ?'
            );
            $update->execute([$job['id']]);
            $this->pdo->commit();

            $job['payload'] = json_decode((string)$job['payload'], true);
            return $job;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Mark a job as completed (delete it).
     */
    public function complete(int $jobId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM jobs WHERE id = ?');
        $stmt->execute([$jobId]);
    }

    /**
     * Mark a job as failed.
     */
    public function fail(int $jobId, string $error): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE jobs SET failed_at = NOW(), reserved_at = NULL, error = ? WHERE id = ?'
        );
        $stmt->execute([$error, $jobId]);
    }

    /**
     * Release a job back for retry (clear reservation).
     */
    public function release(int $jobId, int $delaySeconds = 30): void
    {
        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);
        $stmt = $this->pdo->prepare(
            'UPDATE jobs SET reserved_at = NULL, available_at = ? WHERE id = ?'
        );
        $stmt->execute([$availableAt, $jobId]);
    }

    /**
     * Get count of pending jobs per queue.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        $stmt = $this->pdo->query(
            "SELECT queue,
                    SUM(CASE WHEN reserved_at IS NULL AND failed_at IS NULL THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) AS processing,
                    SUM(CASE WHEN failed_at IS NOT NULL THEN 1 ELSE 0 END) AS failed
             FROM jobs GROUP BY queue"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
