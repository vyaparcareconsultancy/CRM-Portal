<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use PDO;

class PermissionService
{
    private const SESSION_PERM_KEY = '_user_permissions';
    private const SESSION_ROLE_KEY = '_user_role';

    public static function can(string $permission): bool
    {
        Session::start();
        $userId = Session::get('user_id');
        if (!$userId) {
            return false;
        }

        $role = self::getRole((int)$userId);
        if ($role === 'admin') {
            return true;
        }

        $permissions = self::getPermissions((int)$userId);
        return in_array($permission, $permissions, true);
    }

    public static function getPermissions(?int $userId = null): array
    {
        Session::start();
        $userId = $userId ?? (int)Session::get('user_id');
        if (!$userId) {
            return [];
        }

        // Check session cache
        $cached = Session::get(self::SESSION_PERM_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        // Load once from database
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT p.`name`
            FROM `permissions` p
            JOIN `role_permissions` rp ON p.`id` = rp.`permission_id`
            JOIN `users` u ON u.`role_id` = rp.`role_id`
            WHERE u.`id` = ? AND u.`deleted_at` IS NULL AND u.`is_active` = 1
        ");
        $stmt->execute([$userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        Session::set(self::SESSION_PERM_KEY, $permissions);
        return $permissions;
    }

    public static function getRole(?int $userId = null): ?string
    {
        Session::start();
        $userId = $userId ?? (int)Session::get('user_id');
        if (!$userId) {
            return null;
        }

        $cachedRole = Session::get(self::SESSION_ROLE_KEY);
        if ($cachedRole !== null) {
            return (string)$cachedRole;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT r.`name`
            FROM `roles` r
            JOIN `users` u ON u.`role_id` = r.`id`
            WHERE u.`id` = ? LIMIT 1
        ");
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn() ?: null;

        if ($role) {
            Session::set(self::SESSION_ROLE_KEY, $role);
        }

        return $role ? (string)$role : null;
    }

    public static function refresh(): void
    {
        Session::start();
        Session::remove(self::SESSION_PERM_KEY);
        Session::remove(self::SESSION_ROLE_KEY);
    }

    public static function loadUserPermissions(?int $userId = null, mixed $extra = null): array
    {
        self::refresh();
        return self::getPermissions($userId);
    }
}

// Global view helper function for template permission checks
if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return \App\Services\PermissionService::can($permission);
    }
}
