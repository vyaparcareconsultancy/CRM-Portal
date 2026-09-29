<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PermissionService;
use App\Services\UserService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthorizationTest extends TestCase
{
    private PDO $pdo;
    private Client $clientModel;
    private ClientService $clientService;
    private UserService $userService;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        Database::setConnection($this->pdo);

        $this->pdo->exec("
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                label TEXT
            );
            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                label TEXT
            );
            CREATE TABLE role_permissions (
                role_id INTEGER,
                permission_id INTEGER,
                PRIMARY KEY (role_id, permission_id)
            );
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INTEGER,
                name TEXT,
                email TEXT UNIQUE,
                password_hash TEXT,
                is_active INTEGER DEFAULT 1,
                failed_attempts INTEGER DEFAULT 0,
                locked_until TEXT NULL,
                last_login_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_code TEXT UNIQUE,
                name TEXT,
                email TEXT UNIQUE,
                mobile TEXT UNIQUE,
                assigned_to INTEGER,
                status TEXT DEFAULT 'new',
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
        ");

        // Seed Roles
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (1, 'admin', 'Administrator')");
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (2, 'manager', 'Manager')");
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (3, 'sales', 'Sales Rep')");

        // Seed Permissions
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (1, 'client.view_all', 'View All')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (2, 'client.view_own', 'View Own')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (3, 'client.edit', 'Edit Client')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (4, 'user.manage', 'Manage Users')");

        // Map Permissions
        // Admin: all (1, 2, 3, 4)
        $this->pdo->exec("INSERT INTO role_permissions VALUES (1, 1), (1, 2), (1, 3), (1, 4)");
        // Manager: view_all (1), edit (3)
        $this->pdo->exec("INSERT INTO role_permissions VALUES (2, 1), (2, 3)");
        // Sales: view_own (2), edit (3)
        $this->pdo->exec("INSERT INTO role_permissions VALUES (3, 2), (3, 3)");

        // Seed Users
        // User 1: Admin
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (1, 1, 'Admin', 'admin@crm.local', 1)");
        // User 2: Manager
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (2, 2, 'Manager', 'manager@crm.local', 1)");
        // User 10: Sales Rep Alpha
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (10, 3, 'Sales Alpha', 'alpha@crm.local', 1)");
        // User 20: Sales Rep Beta
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (20, 3, 'Sales Beta', 'beta@crm.local', 1)");

        // Seed Clients
        // Client 101: Assigned to Sales Alpha (User 10)
        $this->pdo->exec("INSERT INTO clients (id, client_code, name, email, mobile, assigned_to) VALUES (101, 'CL-001', 'Alpha Corp', 'alpha@client.local', '9990001111', 10)");
        // Client 202: Assigned to Sales Beta (User 20)
        $this->pdo->exec("INSERT INTO clients (id, client_code, name, email, mobile, assigned_to) VALUES (202, 'CL-002', 'Beta LLC', 'beta@client.local', '9990002222', 20)");

        $this->clientModel = new Client($this->pdo);
        $this->clientService = new ClientService($this->clientModel);
        $this->userService = new UserService(new User($this->pdo));

        $_SESSION = [];
    }

    public function testSalesUserCannotReadAnotherUsersClient(): void
    {
        // 1. Authenticate as Sales Alpha (User 10)
        Session::start();
        Session::set('user_id', 10);
        PermissionService::refresh();

        // Verify Sales Alpha permissions
        $this->assertTrue(PermissionService::can('client.view_own'));
        $this->assertFalse(PermissionService::can('client.view_all'));

        // Sales Alpha reads Client 101 (their own client) -> ALLOWED
        $ownClient = $this->clientService->getClient(101);
        $this->assertNotNull($ownClient);
        $this->assertSame('Alpha Corp', $ownClient['name']);
        $this->assertSame(10, (int)$ownClient['assigned_to']);

        // Sales Alpha attempts to read Client 202 (Sales Beta's client) -> MUST BE NULL / DENIED
        $anotherClient = $this->clientService->getClient(202);
        $this->assertNull($anotherClient, 'Sales user must not be able to read client assigned to another user');

        // Sales Alpha lists clients -> ONLY their own client (101) is returned
        $list = $this->clientService->listClients();
        $this->assertSame(1, $list['pagination']['total']);
        $this->assertCount(1, $list['items']);
        $this->assertSame(101, (int)$list['items'][0]['id']);
    }

    public function testSalesUserCannotUpdateAnotherUsersClient(): void
    {
        // Authenticate as Sales Alpha (User 10)
        Session::start();
        Session::set('user_id', 10);
        PermissionService::refresh();

        // Attempt to update Beta's client (202) -> DENIED (returns false)
        $updateForbidden = $this->clientService->updateClient(202, ['name' => 'Hacked Beta']);
        $this->assertFalse($updateForbidden);

        // Verify name was not modified in database
        $check = $this->pdo->query("SELECT name FROM clients WHERE id = 202")->fetchColumn();
        $this->assertSame('Beta LLC', $check);

        // Update own client (101) -> SUCCESS
        $updateAllowed = $this->clientService->updateClient(101, ['name' => 'Alpha Corp Updated']);
        $this->assertTrue($updateAllowed);

        $checkOwn = $this->pdo->query("SELECT name FROM clients WHERE id = 101")->fetchColumn();
        $this->assertSame('Alpha Corp Updated', $checkOwn);
    }

    public function testManagerCanViewAllClients(): void
    {
        // Authenticate as Manager (User 2)
        Session::start();
        Session::set('user_id', 2);
        PermissionService::refresh();

        $this->assertTrue(PermissionService::can('client.view_all'));

        // Manager can read both Alpha's and Beta's client
        $clientA = $this->clientService->getClient(101);
        $clientB = $this->clientService->getClient(202);

        $this->assertNotNull($clientA);
        $this->assertNotNull($clientB);

        // Manager list includes both clients
        $list = $this->clientService->listClients();
        $this->assertSame(2, $list['pagination']['total']);
        $this->assertCount(2, $list['items']);
    }

    public function testAdminCannotDeactivateThemselves(): void
    {
        // Authenticate as Admin (User 1)
        Session::start();
        Session::set('user_id', 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot deactivate your own account.');

        $this->userService->updateUser(1, [
            'name' => 'Admin Updated',
            'email' => 'admin@crm.local',
            'role_id' => 1,
            'is_active' => 0,
        ]);
    }
}
