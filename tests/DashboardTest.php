<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\PermissionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class DashboardTest extends TestCase
{
    private PDO $pdo;
    private Client $clientModel;
    private FollowUp $followUpModel;
    private DashboardService $service;

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
        ");

        $this->pdo->exec("
            INSERT INTO roles (id, name, label) VALUES
            (1, 'admin', 'Administrator'),
            (2, 'manager', 'Manager'),
            (3, 'sales', 'Sales Rep');

            INSERT INTO permissions (id, name, label) VALUES
            (1, 'client.view_all', 'View All Clients'),
            (2, 'client.view_own', 'View Own Clients');

            INSERT INTO role_permissions (role_id, permission_id) VALUES
            (1, 1), (1, 2),
            (2, 1),
            (3, 2);

            INSERT INTO users (id, role_id, name, email, password_hash, is_active) VALUES
            (1, 1, 'Admin', 'admin@crm.local', 'hash', 1),
            (2, 3, 'Alice Sales', 'alice@crm.local', 'hash', 1),
            (3, 3, 'Bob Sales', 'bob@crm.local', 'hash', 1);
        ");

        $now = date('Y-m-d H:i:s');
        $thisMonth = date('Y-m-d 11:00:00');

        $this->pdo->exec("
            INSERT INTO clients (id, client_code, name, email, mobile, assigned_to, status, lead_source, created_at) VALUES
            (1, 'CL-2026-0001', 'Alpha Corp', 'a@test.com', '9876543210', 2, 'active', 'Website', '{$thisMonth}'),
            (2, 'CL-2026-0002', 'Beta LLC', 'b@test.com', '9876543211', 3, 'new', 'Referral', '{$thisMonth}');
        ");

        $this->pdo->exec("
            INSERT INTO follow_ups (id, client_id, user_id, due_at, type, status, created_at) VALUES
            (1, 1, 2, '{$now}', 'call', 'pending', '{$now}'),
            (2, 2, 3, '" . date('Y-m-d H:i:s', strtotime('-1 day')) . "', 'meeting', 'pending', '{$now}');
        ");

        $this->clientModel = new Client($this->pdo);
        $this->followUpModel = new FollowUp($this->pdo);
        $this->service = new DashboardService($this->clientModel, $this->followUpModel);
    }

    private function auth(int $userId, int $roleId): void
    {
        Session::start();
        Session::set('user_id', $userId);
        Session::set('role_id', $roleId);
        PermissionService::loadUserPermissions($userId, $roleId);
    }

    public function testAdminReceivesGlobalMetrics(): void
    {
        $this->auth(1, 1);
        $stats = $this->service->getStats();

        $this->assertSame(2, $stats['total_clients']);
        $this->assertSame(2, $stats['new_this_month']);
        $this->assertSame(1, $stats['by_status']['active']);
        $this->assertSame(1, $stats['by_status']['new']);
        $this->assertSame(1, $stats['follow_ups']['today_count']);
        $this->assertSame(1, $stats['follow_ups']['overdue_count']);
        $this->assertTrue($stats['can_view_all']);
        $this->assertCount(12, $stats['monthly_new_clients']);
    }

    public function testSalesUserMetricsAreStrictlyScoped(): void
    {
        $this->auth(2, 3); // Alice
        $stats = $this->service->getStats();

        $this->assertSame(1, $stats['total_clients']);
        $this->assertSame(1, $stats['new_this_month']);
        $this->assertSame(1, $stats['by_status']['active']);
        $this->assertSame(0, $stats['by_status']['new']);
        $this->assertSame(1, $stats['follow_ups']['today_count']);
        $this->assertSame(0, $stats['follow_ups']['overdue_count']);
        $this->assertFalse($stats['can_view_all']);
        $this->assertEmpty($stats['top_staff']);
    }
}
