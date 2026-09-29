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

    public function createUser(array $data): array
    {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $userId = $this->userModel->insert([
            'role_id' => (int)$data['role_id'],
            'name' => trim($data['name']),
            'email' => trim(strtolower($data['email'])),
            'mobile' => $data['mobile'] ?? null,
            'password_hash' => $hash,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);

        Logger::security("User created by admin", [
            'created_user_id' => $userId,
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'admin_id' => Session::get('user_id'),
        ]);

        $created = $this->userModel->find($userId);
        unset($created['password_hash']);
        return $created;
    }

    public function updateUser(int $id, array $data): array
    {
        $currentUserId = (int)Session::get('user_id');

        // Rule: Admin cannot deactivate themselves
        if ($id === $currentUserId && isset($data['is_active']) && (int)$data['is_active'] === 0) {
            throw new RuntimeException("You cannot deactivate your own account.", 400);
        }

        $existing = $this->userModel->find($id);
        if ($existing === null) {
            throw new RuntimeException("User not found.", 404);
        }

        $updateData = [
            'name' => trim($data['name']),
            'email' => trim(strtolower($data['email'])),
            'role_id' => (int)$data['role_id'],
        ];

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int)$data['is_active'];
        }

        if (isset($data['mobile'])) {
            $updateData['mobile'] = $data['mobile'];
        }

        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $updateData['failed_attempts'] = 0;
            $updateData['locked_until'] = null;
        }

        $this->userModel->update($id, $updateData);

        Logger::security("User updated by admin", [
            'target_user_id' => $id,
            'updated_by' => $currentUserId,
            'is_active' => $updateData['is_active'] ?? $existing['is_active'],
            'role_id' => $updateData['role_id'],
        ]);

        $updated = $this->userModel->find($id);
        unset($updated['password_hash']);
        return $updated;
    }
}
