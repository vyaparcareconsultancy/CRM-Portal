<?php

declare(strict_types=1);

/**
 * CRM Database Seeder
 * CLI command: php database/seeds/seed.php
 */

$projectRoot = dirname(__DIR__, 2);

// Load Composer autoloader if available
$composerAutoload = $projectRoot . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Load environment variables
if (class_exists(\Dotenv\Dotenv::class) && file_exists($projectRoot . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
} elseif (file_exists($projectRoot . '/.env')) {
    $lines = file($projectRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_NAME'] ?? 'crm_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@crm.local';
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? 'Admin@123456';

echo "Connecting to MySQL server at {$host}:{$port}/{$dbName}..." . PHP_EOL;

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // 1. Seed Roles
    echo "Seeding roles..." . PHP_EOL;
    $roles = [
        ['name' => 'admin', 'label' => 'Administrator'],
        ['name' => 'manager', 'label' => 'Manager'],
        ['name' => 'counselor', 'label' => 'Counselor'],
        ['name' => 'accountant', 'label' => 'Accountant'],
        ['name' => 'trainer', 'label' => 'Trainer'],
    ];

    $roleStmt = $pdo->prepare("
        INSERT INTO `roles` (`name`, `label`)
        VALUES (:name, :label)
        ON DUPLICATE KEY UPDATE `label` = VALUES(`label`)
    ");

    foreach ($roles as $role) {
        $roleStmt->execute($role);
    }

    // Map role names to IDs
    $roleIds = $pdo->query("SELECT `name`, `id` FROM `roles`")->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Seed Permissions
    echo "Seeding permissions..." . PHP_EOL;
    $permissions = [
        // Clients & Tax
        ['name' => 'client.create', 'label' => 'Create Client'],
        ['name' => 'client.view_all', 'label' => 'View All Clients'],
        ['name' => 'client.view_own', 'label' => 'View Assigned Clients'],
        ['name' => 'client.edit', 'label' => 'Edit Client'],
        ['name' => 'client.delete', 'label' => 'Delete Client'],
        ['name' => 'client.export', 'label' => 'Export Clients'],
        ['name' => 'client.view_tax', 'label' => 'View Client Tax Data (PAN/GST)'],

        // Leads & Sources
        ['name' => 'lead.view', 'label' => 'View Leads'],
        ['name' => 'lead.view_all', 'label' => 'View All Leads'],
        ['name' => 'lead.manage', 'label' => 'Manage Leads'],
        ['name' => 'lead.convert', 'label' => 'Convert Leads'],
        ['name' => 'lead_source.manage', 'label' => 'Manage Lead Sources'],

        // Follow-ups
        ['name' => 'followup.manage', 'label' => 'Manage Follow-ups'],

        // Services
        ['name' => 'service.manage', 'label' => 'Manage Services Catalog'],
        ['name' => 'client_service.manage', 'label' => 'Manage Client Service Subscriptions'],

        // Billing & Payments
        ['name' => 'invoice.manage', 'label' => 'Manage Invoices'],
        ['name' => 'payment.view', 'label' => 'View Payments & Fees'],
        ['name' => 'payment.record', 'label' => 'Record Payments'],
        ['name' => 'payment.manage', 'label' => 'Manage/Edit Payments'],

        // Academic & Training
        ['name' => 'student.manage', 'label' => 'Manage Students'],
        ['name' => 'student.view', 'label' => 'View All Students'],
        ['name' => 'student.view_assigned', 'label' => 'View Assigned Batch Students'],
        ['name' => 'batch.manage', 'label' => 'Manage Batches'],
        ['name' => 'batch.view_assigned', 'label' => 'View Assigned Batches'],
        ['name' => 'attendance.manage', 'label' => 'Mark Attendance'],
        ['name' => 'attendance.view', 'label' => 'View Attendance'],
        ['name' => 'progress.manage', 'label' => 'Manage Student Progress'],

        // Reports
        ['name' => 'report.view_financial', 'label' => 'View Financial Reports'],
        ['name' => 'report.view_leads', 'label' => 'View Lead Reports'],
        ['name' => 'report.view_academic', 'label' => 'View Academic & Batch Reports'],

        // Reminders & Administration
        ['name' => 'reminder.manage', 'label' => 'Manage Reminders'],
        ['name' => 'user.manage', 'label' => 'Manage Users & Permissions'],
        ['name' => 'system.settings', 'label' => 'Manage System Settings'],
    ];

    $permStmt = $pdo->prepare("
        INSERT INTO `permissions` (`name`, `label`)
        VALUES (:name, :label)
        ON DUPLICATE KEY UPDATE `label` = VALUES(`label`)
    ");

    foreach ($permissions as $permission) {
        $permStmt->execute($permission);
    }

    // Map permission names to IDs
    $permissionIds = $pdo->query("SELECT `name`, `id` FROM `permissions`")->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Seed Role-Permissions Mapping
    echo "Mapping permissions to roles..." . PHP_EOL;
    $allPermNames = array_column($permissions, 'name');

    $rolePermissionMap = [
        'admin' => $allPermNames,
        'manager' => array_values(array_diff($allPermNames, ['user.manage', 'system.settings'])),
        'counselor' => [
            'lead.view',
            'lead.manage',
            'lead.convert',
            'lead_source.manage',
            'followup.manage',
            'client.create',
            'client.view_own',
            'student.view',
            'student.manage',
            'batch.manage',
            'attendance.view',
            'report.view_leads',
        ],
        'accountant' => [
            'client.view_all',
            'client.view_own',
            'client.edit',
            'client.export',
            'client.view_tax',
            'service.manage',
            'client_service.manage',
            'invoice.manage',
            'payment.view',
            'payment.record',
            'payment.manage',
            'student.view',
            'report.view_financial',
            'reminder.manage',
            'followup.manage',
        ],
        'trainer' => [
            'batch.view_assigned',
            'student.view_assigned',
            'attendance.manage',
            'attendance.view',
            'progress.manage',
            'report.view_academic',
        ],
    ];

    $insertRolePermStmt = $pdo->prepare("
        INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
        VALUES (?, ?)
    ");

    foreach ($rolePermissionMap as $roleName => $perms) {
        if (!isset($roleIds[$roleName])) {
            continue;
        }
        $rId = (int)$roleIds[$roleName];
        foreach ($perms as $permName) {
            if (isset($permissionIds[$permName])) {
                $pId = (int)$permissionIds[$permName];
                $insertRolePermStmt->execute([$rId, $pId]);
            }
        }
    }

    // 4. Seed Default Admin User (skip if any active admin already exists)
    $adminRoleId = (int)$roleIds['admin'];

    $checkAdminStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM `users` 
        WHERE `role_id` = ? AND `is_active` = 1 AND `deleted_at` IS NULL
    ");
    $checkAdminStmt->execute([$adminRoleId]);
    $activeAdminCount = (int)$checkAdminStmt->fetchColumn();

    if ($activeAdminCount > 0) {
        echo "Active admin already exists ({$activeAdminCount} found). Skipping default admin creation." . PHP_EOL;
    } else {
        echo "No active admin found. Seeding default admin user ({$adminEmail})..." . PHP_EOL;
        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

        $findUserStmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = ?");
        $findUserStmt->execute([$adminEmail]);
        $existingAdminId = $findUserStmt->fetchColumn();

        if ($existingAdminId) {
            $updateUserStmt = $pdo->prepare("
                UPDATE `users`
                SET `role_id` = ?,
                    `password_hash` = ?,
                    `is_active` = 1,
                    `deleted_at` = NULL
                WHERE `id` = ?
            ");
            $updateUserStmt->execute([$adminRoleId, $passwordHash, $existingAdminId]);
            echo "Admin user updated successfully (ID: {$existingAdminId})." . PHP_EOL;
        } else {
            $insertUserStmt = $pdo->prepare("
                INSERT INTO `users` (`role_id`, `name`, `email`, `password_hash`, `is_active`)
                VALUES (?, ?, ?, ?, 1)
            ");
            $insertUserStmt->execute([
                $adminRoleId,
                'System Administrator',
                $adminEmail,
                $passwordHash,
            ]);
            $newAdminId = $pdo->lastInsertId();
            echo "Admin user created successfully (ID: {$newAdminId})." . PHP_EOL;
        }
    }

    echo PHP_EOL . "Seeding completed successfully!" . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . "Seeding failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
