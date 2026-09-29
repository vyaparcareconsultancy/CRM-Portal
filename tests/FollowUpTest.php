<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\PermissionService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FollowUpTest extends TestCase
{
    private PDO $pdo;
    private FollowUp $followUpModel;
    private Client $clientModel;
    private ActivityLog $activityLog;
    private FollowUpService $service;

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
            CREATE TABLE follow_ups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                due_at TEXT NOT NULL,
                type TEXT NOT NULL,
                notes TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
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

        // Seed roles & permissions
        $this->pdo->exec("
            INSERT INTO roles (id, name, label) VALUES
            (1, 'admin', 'Administrator'),
            (2, 'manager', 'Manager'),
            (3, 'sales', 'Sales Rep');

            INSERT INTO permissions (id, name, label) VALUES
            (1, 'client.view_all', 'View All Clients'),
            (2, 'client.view_own', 'View Own Clients'),
            (3, 'client.create', 'Create Client'),
            (4, 'client.edit', 'Edit Client'),
            (5, 'client.delete', 'Delete Client'),
            (6, 'followup.manage', 'Manage Follow-ups');

            INSERT INTO role_permissions (role_id, permission_id) VALUES
            (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6),
            (2, 1), (2, 3), (2, 4), (2, 5), (2, 6),
            (3, 2), (3, 3), (3, 4), (3, 6);

            INSERT INTO users (id, role_id, name, email, password_hash, is_active) VALUES
            (1, 1, 'Admin', 'admin@crm.local', 'hash', 1),
            (2, 3, 'Alice Sales', 'alice@crm.local', 'hash', 1),
            (3, 3, 'Bob Sales', 'bob@crm.local', 'hash', 1);

            INSERT INTO clients (id, client_code, name, email, mobile, assigned_to, status, created_at) VALUES
            (1, 'CL-2026-0001', 'Acme Corp', 'contact@acme.com', '9876543210', 2, 'active', '2026-01-01 00:00:00'),
            (2, 'CL-2026-0002', 'Beta LLC', 'info@beta.com', '9876543211', 3, 'active', '2026-01-02 00:00:00');
        ");

        $this->followUpModel = new FollowUp($this->pdo);
        $this->clientModel = new Client($this->pdo);
        $this->activityLog = new ActivityLog($this->pdo);
        $this->service = new FollowUpService($this->followUpModel, $this->clientModel, $this->activityLog);
    }

    private function authenticate(int $userId, int $roleId): void
    {
        Session::start();
        Session::set('user_id', $userId);
        Session::set('role_id', $roleId);
        PermissionService::loadUserPermissions($userId, $roleId);
    }

    public function testAdminCanCreateAndListFollowUps(): void
    {
        $this->authenticate(1, 1);

        $dueAt = date('Y-m-d 14:00:00');
        $fu = $this->service->create([
            'client_id' => 1,
            'due_at' => $dueAt,
            'type' => 'call',
            'notes' => 'Q3 Check-in call',
        ]);

        $this->assertGreaterThan(0, $fu['id']);
        $this->assertSame('call', $fu['type']);
        $this->assertSame('pending', $fu['status']);

        $list = $this->service->list(['tab' => 'all']);
        $this->assertSame(1, $list['total']);
    }

    public function testSalesUserCannotAccessOtherUsersClientFollowUps(): void
    {
        // 1. Create FU for Client 1 (assigned to Alice, user 2)
        $this->authenticate(1, 1);
        $fu = $this->service->create([
            'client_id' => 1,
            'due_at' => date('Y-m-d 10:00:00'),
            'type' => 'meeting',
            'notes' => 'Sales demo',
        ]);

        // 2. Alice (user 2) can see it
        $this->authenticate(2, 3);
        $aliceFUs = $this->service->getByClient(1);
        $this->assertCount(1, $aliceFUs);

        // 3. Bob (user 3) cannot see it
        $this->authenticate(3, 3);
        $bobList = $this->service->list(['tab' => 'all']);
        $this->assertCount(0, $bobList['items']);

        $this->expectException(RuntimeException::class);
        $this->service->getByClient(1);
    }

    public function testMarkFollowUpDoneWithOutcomeNote(): void
    {
        $this->authenticate(2, 3); // Alice

        $fu = $this->service->create([
            'client_id' => 1,
            'due_at' => date('Y-m-d 15:00:00'),
            'type' => 'call',
            'notes' => 'Follow up on quote',
        ]);

        $updated = $this->service->update($fu['id'], [
            'outcome' => 'Quote accepted, requested draft contract.',
        ]);

        $this->assertSame('done', $updated['status']);
        $this->assertStringContainsString('[Outcome]: Quote accepted', $updated['notes']);

        // Verify activity log
        $activities = $this->activityLog->getForClient(1);
        $actions = array_column($activities, 'action');
        $this->assertContains('followup_done', $actions);
    }

    public function testCronMarkMissedPastDue(): void
    {
        $pastDate = date('Y-m-d H:i:s', strtotime('-2 hours'));
        $this->pdo->exec("
            INSERT INTO follow_ups (client_id, user_id, due_at, type, notes, status, created_at, updated_at)
            VALUES (1, 2, '{$pastDate}', 'email', 'Pending reminder', 'pending', datetime('now'), datetime('now'));
        ");
        $id = (int)$this->pdo->lastInsertId();

        $affected = $this->followUpModel->markMissedPastDue();
        $this->assertGreaterThanOrEqual(1, $affected);

        $record = $this->followUpModel->find($id);
        $this->assertSame('missed', $record['status']);
    }

    public function testSoftDeleteFollowUp(): void
    {
        $this->authenticate(2, 3);

        $fu = $this->service->create([
            'client_id' => 1,
            'due_at' => date('Y-m-d 11:00:00'),
            'type' => 'call',
        ]);

        $this->assertTrue($this->service->delete($fu['id']));
        $this->assertNull($this->followUpModel->find($fu['id']));
    }
}
