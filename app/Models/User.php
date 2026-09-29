<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class User extends BaseModel
{
    protected string $table = 'users';
    protected bool $softDelete = true;

    public function findByEmail(string $email): ?array
    {
        return $this->first(['email' => $email]);
    }

    public function getRoleAndPermissions(int $roleId): array
    {
        $pdo = $this->getPdo();

        // 1. Fetch role
        $roleStmt = $pdo->prepare("SELECT `id`, `name`, `label` FROM `roles` WHERE `id` = ? LIMIT 1");
        $roleStmt->execute([$roleId]);
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // 2. Fetch permissions
        $permStmt = $pdo->prepare("
            SELECT p.`name`
            FROM `permissions` p
            JOIN `role_permissions` rp ON p.`id` = rp.`permission_id`
            WHERE rp.`role_id` = ?
        ");
        $permStmt->execute([$roleId]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return [
            'role' => $role,
            'permissions' => $permissions,
        ];
    }

    public function incrementFailedAttempts(int $id, int $currentFailed): int
    {
        $newCount = $currentFailed + 1;
        $lockedUntil = null;

        if ($newCount >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', time() + (15 * 60));
        }

        $stmt = $this->getPdo()->prepare("
            UPDATE `users`
            SET `failed_attempts` = ?,
                `locked_until` = COALESCE(?, `locked_until`),
                `updated_at` = NOW()
            WHERE `id` = ?
        ");
        $stmt->execute([$newCount, $lockedUntil, $id]);

        return $newCount;
    }

    public function resetFailedAttempts(int $id): void
    {
        $stmt = $this->getPdo()->prepare("
            UPDATE `users`
            SET `failed_attempts` = 0,
                `locked_until` = NULL,
                `last_login_at` = NOW(),
                `updated_at` = NOW()
            WHERE `id` = ?
        ");
        $stmt->execute([$id]);
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->getPdo()->prepare("
            UPDATE `users`
            SET `password_hash` = ?,
                `failed_attempts` = 0,
                `locked_until` = NULL,
                `updated_at` = NOW()
            WHERE `id` = ?
        ");
        return $stmt->execute([$passwordHash, $id]);
    }
}
