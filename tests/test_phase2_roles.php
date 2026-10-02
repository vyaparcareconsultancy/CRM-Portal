<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $trimmed, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

use App\Core\Database;
use App\Core\Session;
use App\Services\ClientService;
use App\Services\PaymentService;
use App\Services\PermissionService;
use App\Services\RolePermissionService;

Session::start();

function assertCheck(bool $condition, string $message): void
{
    if ($condition) {
        echo "PASS: {$message}\n";
    } else {
        echo "FAIL: {$message}\n";
        exit(1);
    }
}

echo "========================================\n";
echo "Testing Phase 2: Roles & Permissions Matrix\n";
echo "========================================\n\n";

$pdo = Database::getConnection();

// 1. Verify Standard Roles Exist
$roles = $pdo->query("SELECT name FROM roles")->fetchAll(PDO::FETCH_COLUMN);
assertCheck(in_array('admin', $roles, true), "Role 'admin' exists");
assertCheck(in_array('manager', $roles, true), "Role 'manager' exists");
assertCheck(in_array('counselor', $roles, true), "Role 'counselor' exists");
assertCheck(in_array('accountant', $roles, true), "Role 'accountant' exists");
assertCheck(in_array('trainer', $roles, true), "Role 'trainer' exists");

// 2. Verify legacy 'sales' users migrated to 'counselor'
$salesUsersCount = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name = 'sales' AND u.deleted_at IS NULL
")->fetchColumn();
assertCheck($salesUsersCount === 0, "No active users retain legacy 'sales' role (all migrated to counselor)");

