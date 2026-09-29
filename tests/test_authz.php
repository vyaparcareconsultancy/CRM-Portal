<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Core/Logger.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Models/BaseModel.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/Client.php';
require_once dirname(__DIR__) . '/app/Services/PermissionService.php';
require_once dirname(__DIR__) . '/app/Services/ClientService.php';
require_once dirname(__DIR__) . '/app/Services/UserService.php';

use App\Core\Database;
use App\Core\Session;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PermissionService;
use App\Services\UserService;

function rbacAssert(bool $condition, string $msg): void
{
    if (!$condition) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running RBAC & Data Scoping Tests (Standalone)...\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

Database::setConnection($pdo);

$pdo->exec("
    CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT, label TEXT);
    CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT, label TEXT);
    CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER);
    CREATE TABLE users (
        id INTEGER PRIMARY KEY, role_id INTEGER, name TEXT, email TEXT,
        password_hash TEXT, is_active INTEGER DEFAULT 1, failed_attempts INTEGER DEFAULT 0,
        locked_until TEXT NULL, last_login_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL, deleted_at TEXT NULL
    );
    CREATE TABLE clients (
        id INTEGER PRIMARY KEY, client_code TEXT, name TEXT, email TEXT,
        mobile TEXT, assigned_to INTEGER, status TEXT DEFAULT 'new',
        created_at TEXT NULL, updated_at TEXT NULL, deleted_at TEXT NULL
    );
");

// Roles
$pdo->exec("INSERT INTO roles VALUES (1, 'admin', 'Admin'), (2, 'manager', 'Manager'), (3, 'sales', 'Sales')");
// Permissions
$pdo->exec("INSERT INTO permissions VALUES (1, 'client.view_all', 'View All'), (2, 'client.view_own', 'View Own'), (3, 'client.edit', 'Edit'), (4, 'user.manage', 'User Manage')");
// Role Perms
$pdo->exec("INSERT INTO role_permissions VALUES (1, 1), (1, 2), (1, 3), (1, 4), (2, 1), (2, 3), (3, 2), (3, 3)");

// Users: 1=Admin, 2=Manager, 10=SalesAlpha, 20=SalesBeta
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (1, 1, 'Admin', 'admin@crm.local', 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (2, 2, 'Manager', 'manager@crm.local', 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (10, 3, 'Sales Alpha', 'alpha@crm.local', 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (20, 3, 'Sales Beta', 'beta@crm.local', 1)");

// Clients: 101 assigned to 10, 202 assigned to 20
$pdo->exec("INSERT INTO clients (id, client_code, name, email, mobile, assigned_to) VALUES (101, 'C101', 'Alpha Client', 'a@c.com', '111', 10)");
$pdo->exec("INSERT INTO clients (id, client_code, name, email, mobile, assigned_to) VALUES (202, 'C202', 'Beta Client', 'b@c.com', '222', 20)");

$clientModel = new Client($pdo);
$clientService = new ClientService($clientModel);
$userService = new UserService(new User($pdo));

// 1. Sales Alpha (User 10)
Session::start();
Session::set('user_id', 10);
PermissionService::refresh();

rbacAssert(PermissionService::can('client.view_own') && !PermissionService::can('client.view_all'), 'Sales has view_own and NOT view_all');

$readOwn = $clientService->getClient(101);
rbacAssert($readOwn !== null && $readOwn['name'] === 'Alpha Client', 'Sales can read assigned client (101)');

$readOther = $clientService->getClient(202);
rbacAssert($readOther === null, 'Sales CANNOT read client assigned to another rep (202)');

$updateOther = $clientService->updateClient(202, ['name' => 'Compromised']);
rbacAssert($updateOther === false, 'Sales CANNOT update client assigned to another rep (202)');

$listSales = $clientService->listClients();
rbacAssert($listSales['pagination']['total'] === 1 && $listSales['items'][0]['id'] === 101, 'Sales client listing returns only assigned clients');

// 2. Manager (User 2)
Session::set('user_id', 2);
PermissionService::refresh();

rbacAssert(PermissionService::can('client.view_all'), 'Manager has view_all');
$readManagerA = $clientService->getClient(101);
$readManagerB = $clientService->getClient(202);
rbacAssert($readManagerA !== null && $readManagerB !== null, 'Manager can view all clients regardless of assignment');

$listManager = $clientService->listClients();
rbacAssert($listManager['pagination']['total'] === 2, 'Manager client listing returns all clients');

// 3. Admin cannot deactivate self
Session::set('user_id', 1);
PermissionService::refresh();

$deactivated = false;
try {
    $userService->updateUser(1, ['name' => 'Admin', 'email' => 'admin@crm.local', 'role_id' => 1, 'is_active' => 0]);
} catch (\Throwable $e) {
    if (str_contains($e->getMessage(), 'cannot deactivate your own account')) {
        $deactivated = true;
    }
}
rbacAssert($deactivated, 'Admin is blocked from deactivating their own account');

echo "\nAll RBAC and data scoping checks passed successfully!\n";
