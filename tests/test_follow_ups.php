<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Core/Logger.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Exceptions/ValidationException.php';
require_once dirname(__DIR__) . '/app/Models/BaseModel.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/Client.php';
require_once dirname(__DIR__) . '/app/Models/FollowUp.php';
require_once dirname(__DIR__) . '/app/Models/ActivityLog.php';
require_once dirname(__DIR__) . '/app/Services/PermissionService.php';
require_once dirname(__DIR__) . '/app/Services/FollowUpService.php';

use App\Core\Database;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\PermissionService;

function fuAssert(bool $condition, string $msg): void
{
    if (!$condition) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Follow-up & Activity Log Tests (Step 10 Standalone)...\n";

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
    CREATE TABLE activity_log (
        id INTEGER PRIMARY KEY, user_id INTEGER NULL, entity_type TEXT, entity_id INTEGER,
        action TEXT, old_values TEXT NULL, new_values TEXT NULL, ip_address TEXT NULL,
        user_agent TEXT NULL, created_at TEXT NULL
    );
");

// Seed Roles
$pdo->exec("
    INSERT INTO roles (id, name, label) VALUES
    (1, 'admin', 'Administrator'),
    (2, 'manager', 'Manager'),
    (3, 'sales', 'Sales Rep');
");

// Seed Permissions
$pdo->exec("
    INSERT INTO permissions (id, name, label) VALUES
    (1, 'client.view_all', 'View All Clients'),
    (2, 'client.view_own', 'View Own Clients'),
    (3, 'client.create', 'Create Client'),
    (4, 'client.edit', 'Edit Client'),
    (5, 'client.delete', 'Delete Client'),
    (6, 'followup.manage', 'Manage Follow-ups');

    INSERT INTO role_permissions (role_id, permission_id) VALUES
    (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), -- Admin
    (2, 1), (2, 3), (2, 4), (2, 5), (2, 6),         -- Manager
    (3, 2), (3, 3), (3, 4), (3, 6);                 -- Sales (only view_own)
");

// Seed Users
$pdo->exec("
    INSERT INTO users (id, role_id, name, email, password_hash, is_active) VALUES
    (1, 1, 'Admin User', 'admin@crm.local', 'hash', 1),
    (2, 3, 'Alice Sales', 'alice@crm.local', 'hash', 1),
    (3, 3, 'Bob Sales', 'bob@crm.local', 'hash', 1);
");

// Seed Clients: Client 1 assigned to Alice (2), Client 2 assigned to Bob (3)
$pdo->exec("
    INSERT INTO clients (id, client_code, name, email, mobile, assigned_to, status, created_at) VALUES
    (1, 'CL-2026-0001', 'Acme Corp', 'contact@acme.com', '9876543210', 2, 'active', '2026-01-01 00:00:00'),
    (2, 'CL-2026-0002', 'Beta LLC', 'info@beta.com', '9876543211', 3, 'active', '2026-01-02 00:00:00');
");

$followUpModel = new FollowUp($pdo);
$clientModel = new Client($pdo);
$activityLog = new ActivityLog($pdo);
$service = new FollowUpService($followUpModel, $clientModel, $activityLog);

// Helper to switch session
function switchUser(int $userId, int $roleId): void {
    Session::start();
    Session::set('user_id', $userId);
    Session::set('role_id', $roleId);
    PermissionService::loadUserPermissions($userId, $roleId);
}

// ==========================================
// 1. Admin creates follow-up for Client 1
// ==========================================
switchUser(1, 1);
$todayNoon = date('Y-m-d 12:00:00');
$fu1 = $service->create([
    'client_id' => 1,
    'due_at' => $todayNoon,
    'type' => 'call',
    'notes' => 'Discuss Q3 renewal proposal',
]);

fuAssert($fu1['id'] > 0, "Admin created follow-up #{$fu1['id']}");
fuAssert($fu1['type'] === 'call', "Follow-up type is call");
fuAssert($fu1['status'] === 'pending', "Follow-up default status is pending");

// ==========================================
// 2. Sales Scoping: Alice can see Client 1's follow-up
// ==========================================
switchUser(2, 3); // Alice
$aliceList = $service->list(['tab' => 'all']);
fuAssert(count($aliceList['items']) === 1, "Alice sees 1 follow-up (her client)");
fuAssert((int)$aliceList['items'][0]['client_id'] === 1, "Alice sees Client 1 follow-up");

$client1FUs = $service->getByClient(1);
fuAssert(count($client1FUs) === 1, "Alice can get Client 1 follow-ups via client ID");

// ==========================================
// 3. Sales Scoping: Bob CANNOT see Client 1's follow-up
// ==========================================
switchUser(3, 3); // Bob
$bobList = $service->list(['tab' => 'all']);
fuAssert(count($bobList['items']) === 0, "Bob sees 0 follow-ups (no follow-ups for Client 2 yet)");

$blockedGet = false;
try {
    $service->getByClient(1); // Bob attempting to view Client 1
} catch (RuntimeException $e) {
    $blockedGet = true;
}
fuAssert($blockedGet, "Bob is blocked from viewing Client 1 follow-ups (404/Access Denied)");

$blockedSingle = false;
try {
    $service->get($fu1['id']); // Bob attempting to view FU 1
} catch (RuntimeException $e) {
    $blockedSingle = true;
}
fuAssert($blockedSingle, "Bob is blocked from viewing FU 1 directly");

// ==========================================
// 4. Sales Scoping: Bob CANNOT create follow-up for Client 1
// ==========================================
$blockedCreate = false;
try {
    $service->create([
        'client_id' => 1,
        'due_at' => $todayNoon,
        'type' => 'meeting',
    ]);
} catch (RuntimeException $e) {
    $blockedCreate = true;
}
fuAssert($blockedCreate, "Bob is blocked from creating a follow-up for Client 1");

// ==========================================
// 5. Alice creates a follow-up for Client 1 and marks it done with outcome note
// ==========================================
switchUser(2, 3); // Alice
$futureDate = date('Y-m-d H:i:s', strtotime('+2 days'));
$fu2 = $service->create([
    'client_id' => 1,
    'due_at' => $futureDate,
    'type' => 'meeting',
    'notes' => 'Product demo with VP Engineering',
]);
fuAssert($fu2['id'] > 0, "Alice created meeting follow-up #{$fu2['id']}");

// Mark FU 2 as done with outcome note
$completed = $service->update($fu2['id'], [
    'outcome' => 'Demo went great. Client agreed to send RFP by Friday.',
]);

fuAssert($completed['status'] === 'done', "Follow-up status transitioned to 'done'");
fuAssert(str_contains($completed['notes'], '[Outcome]: Demo went great'), "Outcome note recorded in notes field");

// ==========================================
// 6. Verify Activity Log for follow-up events
// ==========================================
$activities = $activityLog->getForClient(1);
$actions = array_column($activities, 'action');
fuAssert(in_array('followup_created', $actions, true), "activity_log contains 'followup_created'");
fuAssert(in_array('followup_done', $actions, true), "activity_log contains 'followup_done'");

// ==========================================
// 7. Cron: markMissedPastDue transitions past-due pending items
// ==========================================
// Create a follow-up with past due date
$pastDate = date('Y-m-d H:i:s', strtotime('-1 day'));
$pdo->exec("
    INSERT INTO follow_ups (client_id, user_id, due_at, type, notes, status, created_at, updated_at)
    VALUES (1, 2, '{$pastDate}', 'call', 'Past due call', 'pending', datetime('now'), datetime('now'));
");
$pastFuId = (int)$pdo->lastInsertId();

$affected = $followUpModel->markMissedPastDue();
fuAssert($affected >= 1, "markMissedPastDue updated at least 1 record");

$checkedPast = $followUpModel->find($pastFuId);
fuAssert($checkedPast['status'] === 'missed', "Past due follow-up transitioned to 'missed'");

// ==========================================
// 8. Soft delete follow-up
// ==========================================
switchUser(2, 3); // Alice
$deleted = $service->delete($fu2['id']);
fuAssert($deleted, "Alice soft-deleted follow-up #{$fu2['id']}");

$deletedRecord = $followUpModel->find($fu2['id']);
fuAssert($deletedRecord === null, "Soft-deleted follow-up is not returned by find()");

$activitiesAfterDelete = $activityLog->getForClient(1);
$actionsAfterDelete = array_column($activitiesAfterDelete, 'action');
fuAssert(in_array('followup_deleted', $actionsAfterDelete, true), "activity_log contains 'followup_deleted'");

echo "\nAll Step 10 Follow-up & Activity Log Tests Passed Successfully!\n";
