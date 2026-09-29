<?php

declare(strict_types=1);

namespace App\Models;

class ActivityLog extends BaseModel
{
    protected string $table = 'activity_log';
    protected bool $timestamps = false;
    protected bool $softDelete = false;

    public function log(
        int|string|null $userId,
        string $entityType,
        int|string $entityId,
        string $action,
        ?array $newValues = null,
        ?array $oldValues = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): int {
        $data = [
            'user_id' => $userId !== null && (int)$userId > 0 ? (int)$userId : null,
            'entity_type' => $entityType,
            'entity_id' => (int)$entityId,
            'action' => $action,
            'old_values' => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return (int)$this->insert($data);
    }

    public function getByEntity(string $entityType, int|string $entityId, int $limit = 50): array
    {
        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM `{$this->table}` a
                LEFT JOIN `users` u ON a.user_id = u.id
                WHERE a.entity_type = ? AND a.entity_id = ?
                ORDER BY a.id DESC
                LIMIT {$limit}";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$entityType, (int)$entityId]);
        return $stmt->fetchAll();
    }

    public function getForClient(int|string $clientId, int $limit = 50): array
    {
        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM `{$this->table}` a
                LEFT JOIN `users` u ON a.user_id = u.id
                WHERE (a.entity_type = 'client' AND a.entity_id = ?)
                   OR (a.entity_type = 'followup' AND a.entity_id IN (SELECT id FROM `follow_ups` WHERE client_id = ?))
                ORDER BY a.id DESC
                LIMIT {$limit}";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([(int)$clientId, (int)$clientId]);
        return $stmt->fetchAll();
    }
}
