<?php

declare(strict_types=1);

namespace App\Models;

class Course extends BaseModel
{
    protected string $table = 'courses';
    protected bool $softDelete = true;

    /**
     * Get all active courses with decoded modules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActive(): array
    {
        $stmt = $this->getPdo()->query("SELECT * FROM `{$this->table}` WHERE `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `name` ASC");
        $rows = $stmt->fetchAll() ?: [];
        return array_map([$this, 'formatRow'], $rows);
    }

    /**
     * Get all courses with decoded modules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->getPdo()->query("SELECT * FROM `{$this->table}` WHERE `deleted_at` IS NULL ORDER BY `name` ASC");
        $rows = $stmt->fetchAll() ?: [];
        return array_map([$this, 'formatRow'], $rows);
    }

    /**
     * Find course by ID with formatted modules.
     */
    public function find($id): ?array
    {
        $row = parent::find($id);
        return $row ? $this->formatRow($row) : null;
    }

    /**
     * Find by course code.
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->getPdo()->prepare("SELECT * FROM `{$this->table}` WHERE `course_code` = ? AND `deleted_at` IS NULL LIMIT 1");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ? $this->formatRow($row) : null;
    }

    /**
     * Format row, parsing JSON modules array.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function formatRow(array $row): array
    {
        if (isset($row['syllabus_modules']) && is_string($row['syllabus_modules'])) {
            $decoded = json_decode($row['syllabus_modules'], true);
            $row['modules'] = is_array($decoded) ? $decoded : [];
        } elseif (isset($row['syllabus_modules']) && is_array($row['syllabus_modules'])) {
            $row['modules'] = $row['syllabus_modules'];
        } else {
            $row['modules'] = [];
        }

        // Standardize duration string if duration_weeks exists
        if (empty($row['duration']) && !empty($row['duration_weeks'])) {
            $row['duration'] = $row['duration_weeks'] . ' Weeks';
        }

        return $row;
    }
}
