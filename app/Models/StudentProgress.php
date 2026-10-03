<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class StudentProgress extends BaseModel
{
    protected string $table = 'student_module_progress';
    protected bool $softDelete = false;

    /**
     * Initialize modules progress for an enrollment.
     *
     * @param int $enrollmentId
     * @param int $studentId
     * @param array<int, string|array{name: string, order?: int}> $modules
     */
    public function initializeModules(int $enrollmentId, int $studentId, array $modules): void
    {
        if (empty($modules)) {
            return;
        }

        $sql = "
            INSERT IGNORE INTO `{$this->table}` (`enrollment_id`, `student_id`, `module_name`, `module_order`, `status`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, 'not_started', NOW(), NOW())
        ";
        $stmt = $this->getPdo()->prepare($sql);

        $order = 1;
        foreach ($modules as $mod) {
            $name = is_array($mod) ? ($mod['name'] ?? 'Module ' . $order) : (string)$mod;
            $modOrder = is_array($mod) && isset($mod['order']) ? (int)$mod['order'] : $order;
            $stmt->execute([$enrollmentId, $studentId, trim($name), $modOrder]);
            $order++;
        }
    }

    /**
     * Update progress and test score for a specific module.
     */
    public function updateModule(
        int $enrollmentId,
        string $moduleName,
        string $status,
        ?string $remarks = null,
        ?float $testScore = null,
        ?int $updatedBy = null
    ): bool {
        $status = strtolower(trim($status));
        if (!in_array($status, ['not_started', 'in_progress', 'completed'], true)) {
            $status = 'in_progress';
        }

        $stmt = $this->getPdo()->prepare("
            UPDATE `{$this->table}` 
            SET `status` = ?, `trainer_remarks` = ?, `test_score` = ?, `updated_by` = ?, `updated_at` = NOW()
            WHERE `enrollment_id` = ? AND `module_name` = ?
        ");
        $success = $stmt->execute([
            $status,
            $remarks,
            $testScore,
            $updatedBy,
            $enrollmentId,
            $moduleName
        ]);

        if ($success) {
            $this->evaluateCertificateReadiness($enrollmentId);
        }

        return $success;
    }

    /**
     * Get module progress list for an enrollment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProgressForEnrollment(int $enrollmentId): array
    {
        $stmt = $this->getPdo()->prepare("
            SELECT p.*, u.`name` AS `updated_by_name`
            FROM `{$this->table}` p
            LEFT JOIN `users` u ON u.`id` = p.`updated_by`
            WHERE p.`enrollment_id` = ?
            ORDER BY p.`module_order` ASC, p.`id` ASC
        ");
        $stmt->execute([$enrollmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Evaluate completion status and set certificate_ready flag on enrollment.
     */
    public function evaluateCertificateReadiness(int $enrollmentId): bool
    {
        $pdo = $this->getPdo();

        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as `total_modules`,
                SUM(CASE WHEN `status` = 'completed' THEN 1 ELSE 0 END) as `completed_modules`
            FROM `{$this->table}`
            WHERE `enrollment_id` = ?
        ");
        $stmt->execute([$enrollmentId]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)($counts['total_modules'] ?? 0);
        $completed = (int)($counts['completed_modules'] ?? 0);

        $isReady = ($total > 0 && $total === $completed);

        $updateStmt = $pdo->prepare("UPDATE `enrollments` SET `certificate_ready` = ? WHERE `id` = ?");
        $updateStmt->execute([$isReady ? 1 : 0, $enrollmentId]);

        return $isReady;
    }
}
