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
require_once dirname(__DIR__) . '/app/Models/ClientDocument.php';
require_once dirname(__DIR__) . '/app/Models/ActivityLog.php';
require_once dirname(__DIR__) . '/app/Services/PermissionService.php';
require_once dirname(__DIR__) . '/app/Services/ClientService.php';

use App\Core\Database;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PermissionService;

function mgmtAssert(bool $condition, string $msg): void
{
    if (!$condition) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Client Management Tests (Step 9 Standalone)...\n";

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
    CREATE TABLE client_documents (
        id INTEGER PRIMARY KEY, client_id INTEGER, original_name TEXT, stored_name TEXT,
        mime_type TEXT, size_bytes INTEGER, uploaded_by INTEGER NULL,
        created_at TEXT NULL, updated_at TEXT NULL, deleted_at TEXT NULL
    );
    CREATE TABLE activity_log (
        id INTEGER PRIMARY KEY, user_id INTEGER NULL, entity_type TEXT, entity_id INTEGER,
        action TEXT, old_values TEXT NULL, new_values TEXT NULL, ip_address TEXT NULL,
        user_agent TEXT NULL, created_at TEXT NULL
    );
");

// Roles
$pdo->exec("INSERT INTO roles VALUES (1, 'admin', 'Admin'), (2, 'manager', 'Manager'), (3, 'sales', 'Sales')");
// Permissions
$pdo->exec("INSERT INTO permissions VALUES (1, 'client.create', 'Create Client'), (2, 'client.view_all', 'View All'), (3, 'client.view_own', 'View Own'), (4, 'client.edit', 'Edit Client'), (5, 'client.delete', 'Delete Client'), (6, 'client.export', 'Export Clients')");
// Role Perms
$pdo->exec("INSERT INTO role_permissions VALUES (1, 1), (1, 2), (1, 4), (1, 5), (1, 6), (2, 1), (2, 2), (2, 4), (2, 5), (2, 6), (3, 1), (3, 3), (3, 4), (3, 6)");

// Users: 1=Admin, 2=Manager, 10=Sales Alpha, 20=Sales Beta
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (1, 1, 'Admin User', 'admin@crm.local', 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (2, 2, 'Manager User', 'manager@crm.local', 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (10, 3, 'Sales Alpha', 'alpha@crm.local', 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (20, 3, 'Sales Beta', 'beta@crm.local', 1)");

// Seed Clients
$pdo->exec("INSERT INTO clients (id, client_code, name, email, mobile, gst_no, pan_no, city, state, lead_source, status, assigned_to, created_at)
    VALUES (1, 'CL-2026-0001', 'Alpha Technologies', 'info@alphatech.in', '9811111111', '27AAAAA0000A1Z5', 'AAAAA0000A', 'Mumbai', 'Maharashtra', 'Website', 'active', 10, '2026-09-01 10:00:00')");

$pdo->exec("INSERT INTO clients (id, client_code, name, email, mobile, gst_no, pan_no, city, state, lead_source, status, assigned_to, created_at)
    VALUES (2, 'CL-2026-0002', 'Beta Logistics', 'info@betalog.in', '9822222222', '27BBBBB0000B1Z5', 'BBBBB0000B', 'Pune', 'Maharashtra', 'Referral', 'new', 20, '2026-09-15 10:00:00')");

$tempUploadDir = sys_get_temp_dir() . '/crm_mgmt_sa_' . bin2hex(random_bytes(6));
mkdir($tempUploadDir, 0777, true);

$clientModel = new Client($pdo);
$docModel = new ClientDocument($pdo);
$activityLog = new ActivityLog($pdo);
$userModel = new User($pdo);

$clientService = new ClientService($clientModel, $docModel, $activityLog, $userModel, $tempUploadDir);

// -------------------------------------------------------------
// 1. Pagination, Filtering, Search & Scoping
// -------------------------------------------------------------
Session::start();
Session::set('user_id', 1);
PermissionService::refresh();

$allClients = $clientService->listClients();
mgmtAssert($allClients['recordsTotal'] === 2, "Admin sees all clients (recordsTotal = 2)");

// Search by GST
$gstMatch = $clientService->listClients(['search' => '27BBBBB0000B1Z5']);
mgmtAssert($gstMatch['recordsFiltered'] === 1 && $gstMatch['items'][0]['name'] === 'Beta Logistics', "Search matches GSTIN accurately");

// Filter by status 'active'
$activeOnly = $clientService->listClients(['status' => 'active']);
mgmtAssert($activeOnly['recordsFiltered'] === 1 && $activeOnly['items'][0]['name'] === 'Alpha Technologies', "Filter by status works");

// Sales scoping: Sales Alpha (User 10)
Session::set('user_id', 10);
PermissionService::refresh();

$salesClients = $clientService->listClients();
mgmtAssert($salesClients['recordsTotal'] === 1 && $salesClients['items'][0]['id'] === 1, "Sales user only sees assigned client (Client 1)");

// -------------------------------------------------------------
// 2. Profile Details & Activity Timeline
// -------------------------------------------------------------
Session::set('user_id', 1);
PermissionService::refresh();

$profile = $clientService->getClientProfile(1);
mgmtAssert($profile['client']['client_code'] === 'CL-2026-0001', "Client profile returns correct client details");
mgmtAssert(is_array($profile['documents']) && is_array($profile['activities']), "Client profile returns documents and activity lists");

// -------------------------------------------------------------
// 3. Update Client with Diff-Only Activity Log
// -------------------------------------------------------------
$updateData = [
    'name' => 'Alpha Technologies International',
    'email' => 'info@alphatech.in', // same email should pass (ignoring self)
    'mobile' => '9811111111',
    'city' => 'Navi Mumbai',
    'state' => 'Maharashtra',
    'address_line1' => 'Plot 99, IT Park',
    'pincode' => '400703',
    'status' => 'active',
];

$updated = $clientService->updateClient(1, $updateData, 1, '127.0.0.1', 'CLI Agent');
mgmtAssert($updated['name'] === 'Alpha Technologies International', "Client name updated");
mgmtAssert($updated['city'] === 'Navi Mumbai', "Client city updated");

$activities = $activityLog->getByEntity('client', 1);
mgmtAssert(!empty($activities) && $activities[0]['action'] === 'update', "Activity log recorded action=update");

$diffNew = json_decode($activities[0]['new_values'], true);
$diffOld = json_decode($activities[0]['old_values'], true);
mgmtAssert(isset($diffNew['name']) && $diffNew['name'] === 'Alpha Technologies International', "Diff captured new name");
mgmtAssert(isset($diffOld['name']) && $diffOld['name'] === 'Alpha Technologies', "Diff captured old name");
mgmtAssert(!isset($diffNew['email']), "Unchanged field (email) omitted from diff");

// -------------------------------------------------------------
// 4. Update Duplicate Email Rejection (Self Ignored, Others Blocked)
// -------------------------------------------------------------
$dupUpdateData = [
    'name' => 'Alpha Tech',
    'email' => 'info@betalog.in', // Taken by Client 2
    'mobile' => '9811111111',
    'city' => 'Mumbai',
    'state' => 'Maharashtra',
    'address_line1' => 'Street 1',
    'pincode' => '400001',
];

$caughtDupUpdate = false;
try {
    $clientService->updateClient(1, $dupUpdateData, 1);
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    if (isset($errors['email'])) {
        $caughtDupUpdate = true;
    }
}
mgmtAssert($caughtDupUpdate, "Updating to an existing client's email is blocked");

// -------------------------------------------------------------
// 5. Document Upload & Deletion in Profile
// -------------------------------------------------------------
$testFile = tempnam(sys_get_temp_dir(), 'prof_doc_');
file_put_contents($testFile, "%PDF-1.4\n%EOF");

$uploadDocData = [
    'name' => 'msme_certificate.pdf',
    'tmp_name' => $testFile,
    'size' => filesize($testFile),
    'error' => UPLOAD_ERR_OK,
];

$uploadedDoc = $clientService->addDocument(1, $uploadDocData, 1, '127.0.0.1');
mgmtAssert(isset($uploadedDoc['id']) && $uploadedDoc['original_name'] === 'msme_certificate.pdf', "Document uploaded to client profile");

$docLogs = $activityLog->getByEntity('client', 1);
mgmtAssert($docLogs[0]['action'] === 'document_upload', "Document upload recorded in activity log");

// Delete the document
$deletedDoc = $clientService->deleteDocument(1, (int)$uploadedDoc['id'], 1);
mgmtAssert($deletedDoc === true, "Document soft-deleted from client profile");

$docDelLogs = $activityLog->getByEntity('client', 1);
mgmtAssert($docDelLogs[0]['action'] === 'document_delete', "Document delete recorded in activity log");

// -------------------------------------------------------------
// 6. Export with PAN Masking
// -------------------------------------------------------------
// Non-admin (Manager): PAN masked showing last 4 only
Session::set('user_id', 2);
Session::set('role', 'manager');
PermissionService::refresh();

$mgrExport = $clientService->exportClients([], 'csv', 2);
$mgrContent = file_get_contents($mgrExport['file_path']);
mgmtAssert(str_contains($mgrContent, '******000A') && !str_contains($mgrContent, 'AAAAA0000A'), "Manager export masks PAN to show last 4 only");
@unlink($mgrExport['file_path']);

// Admin: full PAN visible
Session::set('user_id', 1);
Session::set('role', 'admin');
PermissionService::refresh();

$adminExport = $clientService->exportClients([], 'csv', 1);
$adminContent = file_get_contents($adminExport['file_path']);
mgmtAssert(str_contains($adminContent, 'AAAAA0000A'), "Admin export shows full unmasked PAN");
@unlink($adminExport['file_path']);

// -------------------------------------------------------------
// 7. Client Soft Delete
// -------------------------------------------------------------
$deletedClient = $clientService->deleteClient(1, 1);
mgmtAssert($deletedClient === true, "Client soft deleted");

$postDeleteList = $clientService->listClients();
mgmtAssert($postDeleteList['recordsTotal'] === 1 && $postDeleteList['items'][0]['id'] === 2, "Soft-deleted client excluded from listings");

$delLogs = $activityLog->getByEntity('client', 1);
mgmtAssert($delLogs[0]['action'] === 'delete', "Client deletion logged in activity_log");

// Cleanup
if (file_exists($testFile)) {
    @unlink($testFile);
}
@rmdir($tempUploadDir);

echo "All Client Management Standalone Tests Passed Successfully!\n";
