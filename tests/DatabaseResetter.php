<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use PDO;
use Throwable;

class DatabaseResetter
{
    private static bool $hasReset = false;

    /**
     * Load environment variables from .env.testing and reset test database.
     */
    public static function reset(bool $force = false): ?PDO
    {
        if (self::$hasReset && !$force) {
            try {
                $conn = Database::getConnection();
                if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    return $conn;
                }
            } catch (Throwable) {
                // re-reset if connection was swapped
            }
        }

        $projectRoot = dirname(__DIR__);
        self::loadTestingEnv($projectRoot);

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? 'crm_test_db';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        try {
            // 1. Connect without selecting database to allow DROP/CREATE
            $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
            $pdo = new PDO($serverDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // 2. Drop and recreate isolated test database
            $safeDb = str_replace('`', '``', $dbName);
            $pdo->exec("DROP DATABASE IF EXISTS `{$safeDb}`");
            $pdo->exec("CREATE DATABASE `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$safeDb}`");

            // 3. Run all migrations in database/migrations/*.sql
            $migrationsDir = $projectRoot . '/database/migrations';
            $migrationFiles = glob($migrationsDir . '/*.sql') ?: [];
            sort($migrationFiles);

            foreach ($migrationFiles as $file) {
                $sql = file_get_contents($file);
                if ($sql !== false && trim($sql) !== '') {
                    $pdo->exec($sql);
                }
            }

            // 4. Configure App\Core\Database singleton to use the test connection
            Database::setConnection($pdo);

            // 5. Seed default roles, permissions, and test accounts
            self::seedInitialData($pdo);

            self::$hasReset = true;
            return $pdo;
        } catch (Throwable $e) {
            // If MySQL is not reachable, fall back to SQLite in-memory
            echo "[Notice] MySQL test database connection failed: " . $e->getMessage() . ". Using SQLite in-memory fallback.\n";
            $sqlite = new PDO('sqlite::memory:');
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            Database::setConnection($sqlite);
            self::$hasReset = true;
            return $sqlite;
        }
    }

    private static function loadTestingEnv(string $projectRoot): void
    {
        $testEnvFile = $projectRoot . '/.env.testing';
        if (!file_exists($testEnvFile)) {
            return;
        }

        $lines = file($testEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    private static function seedInitialData(PDO $pdo): void
    {
        // Seed roles
        $roles = [
            ['name' => 'admin', 'label' => 'Administrator'],
            ['name' => 'manager', 'label' => 'Manager'],
            ['name' => 'counselor', 'label' => 'Counselor'],
            ['name' => 'accountant', 'label' => 'Accountant'],
            ['name' => 'trainer', 'label' => 'Trainer'],
            ['name' => 'sales', 'label' => 'Sales Representative'],
        ];
        $roleStmt = $pdo->prepare("INSERT IGNORE INTO `roles` (`name`, `label`) VALUES (?, ?)");
        foreach ($roles as $r) {
            $roleStmt->execute([$r['name'], $r['label']]);
        }

        // Seed permissions
        $permissions = [
            'client.view_all',
            'client.view_own',
            'client.create',
            'client.edit',
            'client.delete',
            'client.export',
            'user.manage',
            'followup.manage',
        ];
        $permStmt = $pdo->prepare("INSERT IGNORE INTO `permissions` (`name`, `label`) VALUES (?, ?)");
        foreach ($permissions as $p) {
            $permStmt->execute([$p, ucfirst(str_replace(['.', '_'], ' ', $p))]);
        }

        // Assign role permissions
        $map = [
            'admin' => $permissions,
            'manager' => ['client.view_all', 'client.create', 'client.edit', 'client.export', 'followup.manage'],
            'sales' => ['client.view_own', 'client.create', 'client.edit', 'followup.manage'],
        ];

        $roleRows = $pdo->query("SELECT id, name FROM `roles`")->fetchAll();
        $permRows = $pdo->query("SELECT id, name FROM `permissions`")->fetchAll();
        $roleIds = array_column($roleRows, 'id', 'name');
        $permIds = array_column($permRows, 'id', 'name');

        $rpStmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
        foreach ($map as $roleName => $perms) {
            $rid = $roleIds[$roleName] ?? null;
            if (!$rid) {
                continue;
            }
            foreach ($perms as $pName) {
                if (isset($permIds[$pName])) {
                    $rpStmt->execute([$rid, $permIds[$pName]]);
                }
            }
        }

        // Seed test users: Admin, Manager, Counselor, Accountant, Trainer
        $userStmt = $pdo->prepare("INSERT IGNORE INTO `users` (`role_id`, `name`, `email`, `password_hash`, `is_active`) VALUES (?, ?, ?, ?, 1)");
        $passwordHash = password_hash('Admin@123456', PASSWORD_BCRYPT);
        $userStmt->execute([$roleIds['admin'], 'Admin User', 'admin@crm.local', $passwordHash]);
        $userStmt->execute([$roleIds['manager'], 'Manager User', 'manager@crm.local', $passwordHash]);
        $counselorRoleId = $roleIds['counselor'] ?? null;
        if ($counselorRoleId) {
            $userStmt->execute([$counselorRoleId, 'Counselor User', 'counselor@crm.local', $passwordHash]);
        }
        $accountantRoleId = $roleIds['accountant'] ?? null;
        if ($accountantRoleId) {
            $userStmt->execute([$accountantRoleId, 'Accountant User', 'accountant@crm.local', $passwordHash]);
        }
        $trainerRoleId = $roleIds['trainer'] ?? null;
        if ($trainerRoleId) {
            $userStmt->execute([$trainerRoleId, 'Trainer User', 'trainer@crm.local', $passwordHash]);
        }
    }
}
