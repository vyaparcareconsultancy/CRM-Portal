<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ReminderRule extends BaseModel
{
    protected string $table = 'reminder_rules';
    protected bool $softDelete = true;

    /**
     * Get all active rules with optional service details.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveRules(): array
    {
        $sql = "
            SELECT r.*, s.name AS service_name, s.code AS service_code
            FROM `{$this->table}` r
            LEFT JOIN `services` s ON r.service_id = s.id AND s.deleted_at IS NULL
            WHERE r.is_active = 1 AND r.deleted_at IS NULL
            ORDER BY r.name ASC
        ";

        $stmt = $this->getPdo()->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            if (isset($row['channels']) && is_string($row['channels'])) {
                $row['channels_array'] = json_decode($row['channels'], true) ?: [];
            } else {
                $row['channels_array'] = is_array($row['channels'] ?? null) ? $row['channels'] : [];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Get all rules (active and inactive) for admin management.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllRules(): array
    {
        $sql = "
            SELECT r.*, s.name AS service_name, s.code AS service_code, u.name AS creator_name
            FROM `{$this->table}` r
            LEFT JOIN `services` s ON r.service_id = s.id AND s.deleted_at IS NULL
            LEFT JOIN `users` u ON r.created_by = u.id
            WHERE r.deleted_at IS NULL
            ORDER BY r.is_active DESC, r.name ASC
        ";

        $stmt = $this->getPdo()->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            if (isset($row['channels']) && is_string($row['channels'])) {
                $row['channels_array'] = json_decode($row['channels'], true) ?: [];
            } else {
                $row['channels_array'] = is_array($row['channels'] ?? null) ? $row['channels'] : [];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Find rule by rule code.
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->getPdo()->prepare("
            SELECT * FROM `{$this->table}`
            WHERE `rule_code` = ? AND `deleted_at` IS NULL
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (isset($row['channels']) && is_string($row['channels'])) {
                $row['channels_array'] = json_decode($row['channels'], true) ?: [];
            } else {
                $row['channels_array'] = is_array($row['channels'] ?? null) ? $row['channels'] : [];
            }
            return $row;
        }

        return null;
    }
}
