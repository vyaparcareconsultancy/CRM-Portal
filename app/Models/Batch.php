<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

class Batch extends BaseModel
{
    protected string $table = 'batches';
    protected bool $softDelete = true;

    /**
     * Generate unique batch code like BAT-2026-GST-01
     */
    public function generateBatchCode(string $courseCode, int $year): string
    {
        $pdo = $this->getPdo();
        $cleanCourse = preg_replace('/^CRS-/', '', strtoupper(trim($courseCode)));
        $prefix = sprintf('BAT-%d-%s-', $year, $cleanCourse);

        $sql = "SELECT `batch_code` FROM `{$this->table}` WHERE `batch_code` LIKE ? ORDER BY `id` DESC LIMIT 1";
        try {
            $stmt = $pdo->prepare($sql . " FOR UPDATE");
            $stmt->execute([$prefix . '%']);
        } catch (Throwable) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$prefix . '%']);
        }

        $lastCode = $stmt->fetchColumn();
        if (!$lastCode || !is_string($lastCode)) {
            return $prefix . '01';
        }

        $parts = explode('-', $lastCode);
        $seq = isset($parts[3]) ? (int)$parts[3] + 1 : 1;
        return sprintf('%s%02d', $prefix, $seq);
    }

    /**
     * List batches with joins on courses and trainers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBatches(?int $trainerId = null, ?string $status = null): array
    {
        $sql = "
            SELECT 
                b.*,
                c.`name` AS `course_name`,
                c.`course_code`,
                c.`fee` AS `course_fee`,
                u.`name` AS `trainer_name`,
                u.`email` AS `trainer_email`,
                (SELECT COUNT(*) FROM `enrollments` e WHERE e.`batch_id` = b.`id` AND e.`deleted_at` IS NULL) AS `enrolled_count`
            FROM `{$this->table}` b
            JOIN `courses` c ON c.`id` = b.`course_id`
            LEFT JOIN `users` u ON u.`id` = b.`trainer_id`
            WHERE b.`deleted_at` IS NULL
        ";
        $params = [];

        if ($trainerId !== null) {
            $sql .= " AND b.`trainer_id` = ?";
            $params[] = $trainerId;
        }

        if ($status !== null && $status !== '') {
            $sql .= " AND b.`status` = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY b.`start_date` DESC, b.`id` DESC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find single batch with detailed information.
     */
    public function findWithCourseAndTrainer(int|string $id): ?array
    {
        $id = (int)$id;
        $sql = "
            SELECT 
                b.*,
                c.`name` AS `course_name`,
                c.`course_code`,
                c.`fee` AS `course_fee`,
                c.`duration` AS `course_duration`,
                c.`syllabus_modules`,
                u.`name` AS `trainer_name`,
                u.`email` AS `trainer_email`,
                (SELECT COUNT(*) FROM `enrollments` e WHERE e.`batch_id` = b.`id` AND e.`deleted_at` IS NULL) AS `enrolled_count`
            FROM `{$this->table}` b
            JOIN `courses` c ON c.`id` = b.`course_id`
            LEFT JOIN `users` u ON u.`id` = b.`trainer_id`
            WHERE b.`id` = ? AND b.`deleted_at` IS NULL
            LIMIT 1
        ";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if (isset($row['syllabus_modules']) && is_string($row['syllabus_modules'])) {
            $row['modules'] = json_decode($row['syllabus_modules'], true) ?: [];
        } else {
            $row['modules'] = [];
        }

        return $row;
    }

    /**
     * Count currently enrolled students.
     */
    public function getEnrolledCount(int $batchId): int
    {
        $stmt = $this->getPdo()->prepare("SELECT COUNT(*) FROM `enrollments` WHERE `batch_id` = ? AND `deleted_at` IS NULL");
        $stmt->execute([$batchId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if batch has reached max seat capacity.
     */
    public function isCapacityReached(int $batchId): bool
    {
        $batch = $this->find($batchId);
        if (!$batch) {
            return true;
        }
        $capacity = (int)($batch['capacity'] ?? 30);
        $enrolled = $this->getEnrolledCount($batchId);
        return $enrolled >= $capacity;
    }
}
