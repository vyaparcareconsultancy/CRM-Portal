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

function clientAssert(bool $condition, string $msg): void
{
    if (!$condition) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Client Creation Backend Tests (Standalone)...\n";

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

// Seed Roles
$pdo->exec("INSERT INTO roles VALUES (1, 'admin', 'Admin'), (2, 'manager', 'Manager'), (3, 'sales', 'Sales')");
// Seed Perms
$pdo->exec("INSERT INTO permissions VALUES (1, 'client.create', 'Create Client'), (2, 'client.view_all', 'View All'), (3, 'client.view_own', 'View Own')");
// Role Perms
$pdo->exec("INSERT INTO role_permissions VALUES (1, 1), (1, 2), (2, 1), (2, 2), (3, 1), (3, 3)");

// Users: 1=Admin, 10=Sales Alpha, 20=Sales Beta
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (1, 1, 'Admin', 'admin@crm.local', 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (10, 3, 'Sales Alpha', 'alpha@crm.local', 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (20, 3, 'Sales Beta', 'beta@crm.local', 1)");

$tempUploadDir = sys_get_temp_dir() . '/crm_standalone_uploads_' . bin2hex(random_bytes(6));
mkdir($tempUploadDir, 0777, true);

$clientModel = new Client($pdo);
$docModel = new ClientDocument($pdo);
$activityLog = new ActivityLog($pdo);
$userModel = new User($pdo);

$clientService = new ClientService($clientModel, $docModel, $activityLog, $userModel, $tempUploadDir);

// ---------------------------------------------------------
// Test 1: Admin Creates Client with Document Upload & Normalization
// ---------------------------------------------------------
Session::start();
Session::set('user_id', 1);
PermissionService::refresh();

$tmpFile = tempnam(sys_get_temp_dir(), 'test_doc_');
file_put_contents($tmpFile, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>%%EOF");

$data1 = [
    'client_type' => 'company',
    'name' => 'Tech Corp India',
    'contact_person' => 'Amitabh Bachchan',
    'email' => 'INFO@TECHCORP.IN', // Test lowercase normalization
    'mobile' => '98200 12345',      // Test space removal
    'alt_mobile' => '98200 54321',  // Test space removal
    'gst_no' => '27aaaaa0000a1z5', // Test uppercase normalization
    'pan_no' => 'aaaaa0000a',      // Test uppercase normalization
    'industry' => 'IT & Software',
    'company_size' => '51-200',
    'website' => 'https://techcorp.in',
    'address_line1' => 'Plot 4, Silicon Valley',
    'city' => 'Bengaluru',
    'state' => 'Karnataka',
    'pincode' => '560001',
    'consent_given' => '1',
    'assigned_to' => 20, // Admin assigns to User 20
];

$files1 = [
    'documents' => [
        [
            'name' => 'gst_certificate.pdf',
            'tmp_name' => $tmpFile,
            'size' => filesize($tmpFile),
            'error' => UPLOAD_ERR_OK,
        ]
    ]
];

$res1 = $clientService->createClient($data1, $files1, 1, '127.0.0.1', 'CLI Test Runner');
clientAssert(isset($res1['id']) && $res1['id'] > 0, "Admin client created with valid ID");
clientAssert(str_starts_with($res1['client_code'], 'CL-') && str_ends_with($res1['client_code'], '-0001'), "Client code generated correctly ({$res1['client_code']})");

$c1 = $clientModel->find($res1['id']);
clientAssert($c1['email'] === 'info@techcorp.in', "Email normalized to lowercase");
clientAssert($c1['mobile'] === '9820012345', "Mobile spaces stripped");
clientAssert($c1['gst_no'] === '27AAAAA0000A1Z5', "GST normalized to uppercase");
clientAssert($c1['pan_no'] === 'AAAAA0000A', "PAN normalized to uppercase");
clientAssert((int)$c1['assigned_to'] === 20, "Admin can assign to any user");

// Check document
$docs = $docModel->getByClient($res1['id']);
clientAssert(count($docs) === 1 && $docs[0]['original_name'] === 'gst_certificate.pdf', "Document metadata recorded in DB");
clientAssert(file_exists($tempUploadDir . '/' . $res1['id'] . '/' . $docs[0]['stored_name']), "Document saved safely in non-web storage folder");

// Check activity log
$logs = $activityLog->where(['entity_type' => 'client', 'entity_id' => $res1['id']]);
clientAssert(count($logs) === 1 && $logs[0]['action'] === 'create', "Activity log written with action=create");

// ---------------------------------------------------------
// Test 2: Duplicate Email Rejection (Active & Soft Deleted)
// ---------------------------------------------------------
$dupData = [
    'client_type' => 'individual',
    'name' => 'Duplicate Candidate',
    'email' => 'info@techcorp.in',
    'mobile' => '9111122223',
    'address_line1' => 'Street 1',
    'city' => 'Delhi',
    'state' => 'Delhi',
    'pincode' => '110001',
    'consent_given' => '1',
];

$caughtDup = false;
try {
    $clientService->createClient($dupData, [], 1);
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    if (isset($errors['email']) && $errors['email'] === 'Client with this email already exists.') {
        $caughtDup = true;
    }
}
clientAssert($caughtDup, "Active client duplicate email rejected with clear message");

// Soft delete client 1
$pdo->exec("UPDATE clients SET deleted_at = '2026-09-27 12:00:00' WHERE id = {$res1['id']}");
$caughtSoftDup = false;
try {
    $clientService->createClient($dupData, [], 1);
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    if (isset($errors['email']) && $errors['email'] === 'Client with this email already exists.') {
        $caughtSoftDup = true;
    }
}
clientAssert($caughtSoftDup, "Soft-deleted client email is STILL treated as taken");

// ---------------------------------------------------------
// Test 3: Invalid GST Rejection
// ---------------------------------------------------------
$badGstData = [
    'client_type' => 'individual',
    'name' => 'Bad GST Candidate',
    'email' => 'unique_gst_test@techcorp.in',
    'mobile' => '9998887776',
    'gst_no' => 'NOT_A_VALID_GST_NUMBER',
    'address_line1' => 'Street 1',
    'city' => 'Delhi',
    'state' => 'Delhi',
    'pincode' => '110001',
    'consent_given' => '1',
];

$caughtBadGst = false;
try {
    $clientService->createClient($badGstData, [], 1);
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    if (isset($errors['gst_no'])) {
        $caughtBadGst = true;
    }
}
clientAssert($caughtBadGst, "Invalid GST format correctly fails validation");

// ---------------------------------------------------------
// Test 4: Sales User Auto-Assignment Enforced
// ---------------------------------------------------------
// Switch to Sales Alpha (User 10)
Session::set('user_id', 10);
PermissionService::refresh();

$salesData = [
    'client_type' => 'individual',
    'name' => 'Self Assigned Client',
    'email' => 'salesrep_client@techcorp.in',
    'mobile' => '9777788889',
    'address_line1' => 'Street 10',
    'city' => 'Jaipur',
    'state' => 'Rajasthan',
    'pincode' => '302001',
    'consent_given' => '1',
    'assigned_to' => 20, // Trying to assign to Sales Beta
];

$resSales = $clientService->createClient($salesData, [], 10);
$cSales = $clientModel->find($resSales['id']);
clientAssert((int)$cSales['assigned_to'] === 10, "Sales user forced assigned_to = self (10), ignoring payload (20)");

// ---------------------------------------------------------
// Test 5: Lookups Data
// ---------------------------------------------------------
$lookups = $clientService->getLookups();
clientAssert(is_array($lookups['industries']) && in_array('IT & Software', $lookups['industries'], true), "Lookups returns industries");
clientAssert(is_array($lookups['states']) && in_array('Maharashtra', $lookups['states'], true), "Lookups returns states");
clientAssert(is_array($lookups['lead_sources']) && in_array('Website', $lookups['lead_sources'], true), "Lookups returns lead sources");
clientAssert(is_array($lookups['staff']) && count($lookups['staff']) === 3, "Lookups returns active staff list");

// Cleanup
if (file_exists($tmpFile)) {
    @unlink($tmpFile);
}
// Clean temp directory
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tempUploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($files as $fileinfo) {
    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
    $todo($fileinfo->getRealPath());
}
@rmdir($tempUploadDir);

echo "Client Creation Standalone Tests Completed Successfully!\n";
