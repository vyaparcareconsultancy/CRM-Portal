<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Core/Logger.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Core/Validator.php';
require_once dirname(__DIR__) . '/app/Exceptions/ValidationException.php';
require_once dirname(__DIR__) . '/app/Helpers/Crypto.php';
require_once dirname(__DIR__) . '/app/Middleware/SecurityHeadersMiddleware.php';
require_once dirname(__DIR__) . '/app/Models/BaseModel.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/Client.php';
require_once dirname(__DIR__) . '/app/Models/ClientDocument.php';
require_once dirname(__DIR__) . '/app/Models/ActivityLog.php';
require_once dirname(__DIR__) . '/app/Services/PermissionService.php';
require_once dirname(__DIR__) . '/app/Services/ClientService.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Helpers\Crypto;
use App\Middleware\SecurityHeadersMiddleware;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PermissionService;

function secAssert(bool $condition, string $msg): void
{
    if (!$condition) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Security Hardening & DPDP Audit Tests...\n";

// -------------------------------------------------------------
// 1. Test Crypto: OpenSSL AES-256-GCM Encryption, Decryption, Masking
// -------------------------------------------------------------
$rawPan = 'ABCDE1234F';
$encryptedPan = Crypto::encryptPan($rawPan);

secAssert($encryptedPan !== null, "PAN encryption returned non-null payload");
secAssert($encryptedPan !== $rawPan, "Encrypted PAN is not plaintext");
secAssert(strlen($encryptedPan) > 40, "Encrypted PAN contains IV, Tag, and Ciphertext base64 payload");

$decryptedPan = Crypto::decryptPan($encryptedPan);
secAssert($decryptedPan === $rawPan, "Decrypted PAN matches original plaintext ({$decryptedPan})");

$maskedFromPlain = Crypto::maskPan($rawPan);
secAssert($maskedFromPlain === '******234F', "Masking from plaintext produces ******234F ({$maskedFromPlain})");

$maskedFromCipher = Crypto::maskPan($encryptedPan);
secAssert($maskedFromCipher === '******234F', "Masking from ciphertext produces ******234F ({$maskedFromCipher})");

secAssert(Crypto::isMasked('******234F'), "isMasked correctly identifies masked value");
secAssert(!Crypto::isMasked('ABCDE1234F'), "isMasked returns false for unmasked PAN");

// -------------------------------------------------------------
// 2. Test Password Policy: Validator
// -------------------------------------------------------------
// Weak: < 8 chars
$v1 = Validator::make(['password' => 'Pass1'], ['password' => 'password_policy']);
secAssert($v1->fails(), "Password policy rejects password < 8 characters");

// Weak: only letters
$v2 = Validator::make(['password' => 'PasswordOnly'], ['password' => 'password_policy']);
secAssert($v2->fails(), "Password policy rejects password without numbers");

// Weak: only numbers
$v3 = Validator::make(['password' => '123456789'], ['password' => 'password_policy']);
secAssert($v3->fails(), "Password policy rejects password without letters");

// Weak: common blacklist password
$v4 = Validator::make(['password' => 'password123'], ['password' => 'password_policy']);
secAssert($v4->fails(), "Password policy rejects common blacklisted password (password123)");

$v5 = Validator::make(['password' => 'admin123'], ['password' => 'password_policy']);
secAssert($v5->fails(), "Password policy rejects common blacklisted password (admin123)");

// Strong: meets all criteria
$v6 = Validator::make(['password' => 'CrmSecurePass2026!'], ['password' => 'password_policy']);
secAssert($v6->passes(), "Password policy accepts compliant password with letters, digits, min length");

// -------------------------------------------------------------
// 3. Test Storage Hardening: .htaccess files
// -------------------------------------------------------------
$storageHtaccess = dirname(__DIR__) . '/storage/.htaccess';
secAssert(file_exists($storageHtaccess), "storage/.htaccess exists");
$content = (string)file_get_contents($storageHtaccess);
secAssert(str_contains($content, 'Require all denied') || str_contains($content, 'Deny from all'), "storage/.htaccess denies direct web access");
secAssert(str_contains($content, 'php_flag engine off'), "storage/.htaccess disables PHP script execution");

$uploadsHtaccess = dirname(__DIR__) . '/storage/uploads/.htaccess';
secAssert(file_exists($uploadsHtaccess), "storage/uploads/.htaccess exists");
$uploadsContent = (string)file_get_contents($uploadsHtaccess);
secAssert(str_contains($uploadsContent, 'Require all denied'), "storage/uploads/.htaccess denies direct web access");

// -------------------------------------------------------------
// 4. Test In-Database PAN Encryption & DPDP Anonymization
// -------------------------------------------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
Database::setConnection($pdo);

$pdo->exec("
    CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT, label TEXT);
    CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT, label TEXT);
    CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER);
    CREATE TABLE users (id INTEGER PRIMARY KEY, role_id INTEGER, name TEXT, email TEXT, password TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT);
    CREATE TABLE clients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_code TEXT,
        client_type TEXT,
        name TEXT,
        contact_person TEXT NULL,
        email TEXT,
        mobile TEXT,
        alt_mobile TEXT NULL,
        gst_no TEXT NULL,
        pan_no TEXT NULL,
        industry TEXT NULL,
        company_size TEXT NULL,
        website TEXT NULL,
        address_line1 TEXT,
        address_line2 TEXT NULL,
        city TEXT,
        state TEXT,
        pincode TEXT,
        country TEXT,
        lead_source TEXT NULL,
        assigned_to INTEGER,
        status TEXT,
        tags TEXT NULL,
        notes TEXT NULL,
        consent_given INTEGER,
        consent_at TEXT,
        created_by INTEGER,
        created_at TEXT,
        updated_at TEXT,
        deleted_at TEXT NULL
    );
    CREATE TABLE client_documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER,
        original_name TEXT,
        stored_name TEXT,
        mime_type TEXT,
        size_bytes INTEGER,
        uploaded_by INTEGER,
        created_at TEXT,
        deleted_at TEXT NULL
    );
    CREATE TABLE activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        entity_type TEXT,
        entity_id INTEGER,
        action TEXT,
        new_values TEXT NULL,
        old_values TEXT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        created_at TEXT
    );
