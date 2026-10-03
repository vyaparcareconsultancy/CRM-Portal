<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Attendance extends BaseModel
{
    protected string $table = 'attendance';
    protected bool $softDelete = false;

    /**
     * Bulk mark or update daily attendance for a batch.
     *
     * @param int $batchId
     * @param string $sessionDate YYYY-MM-DD
     * @param array<int, string|array{status: string, remarks?: string}> $records [student_id => status|details]
     * @param int|null $markedBy
     * @return int Number of records marked
     */
    public function bulkMark(int $batchId, string $sessionDate, array $records, ?int $markedBy = null): int
    {
        if (empty($records)) {
            return 0;
        }

        $pdo = $this->getPdo();
        $sql = "
            INSERT INTO `{$this->table}` (`batch_id`, `student_id`, `session_date`, `status`, `marked_by`, `remarks`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `status` = VALUES(`status`),
                `marked_by` = VALUES(`marked_by`),
                `remarks` = VALUES(`remarks`),
                `updated_at` = NOW()
        ";
        $stmt = $pdo->prepare($sql);

        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($records as $studentId => $info) {
                $status = is_array($info) ? ($info['status'] ?? 'present') : (string)$info;
                $remarks = is_array($info) ? ($info['remarks'] ?? null) : null;
                $status = strtolower(trim($status));
                if (!in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                    $status = 'present';
                }

                $stmt->execute([
                    $batchId,
                    (int)$studentId,
                    $sessionDate,
                    $status,
                    $markedBy,
                    $remarks
                ]);
                $count++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $count;
    }

    /**
     * Get batch attendance records for a specific date.
     *
     * @return array<int, array<string, mixed>> Keyed by student_id
     */
    public function getBatchAttendanceForDate(int $batchId, string $sessionDate): array
    {
        $stmt = $this->getPdo()->prepare("
            SELECT * FROM `{$this->table}`
            WHERE `batch_id` = ? AND `session_date` = ?
        ");
        $stmt->execute([$batchId, $sessionDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byStudent = [];
        foreach ($rows as $row) {
            $byStudent[(int)$row['student_id']] = $row;
        }
        return $byStudent;
    }

    /**
     * Get attendance summary statistics for a student.
     *
     * @return array{percentage: float, present: int, late: int, absent: int, excused: int, total: int}
     */
    public function getStudentAttendanceSummary(int $studentId, ?int $batchId = null): array
    {
        $sql = "
            SELECT 
                COUNT(*) as `total`,
                SUM(CASE WHEN `status` = 'present' THEN 1 ELSE 0 END) as `present`,
                SUM(CASE WHEN `status` = 'late' THEN 1 ELSE 0 END) as `late`,
                SUM(CASE WHEN `status` = 'absent' THEN 1 ELSE 0 END) as `absent`,
                SUM(CASE WHEN `status` = 'excused' THEN 1 ELSE 0 END) as `excused`
            FROM `{$this->table}`
            WHERE `student_id` = ?
        ";
        $params = [$studentId];

        if ($batchId !== null) {
            $sql .= " AND `batch_id` = ?";
            $params[] = $batchId;
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int)($res['total'] ?? 0);
        $present = (int)($res['present'] ?? 0);
        $late = (int)($res['late'] ?? 0);
        $absent = (int)($res['absent'] ?? 0);
        $excused = (int)($res['excused'] ?? 0);

        // Effective attendance includes both present and late sessions
        $attended = $present + $late;
        $pct = $total > 0 ? round(($attended / $total) * 100, 1) : 0.0;

        return [
            'percentage' => $pct,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'excused' => $excused,
            'total' => $total,
        ];
    }

    /**
     * Generate monthly attendance grid for a batch.
     *
     * @param int $batchId
     * @param string $yearMonth YYYY-MM
     * @return array{dates: string[], students: array<int, mixed>}
     */
    public function getMonthlySheet(int $batchId, string $yearMonth): array
    {
        $pdo = $this->getPdo();

        // 1. Get all session dates in this month for this batch
        $dateStmt = $pdo->prepare("
            SELECT DISTINCT `session_date` 
            FROM `{$this->table}` 
            WHERE `batch_id` = ? AND `session_date` LIKE ?
            ORDER BY `session_date` ASC
        ");
        $dateStmt->execute([$batchId, $yearMonth . '%']);
        $dates = $dateStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // 2. Get all enrolled students for this batch
        $studentStmt = $pdo->prepare("
            SELECT 
                s.`id`, 
                s.`student_code`, 
                s.`name`, 
                s.`mobile`,
                e.`enrollment_no`, 
                e.`status` AS `enrollment_status`
            FROM `enrollments` e
            JOIN `students` s ON s.`id` = e.`student_id`
            WHERE e.`batch_id` = ? AND e.`deleted_at` IS NULL
            ORDER BY s.`name` ASC
        ");
        $studentStmt->execute([$batchId]);
        $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Get all attendance logs for this batch in this month
        $attStmt = $pdo->prepare("
            SELECT `student_id`, `session_date`, `status`, `remarks`
            FROM `{$this->table}`
            WHERE `batch_id` = ? AND `session_date` LIKE ?
        ");
        $attStmt->execute([$batchId, $yearMonth . '%']);
        $rawRecords = $attStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mapped = [];
        foreach ($rawRecords as $r) {
            $mapped[(int)$r['student_id']][$r['session_date']] = $r['status'];
        }

        // 4. Assemble student rows with summary
        $studentRows = [];
        foreach ($students as $st) {
            $sId = (int)$st['id'];
            $stAtt = $mapped[$sId] ?? [];
            $presentCount = 0;
            $lateCount = 0;
            $absentCount = 0;
            $excusedCount = 0;

            foreach ($dates as $d) {
                $status = $stAtt[$d] ?? null;
                if ($status === 'present') {
                    $presentCount++;
                } elseif ($status === 'late') {
                    $lateCount++;
                } elseif ($status === 'absent') {
                    $absentCount++;
                } elseif ($status === 'excused') {
                    $excusedCount++;
                }
            }

            $totalMarked = $presentCount + $lateCount + $absentCount + $excusedCount;
            $pct = $totalMarked > 0 ? round((($presentCount + $lateCount) / $totalMarked) * 100, 1) : 0.0;

            $st['attendance_by_date'] = $stAtt;
            $st['summary'] = [
                'percentage' => $pct,
                'present' => $presentCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'excused' => $excusedCount,
                'total' => $totalMarked,
            ];
            $studentRows[] = $st;
        }

        return [
            'dates' => $dates,
            'students' => $studentRows,
            'month' => $yearMonth,
        ];
    }
}
