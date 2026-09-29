<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PermissionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ClientManagementTest extends TestCase
{
    private PDO $pdo;
    private Client $clientModel;
    private ClientDocument $docModel;
    private ActivityLog $activityLog;
    private User $userModel;
    private ClientService $clientService;
    private string $tempUploadDir;

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
                client_type TEXT DEFAULT 'individual',
                name TEXT,
                contact_person TEXT NULL,
                email TEXT UNIQUE,
                mobile TEXT UNIQUE,
                alt_mobile TEXT NULL,
                gst_no TEXT NULL,
                pan_no TEXT NULL,
                industry TEXT NULL,
                company_size TEXT NULL,
                website TEXT NULL,
                address_line1 TEXT NULL,
                address_line2 TEXT NULL,
                city TEXT NULL,
                state TEXT NULL,
                pincode TEXT NULL,
                country TEXT DEFAULT 'India',
                lead_source TEXT NULL,
                assigned_to INTEGER NULL,
                status TEXT DEFAULT 'new',
                tags TEXT NULL,
                notes TEXT NULL,
                consent_given INTEGER DEFAULT 0,
                consent_at TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE client_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER,
                original_name TEXT,
                stored_name TEXT,
                mime_type TEXT,
                size_bytes INTEGER,
                uploaded_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                entity_type TEXT,
                entity_id INTEGER,
                action TEXT,
                old_values TEXT NULL,
                new_values TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT NULL
            );
        ");

        // Seed Roles
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (1, 'admin', 'Administrator')");
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (2, 'manager', 'Manager')");
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (3, 'sales', 'Sales Rep')");

        // Seed Permissions
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (1, 'client.create', 'Create Client')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (2, 'client.view_all', 'View All')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (3, 'client.view_own', 'View Own')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (4, 'client.edit', 'Edit Client')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (5, 'client.delete', 'Delete Client')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (6, 'client.export', 'Export Clients')");

        // Assign Permissions
        // Admin: all (1, 2, 4, 5, 6)
        $this->pdo->exec("INSERT INTO role_permissions VALUES (1, 1), (1, 2), (1, 4), (1, 5), (1, 6)");
        // Manager: view_all, edit, delete, export
        $this->pdo->exec("INSERT INTO role_permissions VALUES (2, 1), (2, 2), (2, 4), (2, 5), (2, 6)");
        // Sales: view_own, create, edit, export (no delete, no view_all)
        $this->pdo->exec("INSERT INTO role_permissions VALUES (3, 1), (3, 3), (3, 4), (3, 6)");

        // Users
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (1, 1, 'Admin', 'admin@crm.local', 1)");
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (2, 2, 'Manager', 'manager@crm.local', 1)");
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (10, 3, 'Sales Alpha', 'alpha@crm.local', 1)");
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (20, 3, 'Sales Beta', 'beta@crm.local', 1)");

        $this->tempUploadDir = sys_get_temp_dir() . '/crm_mgmt_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempUploadDir, 0777, true);

        $this->clientModel = new Client($this->pdo);
        $this->docModel = new ClientDocument($this->pdo);
        $this->activityLog = new ActivityLog($this->pdo);
        $this->userModel = new User($this->pdo);

        $this->clientService = new ClientService(
            $this->clientModel,
            $this->docModel,
            $this->activityLog,
            $this->userModel,
            $this->tempUploadDir
        );

        // Seed Clients:
        // Client 1: assigned to 10 (Sales Alpha)
        $this->pdo->exec("INSERT INTO clients (id, client_code, name, email, mobile, gst_no, pan_no, city, state, lead_source, status, assigned_to, created_at)
            VALUES (1, 'CL-2026-0001', 'Alpha Corp', 'alpha@corp.in', '9811111111', '27AAAAA0000A1Z5', 'AAAAA0000A', 'Mumbai', 'Maharashtra', 'Website', 'active', 10, '2026-09-01 10:00:00')");

        // Client 2: assigned to 20 (Sales Beta)
        $this->pdo->exec("INSERT INTO clients (id, client_code, name, email, mobile, gst_no, pan_no, city, state, lead_source, status, assigned_to, created_at)
            VALUES (2, 'CL-2026-0002', 'Beta Retail', 'beta@retail.in', '9822222222', '27BBBBB0000B1Z5', 'BBBBB0000B', 'Pune', 'Maharashtra', 'Referral', 'new', 20, '2026-09-15 10:00:00')");
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempUploadDir)) {
            $files = scandir($this->tempUploadDir) ?: [];
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..') {
                    $p = $this->tempUploadDir . '/' . $f;
                    if (is_dir($p)) {
                        @rmdir($p);
                    } else {
                        @unlink($p);
                    }
                }
            }
            @rmdir($this->tempUploadDir);
        }
    }

    public function testListClientsSearchAndScoping(): void
    {
        // 1. Admin sees both clients
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        $adminList = $this->clientService->listClients();
        $this->assertSame(2, $adminList['recordsTotal']);
        $this->assertCount(2, $adminList['items']);

        // Search by GST
        $searchGst = $this->clientService->listClients(['search' => '27AAAAA0000A1Z5']);
        $this->assertSame(1, $searchGst['recordsFiltered']);
        $this->assertSame('Alpha Corp', $searchGst['items'][0]['name']);

        // Filter by status 'new'
        $filterStatus = $this->clientService->listClients(['status' => 'new']);
        $this->assertSame(1, $filterStatus['recordsFiltered']);
        $this->assertSame('Beta Retail', $filterStatus['items'][0]['name']);

        // 2. Sales Alpha (User 10) only sees assigned Client 1
        Session::set('user_id', 10);
        PermissionService::refresh();

        $salesList = $this->clientService->listClients();
        $this->assertSame(1, $salesList['recordsTotal']);
        $this->assertCount(1, $salesList['items']);
        $this->assertSame('Alpha Corp', $salesList['items'][0]['name']);

        // Sales Alpha searching for Beta Retail returns 0 matches
        $salesSearchBeta = $this->clientService->listClients(['search' => 'Beta']);
        $this->assertSame(0, $salesSearchBeta['recordsFiltered']);
    }

    public function testClientProfileDetailsAndTimeline(): void
    {
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        $profile = $this->clientService->getClientProfile(1);
        $this->assertArrayHasKey('client', $profile);
        $this->assertArrayHasKey('documents', $profile);
        $this->assertArrayHasKey('activities', $profile);
        $this->assertSame('CL-2026-0001', $profile['client']['client_code']);
        $this->assertSame('Sales Alpha', $profile['client']['assigned_to_name']);
    }

    public function testClientUpdateWithDiffLogging(): void
    {
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        $updateData = [
            'name' => 'Alpha Corporation Updated',
            'status' => 'active',
            'email' => 'alpha@corp.in', // same email should pass (ignoring self)
            'mobile' => '9811111111',
            'city' => 'Navi Mumbai',
            'state' => 'Maharashtra',
            'address_line1' => 'Plot 50, Sector 15',
            'pincode' => '400703',
        ];

        $updated = $this->clientService->updateClient(1, $updateData, 1, '127.0.0.1', 'PHPUnit Agent');
        $this->assertSame('Alpha Corporation Updated', $updated['name']);
        $this->assertSame('Navi Mumbai', $updated['city']);

        // Verify activity log captured only changed fields
        $logs = $this->activityLog->getByEntity('client', 1);
        $this->assertNotEmpty($logs);
        $latestLog = $logs[0];
        $this->assertSame('update', $latestLog['action']);

        $newVals = json_decode($latestLog['new_values'], true);
        $oldVals = json_decode($latestLog['old_values'], true);

        $this->assertArrayHasKey('name', $newVals);
        $this->assertSame('Alpha Corporation Updated', $newVals['name']);
        $this->assertSame('Alpha Corp', $oldVals['name']);
        $this->assertArrayHasKey('city', $newVals);
        $this->assertSame('Navi Mumbai', $newVals['city']);
        $this->assertSame('Mumbai', $oldVals['city']);

        // Unchanged fields like email and mobile should NOT be in diff
        $this->assertArrayNotHasKey('email', $newVals);
        $this->assertArrayNotHasKey('mobile', $newVals);
    }

    public function testClientUpdateDuplicateEmailRejection(): void
    {
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        // Try updating Client 1's email to Client 2's email ('beta@retail.in')
        $badData = [
            'name' => 'Alpha Corp',
            'email' => 'beta@retail.in', // taken by client 2
            'mobile' => '9811111111',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'address_line1' => 'Plot 50',
            'pincode' => '400001',
        ];

        $this->expectException(ValidationException::class);
        $this->clientService->updateClient(1, $badData, 1);
    }

    public function testClientSoftDelete(): void
    {
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        $deleted = $this->clientService->deleteClient(1, 1, '127.0.0.1');
        $this->assertTrue($deleted);

        // Verify client is soft-deleted
        $stmt = $this->pdo->query("SELECT deleted_at FROM clients WHERE id = 1");
        $deletedAt = $stmt->fetchColumn();
        $this->assertNotNull($deletedAt);

        // Verify excluded from list
        $list = $this->clientService->listClients();
        $this->assertSame(1, $list['recordsTotal']);
        $this->assertSame('Beta Retail', $list['items'][0]['name']);

        // Verify activity log recorded action=delete
        $logs = $this->activityLog->getByEntity('client', 1);
        $this->assertSame('delete', $logs[0]['action']);
    }

    public function testExportPanMaskingForNonAdmin(): void
    {
        Session::start();

        // 1. Manager (non-admin): PAN should be masked with only last 4 visible
        Session::set('user_id', 2);
        Session::set('role', 'manager');
        PermissionService::refresh();

        $export = $this->clientService->exportClients([], 'csv', 2);
        $this->assertFileExists($export['file_path']);

        $content = file_get_contents($export['file_path']);
        // Client 1 PAN: AAAAA0000A -> masked to ******000A
        // Client 2 PAN: BBBBB0000B -> masked to ******000B
        $this->assertStringContainsString('******000A', $content);
        $this->assertStringContainsString('******000B', $content);
        $this->assertStringNotContainsString('AAAAA0000A', $content);

        @unlink($export['file_path']);

        // 2. Admin: Full PAN should be visible
        Session::set('user_id', 1);
        Session::set('role', 'admin');
        PermissionService::refresh();

        $adminExport = $this->clientService->exportClients([], 'csv', 1);
        $adminContent = file_get_contents($adminExport['file_path']);
        $this->assertStringContainsString('AAAAA0000A', $adminContent);
        $this->assertStringContainsString('BBBBB0000B', $adminContent);

        @unlink($adminExport['file_path']);
    }
}
