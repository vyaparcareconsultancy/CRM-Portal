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
}
