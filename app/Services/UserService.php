<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Models\User;
use PDO;
use RuntimeException;

class UserService
{
    private User $userModel;

    public function __construct(?User $userModel = null)
    {
        $this->userModel = $userModel ?? new User();
    }

    public function listUsers(int $page = 1, int $perPage = 25): array
    {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        // Total count
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `deleted_at` IS NULL");
        $total = (int)$countStmt->fetchColumn();

        // Paginated list
        $stmt = $pdo->prepare("
            SELECT 
                u.`id`, u.`role_id`, r.`name` AS `role_name`, r.`label` AS `role_label`,
                u.`name`, u.`email`, u.`mobile`, u.`is_active`, u.`last_login_at`, u.`created_at`
            FROM `users` u
            JOIN `roles` r ON u.`role_id` = r.`id`
            WHERE u.`deleted_at` IS NULL
            ORDER BY u.`id` DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lastPage = max(1, (int)ceil($total / $perPage));

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    public function getAdminRoleId(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT `id` FROM `roles` WHERE `name` = 'admin' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : 1;
    }

    public function countActiveAdmins(): int
    {
        $adminRoleId = $this->getAdminRoleId();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM `users` 
            WHERE `role_id` = ? 
              AND `is_active` = 1 
              AND `deleted_at` IS NULL
        ");
        $stmt->execute([$adminRoleId]);
        return (int)$stmt->fetchColumn();
    }

    public function getRoles(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT `id`, `name`, `label` FROM `roles` WHERE `name` != 'sales' ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUser(int $id): ?array
    {
        $user = $this->userModel->find($id);
        if ($user) {
            unset($user['password_hash']);
        }
        return $user;
    }

    public function createUser(array $data): array
    {
        $pdo = Database::getConnection();

        $roleId = isset($data['role_id']) ? (int)$data['role_id'] : 0;
        if ($roleId <= 0 && !empty($data['role'])) {
            $stmt = $pdo->prepare("SELECT `id` FROM `roles` WHERE `name` = ? LIMIT 1");
            $stmt->execute([strtolower((string)$data['role'])]);
            $roleId = (int)$stmt->fetchColumn();
        }

        if ($roleId <= 0) {
            throw new RuntimeException("Invalid role selected.", 422);
        }

        $stmt = $pdo->prepare("SELECT `id` FROM `roles` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$roleId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException("Selected role does not exist.", 422);
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $userId = $this->userModel->insert([
            'role_id' => $roleId,
            'name' => trim($data['name']),
            'email' => trim(strtolower($data['email'])),
            'mobile' => $data['mobile'] ?? null,
            'password_hash' => $hash,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);

        Logger::security("User created by admin", [
            'created_user_id' => $userId,
            'email' => $data['email'],
            'role_id' => $roleId,
            'admin_id' => Session::get('user_id'),
        ]);

        $created = $this->userModel->find($userId);
        unset($created['password_hash']);
        return $created;
    }

    public function updateUser(int $id, array $data): array
    {
        $currentUserId = (int)(Session::get('user_id') ?? 0);

        $existing = $this->userModel->find($id);
        if ($existing === null) {
            throw new RuntimeException("User not found.", 404);
        }

        $adminRoleId = $this->getAdminRoleId();
        $isCurrentlyActiveAdmin = ((int)$existing['role_id'] === $adminRoleId && (int)$existing['is_active'] === 1);

        // Rule: Admin cannot deactivate themselves
        if ($currentUserId > 0 && $id === $currentUserId && isset($data['is_active']) && (int)$data['is_active'] === 0) {
            throw new RuntimeException("You cannot deactivate your own account.", 400);
        }

        // Rule: System must always keep at least one active admin
        $willBeInactive = isset($data['is_active']) && (int)$data['is_active'] === 0;
        $willChangeRoleAwayFromAdmin = isset($data['role_id']) && (int)$data['role_id'] !== $adminRoleId;

        if ($isCurrentlyActiveAdmin && ($willBeInactive || $willChangeRoleAwayFromAdmin)) {
            if ($this->countActiveAdmins() <= 1) {
                throw new RuntimeException("The system must always keep at least one active admin.", 400);
            }
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = trim((string)$data['name']);
        }
        if (isset($data['email'])) {
            $updateData['email'] = trim(strtolower((string)$data['email']));
        }
        if (isset($data['role_id'])) {
            $updateData['role_id'] = (int)$data['role_id'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int)$data['is_active'];
        }
        if (array_key_exists('mobile', $data)) {
            $updateData['mobile'] = $data['mobile'];
        }
        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $updateData['failed_attempts'] = 0;
            $updateData['locked_until'] = null;
        }

        if (!empty($updateData)) {
            $this->userModel->update($id, $updateData);
        }

        Logger::security("User updated by admin", [
            'target_user_id' => $id,
            'updated_by' => $currentUserId,
            'is_active' => $updateData['is_active'] ?? $existing['is_active'],
            'role_id' => $updateData['role_id'] ?? $existing['role_id'],
        ]);

        $updated = $this->userModel->find($id);
        unset($updated['password_hash']);
        return $updated;
    }

    public function deleteUser(int $id): bool
    {
        $currentUserId = (int)(Session::get('user_id') ?? 0);

        // Rule: Admin cannot delete themselves
        if ($currentUserId > 0 && $id === $currentUserId) {
            throw new RuntimeException("You cannot delete your own account.", 400);
        }

        $existing = $this->userModel->find($id);
        if ($existing === null) {
            throw new RuntimeException("User not found.", 404);
        }

        $adminRoleId = $this->getAdminRoleId();
        $isCurrentlyActiveAdmin = ((int)$existing['role_id'] === $adminRoleId && (int)$existing['is_active'] === 1);

        if ($isCurrentlyActiveAdmin && $this->countActiveAdmins() <= 1) {
            throw new RuntimeException("The system must always keep at least one active admin.", 400);
        }

        $result = $this->userModel->softDelete($id);

        Logger::security("User deleted by admin", [
            'target_user_id' => $id,
            'deleted_by' => $currentUserId,
        ]);

        return $result;
    }
}
