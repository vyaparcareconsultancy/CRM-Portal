<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Core/Logger.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Models/BaseModel.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/Client.php';
require_once dirname(__DIR__) . '/app/Models/FollowUp.php';
require_once dirname(__DIR__) . '/app/Services/PermissionService.php';
require_once dirname(__DIR__) . '/app/Services/DashboardService.php';

use App\Core\Database;
use App\Core\Session;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\PermissionService;

function dashAssert(bool $condition, string $msg): void
{
    if (!$condition) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Dashboard Aggregations & Scoping Tests (Step 11 Standalone)...\n";

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
        id INTEGER PRIMARY KEY, client_code TEXT, client_type TEXT DEFAULT 'individual',
        name TEXT, contact_person TEXT NULL, email TEXT UNIQUE, mobile TEXT UNIQUE,
        alt_mobile TEXT NULL, gst_no TEXT NULL, pan_no TEXT NULL, industry TEXT NULL,
        company_size TEXT NULL, website TEXT NULL, address_line1 TEXT NULL, address_line2 TEXT NULL,
        city TEXT NULL, state TEXT NULL, pincode TEXT NULL, country TEXT DEFAULT 'India',
        lead_source TEXT NULL, assigned_to INTEGER NULL, status TEXT DEFAULT 'new',
        tags TEXT NULL, notes TEXT NULL, consent_given INTEGER DEFAULT 0, consent_at TEXT NULL,
        created_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL, deleted_at TEXT NULL
    );
    CREATE TABLE follow_ups (
        id INTEGER PRIMARY KEY, client_id INTEGER, user_id INTEGER,
        due_at TEXT, type TEXT, notes TEXT NULL, status TEXT DEFAULT 'pending',
        created_at TEXT NULL, updated_at TEXT NULL, deleted_at TEXT NULL
    );
");

// Seed Roles
$pdo->exec("
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
    (1, 1, 'Admin User', 'admin@crm.local', 'hash', 1),
    (2, 3, 'Alice Sales', 'alice@crm.local', 'hash', 1),
    (3, 3, 'Bob Sales', 'bob@crm.local', 'hash', 1);
");

$now = date('Y-m-d H:i:s');
$currentMonth = date('Y-m-d 10:00:00');
$pastMonth = date('Y-m-d 10:00:00', strtotime('-2 months'));

// Seed Clients:
// Client 1: assigned to Alice (2), status 'active', lead_source 'Website', created this month
// Client 2: assigned to Bob (3), status 'new', lead_source 'Referral', created this month
// Client 3: assigned to Alice (2), status 'inactive', lead_source 'Website', created 2 months ago
$pdo->exec("
    INSERT INTO clients (id, client_code, name, email, mobile, assigned_to, status, lead_source, created_at) VALUES
    (1, 'CL-2026-0001', 'Acme Corp', 'c1@test.com', '9876543210', 2, 'active', 'Website', '{$currentMonth}'),
    (2, 'CL-2026-0002', 'Beta LLC', 'c2@test.com', '9876543211', 3, 'new', 'Referral', '{$currentMonth}'),
    (3, 'CL-2026-0003', 'Gamma Ltd', 'c3@test.com', '9876543212', 2, 'inactive', 'Website', '{$pastMonth}');
");

// Seed Follow-ups:
// FU 1: Client 1, due today (pending)
// FU 2: Client 2, overdue (pending, due yesterday)
// FU 3: Client 1, done
$todayDue = date('Y-m-d 15:00:00');
$overdueDue = date('Y-m-d 10:00:00', strtotime('-1 day'));

$pdo->exec("
    INSERT INTO follow_ups (id, client_id, user_id, due_at, type, status, created_at) VALUES
    (1, 1, 2, '{$todayDue}', 'call', 'pending', '{$now}'),
    (2, 2, 3, '{$overdueDue}', 'meeting', 'pending', '{$now}'),
    (3, 1, 2, '{$todayDue}', 'email', 'done', '{$now}');
");

$clientModel = new Client($pdo);
$followUpModel = new FollowUp($pdo);
$service = new DashboardService($clientModel, $followUpModel);

function setTestUser(int $userId, int $roleId): void {
    Session::start();
    Session::set('user_id', $userId);
    Session::set('role_id', $roleId);
    PermissionService::loadUserPermissions($userId, $roleId);
}

// ==========================================
// 1. Admin Dashboard Stats (Full Scope)
// ==========================================
setTestUser(1, 1); // Admin
$adminStats = $service->getStats();

dashAssert($adminStats['total_clients'] === 3, "Admin sees 3 total clients");
dashAssert($adminStats['new_this_month'] === 2, "Admin sees 2 new clients this month");
dashAssert($adminStats['by_status']['active'] === 1, "Status count active is 1");
dashAssert($adminStats['by_status']['new'] === 1, "Status count new is 1");
dashAssert($adminStats['by_status']['inactive'] === 1, "Status count inactive is 1");
dashAssert($adminStats['by_lead_source']['Website'] === 2, "Lead source Website count is 2");
dashAssert($adminStats['by_lead_source']['Referral'] === 1, "Lead source Referral count is 1");

dashAssert(count($adminStats['monthly_new_clients']) === 12, "Monthly trend has exactly 12 months");
dashAssert($adminStats['follow_ups']['today_count'] === 1, "Today's pending follow-up count is 1");
dashAssert($adminStats['follow_ups']['overdue_count'] === 1, "Overdue pending follow-up count is 1");
dashAssert(count($adminStats['top_staff']) >= 1, "Admin receives top staff leaderboard");
dashAssert($adminStats['can_view_all'] === true, "Admin has can_view_all = true");

// ==========================================
// 2. Sales Rep Alice Dashboard Stats (Scoped)
// ==========================================
setTestUser(2, 3); // Alice (assigned to Client 1 & Client 3)
$aliceStats = $service->getStats();

dashAssert($aliceStats['total_clients'] === 2, "Alice sees only her 2 clients");
dashAssert($aliceStats['new_this_month'] === 1, "Alice sees only 1 new client this month (Client 1)");
dashAssert($aliceStats['by_status']['new'] === 0, "Alice sees 0 'new' status clients (Client 2 is Bob's)");
dashAssert($aliceStats['by_status']['active'] === 1, "Alice sees 1 active client");
dashAssert($aliceStats['by_status']['inactive'] === 1, "Alice sees 1 inactive client");
dashAssert(!isset($aliceStats['by_lead_source']['Referral']), "Alice does not see Bob's lead sources");

dashAssert($aliceStats['follow_ups']['today_count'] === 1, "Alice sees today's pending follow-up for Client 1");
dashAssert($aliceStats['follow_ups']['overdue_count'] === 0, "Alice sees 0 overdue follow-ups (overdue was Bob's)");
dashAssert(empty($aliceStats['top_staff']), "Sales rep receives empty top staff leaderboard");
dashAssert($aliceStats['can_view_all'] === false, "Alice has can_view_all = false");

// ==========================================
// 3. Sales Rep Bob Dashboard Stats (Scoped)
// ==========================================
setTestUser(3, 3); // Bob (assigned to Client 2)
$bobStats = $service->getStats();

dashAssert($bobStats['total_clients'] === 1, "Bob sees only his 1 client");
dashAssert($bobStats['follow_ups']['today_count'] === 0, "Bob has 0 follow-ups today");
dashAssert($bobStats['follow_ups']['overdue_count'] === 1, "Bob has 1 overdue follow-up");

echo "\nAll Step 11 Dashboard Aggregations & Scoping Tests Passed Successfully!\n";