// Ensure test users exist for all roles
$roleMap = $pdo->query("SELECT name, id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
$passwordHash = password_hash('Pass123456!', PASSWORD_DEFAULT);

$users = [
    'admin' => ['name' => 'Admin User', 'email' => 'admin_test@crm.local'],
    'manager' => ['name' => 'Manager User', 'email' => 'manager_test@crm.local'],
    'counselor' => ['name' => 'Counselor Test', 'email' => 'counselor_test@crm.local'],
    'accountant' => ['name' => 'Accountant Test', 'email' => 'accountant_test@crm.local'],
    'trainer' => ['name' => 'Trainer Test', 'email' => 'trainer_test@crm.local'],
];

$userIds = [];
$userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$insertStmt = $pdo->prepare("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)");

foreach ($users as $roleName => $data) {
    $userStmt->execute([$data['email']]);
    $existingId = $userStmt->fetchColumn();
    if ($existingId) {
        $userIds[$roleName] = (int)$existingId;
    } else {
        $insertStmt->execute([$roleMap[$roleName], $data['name'], $data['email'], $passwordHash]);
        $userIds[$roleName] = (int)$pdo->lastInsertId();
    }
}

function setSession(int $id, string $role, int $roleId): void {
    Session::set('user_id', $id);
    Session::set('user_role', $role);
    Session::set('role_id', $roleId);
    PermissionService::refresh();
}

$rolePermService = new RolePermissionService($pdo);
$clientService = new ClientService();
$paymentService = new PaymentService();

// 3. Test Admin Permissions (Has all)
setSession($userIds['admin'], 'admin', (int)$roleMap['admin']);
assertCheck(PermissionService::can('user.manage'), "Admin has 'user.manage'");
assertCheck(PermissionService::can('payment.manage'), "Admin has 'payment.manage'");
assertCheck(PermissionService::can('client.view_tax'), "Admin has 'client.view_tax'");

// 4. Test Manager Permissions (All except user/settings management)
setSession($userIds['manager'], 'manager', (int)$roleMap['manager']);
assertCheck(!PermissionService::can('user.manage'), "Manager does NOT have 'user.manage'");
assertCheck(!PermissionService::can('system.settings'), "Manager does NOT have 'system.settings'");
assertCheck(PermissionService::can('lead.view_all'), "Manager has 'lead.view_all'");
assertCheck(PermissionService::can('client.view_all'), "Manager has 'client.view_all'");
assertCheck(PermissionService::can('payment.view'), "Manager has 'payment.view'");

// 5. Test Counselor Permissions (Leads, follow-ups, own clients, NO payments edit)
setSession($userIds['counselor'], 'counselor', (int)$roleMap['counselor']);
assertCheck(PermissionService::can('lead.view'), "Counselor has 'lead.view'");
assertCheck(PermissionService::can('lead.manage'), "Counselor has 'lead.manage'");
assertCheck(PermissionService::can('followup.manage'), "Counselor has 'followup.manage'");
assertCheck(PermissionService::can('client.view_own'), "Counselor has 'client.view_own'");
assertCheck(!PermissionService::can('payment.record'), "Counselor does NOT have 'payment.record'");
assertCheck(!PermissionService::can('payment.manage'), "Counselor does NOT have 'payment.manage'");
assertCheck(!PermissionService::can('invoice.manage'), "Counselor does NOT have 'invoice.manage'");

// 6. Test Accountant Permissions (Clients, services, payments, reports; NO user management)
setSession($userIds['accountant'], 'accountant', (int)$roleMap['accountant']);
assertCheck(PermissionService::can('client.view_all'), "Accountant has 'client.view_all'");
assertCheck(PermissionService::can('client.view_tax'), "Accountant has 'client.view_tax'");
assertCheck(PermissionService::can('payment.view'), "Accountant has 'payment.view'");
assertCheck(PermissionService::can('payment.record'), "Accountant has 'payment.record'");
assertCheck(PermissionService::can('invoice.manage'), "Accountant has 'invoice.manage'");
assertCheck(PermissionService::can('report.view_financial'), "Accountant has 'report.view_financial'");
assertCheck(!PermissionService::can('user.manage'), "Accountant does NOT have 'user.manage'");

// 7. CRITICAL TEST: Prove Trainer CANNOT see client data or client tax data
setSession($userIds['trainer'], 'trainer', (int)$roleMap['trainer']);
assertCheck(!PermissionService::can('client.view_all'), "Trainer does NOT have 'client.view_all'");
assertCheck(!PermissionService::can('client.view_own'), "Trainer does NOT have 'client.view_own'");
assertCheck(!PermissionService::can('client.view_tax'), "Trainer does NOT have 'client.view_tax'");
assertCheck(!PermissionService::can('client.create'), "Trainer does NOT have 'client.create'");

// Backend service layer check: ClientService throws 403 for Trainer
$clientBlocked = false;
try {
    $clientService->listClients([]);
} catch (RuntimeException $e) {
    if ($e->getCode() === 403) {
        $clientBlocked = true;
    }
}
assertCheck($clientBlocked, "Backend ClientService::listClients() rejects Trainer with HTTP 403");

// 8. CRITICAL TEST: Prove Trainer CANNOT see or record payments
assertCheck(!PermissionService::can('payment.view'), "Trainer does NOT have 'payment.view'");
assertCheck(!PermissionService::can('payment.record'), "Trainer does NOT have 'payment.record'");
assertCheck(!PermissionService::can('payment.manage'), "Trainer does NOT have 'payment.manage'");

$paymentBlocked = false;
try {
    $paymentService->listPayments();
} catch (RuntimeException $e) {
    if ($e->getCode() === 403) {
        $paymentBlocked = true;
    }
}
assertCheck($paymentBlocked, "Backend PaymentService::listPayments() rejects Trainer with HTTP 403");

// 9. Prove Trainer CAN access assigned academic functions
assertCheck(PermissionService::can('batch.view_assigned'), "Trainer has 'batch.view_assigned'");
assertCheck(PermissionService::can('student.view_assigned'), "Trainer has 'student.view_assigned'");
assertCheck(PermissionService::can('attendance.manage'), "Trainer has 'attendance.manage'");
assertCheck(PermissionService::can('attendance.view'), "Trainer has 'attendance.view'");
assertCheck(PermissionService::can('progress.manage'), "Trainer has 'progress.manage'");
assertCheck(PermissionService::can('report.view_academic'), "Trainer has 'report.view_academic'");

// 10. Test Permission Matrix Toggle via RolePermissionService
setSession($userIds['admin'], 'admin', (int)$roleMap['admin']);
$matrix = $rolePermService->getMatrix();
assertCheck(isset($matrix['roles']) && count($matrix['roles']) >= 5, "Matrix returns all 5 roles");
assertCheck(isset($matrix['categories']) && count($matrix['categories']) > 0, "Matrix categorizes permissions");

// Toggle attendance.manage off for counselor
$attendancePermId = (int)$pdo->query("SELECT id FROM permissions WHERE name = 'attendance.view'")->fetchColumn();
$counselorRoleId = (int)$roleMap['counselor'];

$resToggleOff = $rolePermService->toggle($counselorRoleId, $attendancePermId, false);
assertCheck($resToggleOff['enabled'] === false, "Admin toggled permission OFF for role");

$resToggleOn = $rolePermService->toggle($counselorRoleId, $attendancePermId, true);
assertCheck($resToggleOn['enabled'] === true, "Admin toggled permission ON for role");

// Test that Admin role is protected from being stripped of user.manage
$adminRoleId = (int)$roleMap['admin'];
$userManagePermId = (int)$pdo->query("SELECT id FROM permissions WHERE name = 'user.manage'")->fetchColumn();
$adminProtected = false;
try {
    $rolePermService->toggle($adminRoleId, $userManagePermId, false);
} catch (RuntimeException $e) {
    $adminProtected = true;
}
assertCheck($adminProtected, "Permission matrix forbids disabling permissions for Admin role");

// Test that non-admin cannot toggle permissions
setSession($userIds['trainer'], 'trainer', (int)$roleMap['trainer']);
$nonAdminBlocked = false;
try {
    $rolePermService->toggle($counselorRoleId, $attendancePermId, false);
} catch (RuntimeException $e) {
    if ($e->getCode() === 403) {
        $nonAdminBlocked = true;
    }
}
assertCheck($nonAdminBlocked, "Non-admin user rejected with HTTP 403 when attempting to toggle matrix");

echo "\n========================================\n";
echo "ALL PHASE 2 INTEGRATION TESTS PASSED!\n";
echo "========================================\n";
