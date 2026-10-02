<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use PDO;
use RuntimeException;

class RolePermissionService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Get complete matrix data (roles, categorized permissions, and role-permission map).
     */
    public function getMatrix(): array
    {
        // 1. Roles
        $rolesStmt = $this->pdo->query("SELECT `id`, `name`, `label` FROM `roles` ORDER BY `id` ASC");
        $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 2. Permissions
        $permsStmt = $this->pdo->query("SELECT `id`, `name`, `label` FROM `permissions` ORDER BY `name` ASC");
        $permissions = $permsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Group permissions by category/module
        $categorized = [];
        foreach ($permissions as $p) {
            $name = $p['name'];
            $category = $this->categorizePermission($name);
            $p['category'] = $category;
            $categorized[$category][] = $p;
        }

        // 3. Active role_permissions map
        $mapStmt = $this->pdo->query("SELECT `role_id`, `permission_id` FROM `role_permissions`");
        $matrix = [];
        while ($row = $mapStmt->fetch(PDO::FETCH_ASSOC)) {
            $rId = (int)$row['role_id'];
            $pId = (int)$row['permission_id'];
            $matrix[$rId][$pId] = true;
        }

        return [
            'roles' => $roles,
            'categories' => $categorized,
            'permissions' => $permissions,
            'matrix' => $matrix,
        ];
    }

    /**
     * Toggle a permission for a given role.
     */
    public function toggle(int $roleId, int|string $permissionIdOrName, bool $enabled): array
    {
        Session::start();
        if (!PermissionService::can('user.manage')) {
            throw new RuntimeException("Unauthorized: only administrators can modify permissions.", 403);
        }

        // Get role details
        $roleStmt = $this->pdo->prepare("SELECT `id`, `name` FROM `roles` WHERE `id` = ? LIMIT 1");
        $roleStmt->execute([$roleId]);
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            throw new RuntimeException("Role not found.", 404);
        }

        if (strtolower($role['name']) === 'admin' && !$enabled) {
            throw new RuntimeException("Cannot remove permissions from the Administrator role.", 400);
        }

        // Resolve permission ID
        $permId = null;
        if (is_numeric($permissionIdOrName)) {
            $pStmt = $this->pdo->prepare("SELECT `id`, `name` FROM `permissions` WHERE `id` = ? LIMIT 1");
            $pStmt->execute([(int)$permissionIdOrName]);
            $perm = $pStmt->fetch(PDO::FETCH_ASSOC);
            $permId = $perm ? (int)$perm['id'] : null;
        } else {
            $pStmt = $this->pdo->prepare("SELECT `id`, `name` FROM `permissions` WHERE `name` = ? LIMIT 1");
            $pStmt->execute([trim((string)$permissionIdOrName)]);
            $perm = $pStmt->fetch(PDO::FETCH_ASSOC);
            $permId = $perm ? (int)$perm['id'] : null;
        }

        if (!$permId) {
            throw new RuntimeException("Permission not found.", 404);
        }

        if ($enabled) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
            $stmt->execute([$roleId, $permId]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM `role_permissions` WHERE `role_id` = ? AND `permission_id` = ?");
            $stmt->execute([$roleId, $permId]);
        }

        // Invalidate permissions cache
        PermissionService::refresh();

        return [
            'role_id' => $roleId,
            'role_name' => $role['name'],
            'permission_id' => $permId,
            'enabled' => $enabled,
        ];
    }

    private function categorizePermission(string $perm): string
    {
        if (str_starts_with($perm, 'client.') || str_starts_with($perm, 'client_service.')) {
            return 'Clients & Compliance';
        }
        if (str_starts_with($perm, 'lead.') || str_starts_with($perm, 'lead_source.')) {
            return 'Lead Pipeline';
        }
        if (str_starts_with($perm, 'followup.')) {
            return 'Follow-ups';
        }
        if (str_starts_with($perm, 'service.')) {
            return 'Services Catalog';
        }
        if (str_starts_with($perm, 'invoice.') || str_starts_with($perm, 'payment.')) {
            return 'Billing & Fees';
        }
        if (str_starts_with($perm, 'student.') || str_starts_with($perm, 'batch.') || str_starts_with($perm, 'attendance.') || str_starts_with($perm, 'progress.')) {
            return 'Academic & Training';
        }
        if (str_starts_with($perm, 'report.')) {
            return 'Reports';
        }
        if (str_starts_with($perm, 'user.') || str_starts_with($perm, 'system.')) {
            return 'System Administration';
        }
        if (str_starts_with($perm, 'reminder.')) {
            return 'Reminders & Notifications';
        }

        return 'General';
    }
}
