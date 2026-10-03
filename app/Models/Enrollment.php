<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

class Enrollment extends BaseModel
{
    protected string $table = 'enrollments';
    protected bool $softDelete = true;

    /**
     * Generate sequential enrollment number like ENR-2026-0001
     */
    public function generateEnrollmentNo(int $year): string
    {
        $pdo = $this->getPdo();
        $prefix = sprintf('ENR-%d-', $year);

        $sql = "SELECT `enrollment_no` FROM `{$this->table}` WHERE `enrollment_no` LIKE ? ORDER BY `id` DESC LIMIT 1";
        try {
            $stmt = $pdo->prepare($sql . " FOR UPDATE");
            $stmt->execute([$prefix . '%']);
        } catch (Throwable) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$prefix . '%']);
        }

        $lastCode = $stmt->fetchColumn();
        if (!$lastCode || !is_string($lastCode)) {
            return sprintf('ENR-%d-0001', $year);
        }

        $parts = explode('-', $lastCode);
        $seq = isset($parts[2]) ? (int)$parts[2] + 1 : 1;
        return sprintf('ENR-%d-%04d', $year, $seq);
    }

    /**
     * Find single enrollment with student, course, batch, and invoice details.
     */
    public function findWithDetails(int|string $id): ?array
    {
        $id = (int)$id;
        $sql = "
            SELECT 
                e.*,
                s.`name` AS `student_name`,
                s.`student_code`,
                s.`email` AS `student_email`,
                s.`mobile` AS `student_mobile`,
                c.`name` AS `course_name`,
                c.`course_code`,
                c.`duration` AS `course_duration`,
                b.`name` AS `batch_name`,
                b.`batch_code`,
                b.`timing` AS `batch_timing`,
                b.`days` AS `batch_days`,
                b.`trainer_id`,
                u.`name` AS `trainer_name`,
                i.`invoice_no`,
                i.`total_amount` AS `invoice_gross`,
                i.`net_amount` AS `invoice_total`,
                i.`net_amount` AS `invoice_net`,
                i.`paid_amount` AS `invoice_paid`,
                i.`balance_amount` AS `invoice_balance`,
                i.`discount_amount` AS `invoice_discount`,
                i.`status` AS `invoice_status`
            FROM `{$this->table}` e
            JOIN `students` s ON s.`id` = e.`student_id`
            JOIN `courses` c ON c.`id` = e.`course_id`
            JOIN `batches` b ON b.`id` = e.`batch_id`
            LEFT JOIN `users` u ON u.`id` = b.`trainer_id`
            LEFT JOIN `invoices` i ON i.`id` = e.`invoice_id`
            WHERE e.`id` = ? AND e.`deleted_at` IS NULL
            LIMIT 1
        ";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * List all enrollments for a given batch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForBatch(int $batchId): array
    {
        $sql = "
            SELECT 
                e.*,
                s.`name` AS `student_name`,
                s.`student_code`,
                s.`email` AS `student_email`,
                s.`mobile` AS `student_mobile`,
                s.`status` AS `student_status`,
                c.`name` AS `course_name`,
                i.`invoice_no`,
                i.`balance_amount` AS `invoice_balance`,
                i.`status` AS `invoice_status`
            FROM `{$this->table}` e
            JOIN `students` s ON s.`id` = e.`student_id`
            JOIN `courses` c ON c.`id` = e.`course_id`
            LEFT JOIN `invoices` i ON i.`id` = e.`invoice_id`
            WHERE e.`batch_id` = ? AND e.`deleted_at` IS NULL
            ORDER BY s.`name` ASC
        ";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$batchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * List all enrollments for a given student.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForStudent(int $studentId): array
    {
        $sql = "
            SELECT 
                e.*,
                c.`name` AS `course_name`,
                c.`course_code`,
                c.`duration` AS `course_duration`,
                b.`name` AS `batch_name`,
                b.`batch_code`,
                b.`start_date` AS `batch_start_date`,
                b.`end_date` AS `batch_end_date`,
                b.`timing` AS `batch_timing`,
                b.`days` AS `batch_days`,
                b.`status` AS `batch_status`,
                b.`trainer_id`,
                u.`name` AS `trainer_name`,
                i.`invoice_no`,
                i.`total_amount` AS `invoice_gross`,
                i.`net_amount` AS `invoice_total`,
                i.`net_amount` AS `invoice_net`,
                i.`paid_amount` AS `invoice_paid`,
                i.`balance_amount` AS `invoice_balance`,
                i.`discount_amount` AS `invoice_discount`,
                i.`status` AS `invoice_status`
            FROM `{$this->table}` e
            JOIN `courses` c ON c.`id` = e.`course_id`
            JOIN `batches` b ON b.`id` = e.`batch_id`
            LEFT JOIN `users` u ON u.`id` = b.`trainer_id`
            LEFT JOIN `invoices` i ON i.`id` = e.`invoice_id`
            WHERE e.`student_id` = ? AND e.`deleted_at` IS NULL
            ORDER BY e.`admission_date` DESC, e.`id` DESC
        ";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
