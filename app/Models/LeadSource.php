<?php

declare(strict_types=1);

namespace App\Models;

class LeadSource extends BaseModel
{
    protected string $table = 'lead_sources';
    protected bool $softDelete = false;

    /**
     * Get all active lead sources.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActive(): array
    {
        $stmt = $this->getPdo()->query("SELECT * FROM `{$this->table}` WHERE `is_active` = 1 ORDER BY `name` ASC");
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Alias for getActive.
     */
    public function getActiveSources(): array
    {
        return $this->getActive();
    }

    /**
     * Get all lead sources.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(array $orderBy = ['name' => 'ASC'], ?int $limit = null): array
    {
        return parent::all($orderBy, $limit);
    }

    /**
     * Find lead source by exact name (case-insensitive).
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->getPdo()->prepare("SELECT * FROM `{$this->table}` WHERE LOWER(`name`) = LOWER(?) LIMIT 1");
        $stmt->execute([trim($name)]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }
}