");

// Setup Admin & Sales roles
$pdo->exec("
    INSERT INTO roles (id, name, label) VALUES (1, 'admin', 'Administrator'), (2, 'sales', 'Sales Representative');
    INSERT INTO permissions (id, name, label) VALUES 
        (1, 'client.create', 'Create Client'),
        (2, 'client.view_all', 'View All Clients'),
        (3, 'client.view_own', 'View Own Clients'),
        (4, 'client.edit', 'Edit Client'),
        (5, 'client.delete', 'Delete Client'),
        (6, 'user.manage', 'Manage Users');
    INSERT INTO role_permissions (role_id, permission_id) VALUES 
        (1, 1), (1, 2), (1, 4), (1, 5), (1, 6),
        (2, 1), (2, 3), (2, 4);
    INSERT INTO users (id, role_id, name, email, password, is_active) VALUES 
        (1, 1, 'Admin User', 'admin@techtians.in', 'hash', 1),
        (2, 2, 'Sales Rep', 'sales@techtians.in', 'hash', 1);
");

$clientModel = new Client($pdo);
$docModel = new ClientDocument($pdo);
$activityLog = new ActivityLog($pdo);
$userModel = new User($pdo);

$tempUploadDir = sys_get_temp_dir() . '/crm_sec_test_' . bin2hex(random_bytes(6));
mkdir($tempUploadDir, 0755, true);

$clientService = new ClientService($clientModel, $docModel, $activityLog, $userModel, $tempUploadDir);

// Log in as Admin
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Admin User';
PermissionService::loadUserPermissions(1, $pdo);

// Create Client with PAN
$clientData = [
    'client_type' => 'individual',
    'name' => 'Rahul Sharma',
    'email' => 'rahul.sharma@example.com',
    'mobile' => '9876543210',
    'pan_no' => 'ABCDE1234F',
    'address_line1' => '123 MG Road',
    'city' => 'Mumbai',
    'state' => 'Maharashtra',
    'pincode' => '400001',
    'consent_given' => '1',
];

$res = $clientService->createClient($clientData, [], 1, '127.0.0.1', 'CLI Test');
$clientId = (int)$res['id'];
secAssert($clientId > 0, "Client created successfully with ID {$clientId}");

// Verify Raw Database Stored Record: PAN must be ENCRYPTED at rest
$rawRow = $pdo->query("SELECT pan_no FROM clients WHERE id = {$clientId}")->fetch();
secAssert($rawRow['pan_no'] !== 'ABCDE1234F', "PAN at rest is NOT plaintext in database ({$rawRow['pan_no']})");
secAssert(strlen($rawRow['pan_no']) > 40, "PAN at rest is stored as AES-256-GCM encrypted payload");

// Verify Model find() auto-decrypts
$loadedClient = $clientModel->find($clientId);
secAssert($loadedClient['pan_no'] === 'ABCDE1234F', "ClientModel::find() cleanly decrypts PAN ({$loadedClient['pan_no']})");

// Verify ClientService::getClient() masks PAN on display
$displayedClient = $clientService->getClient($clientId);
secAssert($displayedClient['pan_no'] === '******234F', "ClientService::getClient() masks PAN on display ({$displayedClient['pan_no']})");
secAssert($displayedClient['pan_no_raw'] === 'ABCDE1234F', "ClientService::getClient() provides pan_no_raw to admin ({$displayedClient['pan_no_raw']})");

// Verify ClientService::getClientProfile() masks PAN on display
$profile = $clientService->getClientProfile($clientId);
secAssert($profile['client']['pan_no'] === '******234F', "ClientService::getClientProfile() masks PAN for UI profile");

// Verify Activity Log does NOT leak plain PAN
$logs = $activityLog->where(['entity_type' => 'client', 'entity_id' => $clientId]);
secAssert(!empty($logs), "Activity log entry created");
secAssert(!str_contains($logs[0]['new_values'] ?? '', 'ABCDE1234F'), "Activity log new_values does not leak raw plaintext PAN");

// Add a test file in storage for DPDP test
$clientUploadDir = $tempUploadDir . '/' . $clientId;
mkdir($clientUploadDir, 0755, true);
$testFilePath = $clientUploadDir . '/test_id_proof.pdf';
file_put_contents($testFilePath, 'dummy pdf content');

$docId = $docModel->createDocument([
    'client_id' => $clientId,
    'original_name' => 'id_proof.pdf',
    'stored_name' => 'test_id_proof.pdf',
    'mime_type' => 'application/pdf',
    'size_bytes' => 17,
    'uploaded_by' => 1,
]);
secAssert(file_exists($testFilePath), "Test client file created in storage folder");

// -------------------------------------------------------------
// 5. Test DPDP Act Anonymization Action
// -------------------------------------------------------------
// Test sales user forbidden
$_SESSION['user_id'] = 2;
PermissionService::loadUserPermissions(2, $pdo);

$salesForbidden = false;
try {
    $clientService->anonymizeClient($clientId, 2);
} catch (\Throwable $e) {
    if ($e->getCode() === 403) {
        $salesForbidden = true;
    }
}
secAssert($salesForbidden, "Sales user is blocked from executing DPDP client anonymization (403)");

// Admin executes anonymization
$_SESSION['user_id'] = 1;
PermissionService::loadUserPermissions(1, $pdo);

$anonSuccess = $clientService->anonymizeClient($clientId, 1, '127.0.0.1', 'Audit CLI');
secAssert($anonSuccess, "Admin successfully anonymized client record pursuant to DPDP Act");

// Verify physical files purged
secAssert(!file_exists($testFilePath), "Physical client documents deleted from storage");
secAssert(!is_dir($clientUploadDir), "Client upload directory purged from storage");

// Verify document record redacted
$docRecord = $pdo->query("SELECT * FROM client_documents WHERE id = {$docId}")->fetch();
secAssert($docRecord['original_name'] === '[Redacted]', "Document original name set to [Redacted]");
secAssert($docRecord['stored_name'] === '', "Document stored name emptied");
secAssert($docRecord['deleted_at'] !== null, "Document marked as soft-deleted");

// Verify database record anonymized
$anonDbRecord = $pdo->query("SELECT * FROM clients WHERE id = {$clientId}")->fetch();
secAssert($anonDbRecord['name'] === "Anonymized Client #{$clientId}", "Name redacted/anonymized ({$anonDbRecord['name']})");
secAssert(str_starts_with($anonDbRecord['email'], "anonymized_{$clientId}_"), "Email pseudonymized to dummy domain ({$anonDbRecord['email']})");
secAssert($anonDbRecord['pan_no'] === null, "PAN number cleared to null");
secAssert($anonDbRecord['address_line1'] === '[Redacted for Privacy]', "Address redacted");
secAssert($anonDbRecord['status'] === 'inactive', "Status updated to inactive");
secAssert($anonDbRecord['deleted_at'] !== null, "Record marked as soft-deleted with timestamp");

// Verify DPDP activity log audit entry
$dpdpLogs = $activityLog->where(['entity_type' => 'client', 'entity_id' => $clientId, 'action' => 'dpdp_anonymize']);
secAssert(count($dpdpLogs) === 1, "Activity log contains dpdp_anonymize action entry");
secAssert(!str_contains($dpdpLogs[0]['new_values'] ?? '', 'Rahul Sharma'), "DPDP audit log does not retain redacted PII");

// Cleanup temp test directory
@unlink($tempUploadDir);

echo "\nAll Security Hardening & DPDP Audit Tests Passed!\n";
