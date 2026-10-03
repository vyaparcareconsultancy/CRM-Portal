<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

class Student extends BaseModel
{
    protected string $table = 'students';
    protected bool $softDelete = true;

    /**
     * Generate sequential student code like ST-2026-0001
     */
    public function generateStudentCode(int $year): string
    {
        $pdo = $this->getPdo();
        $prefix = sprintf('ST-%d-', $year);

        $sql = "SELECT `student_code` FROM `{$this->table}` WHERE `student_code` LIKE ? ORDER BY `id` DESC LIMIT 1";
        try {
            $stmt = $pdo->prepare($sql . " FOR UPDATE");
            $stmt->execute([$prefix . '%']);
        } catch (Throwable) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$prefix . '%']);
        }

        $lastCode = $stmt->fetchColumn();

        if (!$lastCode || !is_string($lastCode)) {
            return sprintf('ST-%d-0001', $year);
        }

        $parts = explode('-', $lastCode);
        $seq = isset($parts[2]) ? (int)$parts[2] + 1 : 1;

        return sprintf('ST-%d-%04d', $year, $seq);
    }

    /**
     * Find student by mobile number.
     */
    public function findByMobile(string $mobile): ?array
    {
        $clean = preg_replace('/[^0-9]/', '', $mobile);
        $stmt = $this->getPdo()->prepare("SELECT * FROM `{$this->table}` WHERE `mobile` = ? AND `deleted_at` IS NULL LIMIT 1");
        $stmt->execute([$clean]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * List students with optional trainer scoping, batch filter, and search.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listStudents(?int $trainerId = null, ?int $batchId = null, ?string $search = null): array
    {
        $sql = "
            SELECT DISTINCT
                s.*,
                (
                    SELECT b.`name` 
                    FROM `enrollments` e 
                    JOIN `batches` b ON b.`id` = e.`batch_id` 
                    WHERE e.`student_id` = s.`id` AND e.`deleted_at` IS NULL 
                    ORDER BY e.`id` DESC LIMIT 1
                ) AS `current_batch_name`,
                (
                    SELECT c.`name` 
                    FROM `enrollments` e 
                    JOIN `courses` c ON c.`id` = e.`course_id` 
                    WHERE e.`student_id` = s.`id` AND e.`deleted_at` IS NULL 
                    ORDER BY e.`id` DESC LIMIT 1
                ) AS `current_course_name`
            FROM `{$this->table}` s
        ";

        if ($trainerId !== null) {
            $sql .= "
                JOIN `enrollments` enr ON enr.`student_id` = s.`id` AND enr.`deleted_at` IS NULL
                JOIN `batches` bat ON bat.`id` = enr.`batch_id` AND bat.`deleted_at` IS NULL
            ";
        } elseif ($batchId !== null) {
            $sql .= "
                JOIN `enrollments` enr ON enr.`student_id` = s.`id` AND enr.`deleted_at` IS NULL
            ";
        }

        $sql .= " WHERE s.`deleted_at` IS NULL";
        $params = [];

        if ($trainerId !== null) {
            $sql .= " AND bat.`trainer_id` = ?";
            $params[] = $trainerId;
        }

        if ($batchId !== null) {
            $sql .= " AND enr.`batch_id` = ?";
            $params[] = $batchId;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (s.`name` LIKE ? OR s.`student_code` LIKE ? OR s.`mobile` LIKE ? OR s.`email` LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY s.`id` DESC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
