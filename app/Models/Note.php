<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Note extends BaseModel
{
    protected string $table = 'notes';
    protected bool $softDelete = true;

    /**
     * List notes for an entity (lead, client, student), pinned notes first, then newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForEntity(string $entityType, int|string $entityId): array
    {
        $sql = "
            SELECT n.*, u.`name` AS `author_name`, u.`email` AS `author_email`
            FROM `{$this->table}` n
            JOIN `users` u ON u.`id` = n.`user_id`
            WHERE n.`entity_type` = ? AND n.`entity_id` = ? AND n.`deleted_at` IS NULL
            ORDER BY n.`is_pinned` DESC, n.`created_at` DESC, n.`id` DESC
        ";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$entityType, (int)$entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Add a note to an entity.
     */
    public function addNote(
        string $entityType,
        int|string $entityId,
        int $userId,
        string $note,
        bool $isPinned = false
    ): int {
        return (int)$this->create([
            'entity_type' => $entityType,
            'entity_id' => (int)$entityId,
            'user_id' => $userId,
            'note' => $note,
            'is_pinned' => $isPinned ? 1 : 0,
        ]);
    }

    /**
     * Toggle pinned status.
     */
    public function togglePin(int|string $id): bool
    {
        $stmt = $this->getPdo()->prepare("
            UPDATE `{$this->table}`
            SET `is_pinned` = CASE WHEN `is_pinned` = 1 THEN 0 ELSE 1 END, `updated_at` = NOW()
            WHERE `id` = ? AND `deleted_at` IS NULL
        ");
        return $stmt->execute([(int)$id]);
    }
}
