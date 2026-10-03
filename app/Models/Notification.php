<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Notification extends BaseModel
{
    protected string $table = 'notifications';
    protected bool $softDelete = false;

    /**
     * Create an in-app notification for a user.
     */
    public function createNotification(
        int $userId,
        string $title,
        string $message,
        ?string $link = null,
        string $type = 'mention'
    ): int {
        return (int)$this->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'type' => $type,
            'is_read' => 0,
        ]);
    }

    /**
     * Get unread notifications for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUnreadForUser(int $userId): array
    {
        $stmt = $this->getPdo()->prepare("
            SELECT * FROM `{$this->table}`
            WHERE `user_id` = ? AND `is_read` = 0
            ORDER BY `created_at` DESC, `id` DESC
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(int|string $id, int $userId): bool
    {
        $stmt = $this->getPdo()->prepare("
            UPDATE `{$this->table}`
            SET `is_read` = 1, `read_at` = NOW()
            WHERE `id` = ? AND `user_id` = ?
        ");
        return $stmt->execute([(int)$id, $userId]);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->getPdo()->prepare("
            UPDATE `{$this->table}`
            SET `is_read` = 1, `read_at` = NOW()
            WHERE `user_id` = ? AND `is_read` = 0
        ");
        return $stmt->execute([$userId]);
    }
}
