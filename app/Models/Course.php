<?php

declare(strict_types=1);

namespace App\Models;

class Course extends BaseModel
{
    protected string $table = 'courses';
    protected bool $softDelete = true;

    /**
     * Get all active courses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActive(): array
    {
        $stmt = $this->getPdo()->query("SELECT * FROM `{$this->table}` WHERE `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `name` ASC");
        return $stmt->fetchAll() ?: [];
    }
}
