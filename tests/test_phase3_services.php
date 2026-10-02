<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $trimmed, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

use App\Core\Database;
use App\Core\Session;
use App\Helpers\Crypto;
use App\Services\ClientComplianceService;
use App\Services\PermissionService;
use App\Services\ServiceCatalogService;

Session::start();

function assertCheck(bool $condition, string $message): void
{
    if ($condition) {
        echo "PASS: {$message}\n";
    } else {
        echo "FAIL: {$message}\n";
        exit(1);
    }
}

function setUser(PDO $pdo, string $roleName): int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$roleName . '@crm.local']);
    $userId = (int)$stmt->fetchColumn();

    if (!$userId) {
        $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $stmtRole->execute([$roleName]);
        $roleId = (int)$stmtRole->fetchColumn();

        $insert = $pdo->prepare("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)");
        $insert->execute([$roleId, ucfirst($roleName) . ' User', $roleName . '@crm.local', password_hash('Pass@123', PASSWORD_BCRYPT)]);
        $userId = (int)$pdo->lastInsertId();
    }

    Session::set('user_id', $userId);
    Session::set('user_role', $roleName);

    $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
    $stmtRole->execute([$roleName]);
    $roleId = (int)$stmtRole->fetchColumn();
    Session::set('role_id', $roleId);

    PermissionService::refresh();
    return $userId;
}

echo "========================================\n";
echo "Testing Phase 3: Services Master, Client Services, Compliance & Work Tracker\n";
echo "========================================\n\n";

$pdo = Database::getConnection();
$catalogService = new ServiceCatalogService();
$complianceService = new ClientComplianceService();

// Set user as admin
$adminId = setUser($pdo, 'admin');
$accountantId = setUser($pdo, 'accountant');
$counselorId = setUser($pdo, 'counselor');
$trainerId = setUser($pdo, 'trainer');

// 1. Services Master Catalog
echo "--- 1. Testing Services Master Catalog ---\n";
setUser($pdo, 'admin');
$services = $catalogService->listServices();
$serviceNames = array_column($services, 'name');

$expectedServices = [
    'GST Registration',
    'GST Return',
    'ITR',
    'Accounting/Bookkeeping',
    'Tax Audit',
    'Company/Firm Registration',
    'ROC Compliance',
    'TDS',
    'PF/ESI',
];

foreach ($expectedServices as $exp) {
    assertCheck(in_array($exp, $serviceNames, true), "Master catalog includes '{$exp}'");
}

$gstReturn = null;
foreach ($services as $srv) {
    if ($srv['name'] === 'GST Return') {
        $gstReturn = $srv;
        break;
    }
}
assertCheck($gstReturn !== null && $gstReturn['type'] === 'recurring', "GST Return is recurring");
assertCheck($gstReturn['frequency'] === 'monthly', "GST Return has monthly frequency");
assertCheck((float)$gstReturn['default_fee'] > 0, "GST Return has default fee");

// Test non-admin cannot create service
setUser($pdo, 'counselor');
$blocked = false;
try {
    $catalogService->createService(['name' => 'Should Fail', 'type' => 'one_time']);
} catch (RuntimeException $e) {
    $blocked = ($e->getCode() === 403);
}
assertCheck($blocked, "Counselor cannot create services in master catalog (403)");

// 2. Client Setup
echo "\n--- 2. Setting Up Test Client ---\n";
setUser($pdo, 'admin');
$clientStmt = $pdo->query("SELECT id FROM clients WHERE client_code = 'CL-PHASE3-TEST' LIMIT 1");
$clientId = (int)$clientStmt->fetchColumn();
if (!$clientId) {
    $pdo->prepare("
        INSERT INTO clients (client_code, name, email, mobile, assigned_to, created_by, status)
        VALUES ('CL-PHASE3-TEST', 'Phase 3 Corp', 'phase3@test.local', '9988776655', ?, ?, 'active')
    ")->execute([$accountantId, $adminId]);
    $clientId = (int)$pdo->lastInsertId();
}
assertCheck($clientId > 0, "Test client exists (ID: {$clientId})");

// 3. Client Services Subscriptions
echo "\n--- 3. Testing Client Services Subscriptions ---\n";
setUser($pdo, 'accountant');

$newSub = $complianceService->addClientService($clientId, [
    'service_id' => (int)$gstReturn['id'],
    'start_date' => '2026-10-01',
    'frequency' => 'monthly',
    'fee' => 2200.00,
    'assigned_accountant_id' => $accountantId,
    'status' => 'active',
    'notes' => 'Handled by accountant',
]);

assertCheck(!empty($newSub['id']), "Service subscription created");
$subId = (int)$newSub['id'];
assertCheck($newSub['status'] === 'active', "Initial status is 'active'");
assertCheck((float)$newSub['fee'] === 2200.00, "Fee recorded accurately");

// Update status to paused then completed
$paused = $complianceService->updateClientService($subId, ['status' => 'paused']);
assertCheck($paused['status'] === 'paused', "Subscription status updated to 'paused'");

$completed = $complianceService->updateClientService($subId, ['status' => 'completed']);
assertCheck($completed['status'] === 'completed', "Subscription status updated to 'completed'");

// Re-activate for work tracker test
$activeSub = $complianceService->updateClientService($subId, ['status' => 'active']);
assertCheck($activeSub['status'] === 'active', "Subscription reactivated to 'active'");

// 4. Compliance Details & Encryption at Rest
echo "\n--- 4. Testing Compliance Details & Encryption at Rest ---\n";
$secretPortalPass = 'GovPortalSecret#987!';

$savedCompliance = $complianceService->saveComplianceDetails($clientId, [
    'gstin' => '27AABCU9603R1ZM',
    'gst_filing_type' => 'monthly',
    'pan' => 'AABCU9603R',
    'tan' => 'MUMA12345B',
    'cin_llpin' => 'U72900MH2026PTC123456',
    'pf_esi_codes' => 'PF: MH/123 | ESI: 31001',
    'financial_year' => '2026-2027',
    'portal_notes' => 'Authorized signatory OTP linked to mobile 9988776655',
    'portal_credentials' => $secretPortalPass,
]);

assertCheck($savedCompliance['has_portal_credentials'] === true, "Compliance flags presence of credentials");
assertCheck($savedCompliance['portal_credentials'] === null, "Plain password is not exposed in default view");

// Verify raw DB storage
$rawDbPass = $pdo->query("SELECT portal_credentials_encrypted FROM client_compliance_details WHERE client_id = {$clientId}")->fetchColumn();
assertCheck(!empty($rawDbPass), "Encrypted credentials stored in DB");
assertCheck($rawDbPass !== $secretPortalPass, "DB credentials are NOT plain text");

$decodedPayload = base64_decode((string)$rawDbPass, true);
assertCheck($decodedPayload !== false && strlen($decodedPayload) >= 29, "DB credentials packed with AES-256-GCM (IV + Tag + Ciphertext)");

// 5. Reveal Credentials & Audit Log
echo "\n--- 5. Testing Credential Reveal & Access Control ---\n";

// Accountant can reveal
setUser($pdo, 'accountant');
$acctReveal = $complianceService->getComplianceDetails($clientId, true);
assertCheck($acctReveal['portal_credentials'] === $secretPortalPass, "Accountant successfully decrypted portal credentials");

// Admin can reveal
setUser($pdo, 'admin');
$adminReveal = $complianceService->getComplianceDetails($clientId, true);
assertCheck($adminReveal['portal_credentials'] === $secretPortalPass, "Admin successfully decrypted portal credentials");

// Verify audit log
$auditLog = $pdo->query("
    SELECT * FROM activity_log 
    WHERE entity_type = 'client_compliance' 
      AND entity_id = {$clientId} 
      AND action = 'reveal_portal_credential'
    ORDER BY id DESC LIMIT 1
")->fetch();
assertCheck(!empty($auditLog), "Audit log entry created for credential reveal");
assertCheck((int)$auditLog['user_id'] === $adminId, "Audit log records viewing user ID");

// Counselor forbidden from revealing
setUser($pdo, 'counselor');
$counselorBlocked = false;
try {
    $complianceService->getComplianceDetails($clientId, true);
} catch (RuntimeException $e) {
    $counselorBlocked = ($e->getCode() === 403);
}
assertCheck($counselorBlocked, "Counselor forbidden from revealing portal credentials (403)");

// Trainer forbidden from revealing
setUser($pdo, 'trainer');
$trainerBlocked = false;
try {
    $complianceService->getComplianceDetails($clientId, true);
} catch (RuntimeException $e) {
    $trainerBlocked = ($e->getCode() === 403);
}
assertCheck($trainerBlocked, "Trainer forbidden from revealing portal credentials (403)");

// 6. Work Tracker per Service Period
echo "\n--- 6. Testing Work Tracker Lifecycle ---\n";
setUser($pdo, 'accountant');

// Pending
$trackerItem = $complianceService->addWorkTrackerItem($clientId, $subId, [
    'period' => 'Sep-2026',
    'status' => 'pending',
    'notes' => 'Awaiting purchase/sales register',
]);
assertCheck(!empty($trackerItem['id']), "Work tracker item created");
$trackerId = (int)$trackerItem['id'];
assertCheck($trackerItem['status'] === 'pending', "Status is 'pending'");

// Data received
$dataRecv = $complianceService->updateWorkTrackerItem($trackerId, [
    'status' => 'data_received',
    'notes' => 'Books received',
]);
assertCheck($dataRecv['status'] === 'data_received', "Status updated to 'data_received'");

// Filed + ack number + date
$filed = $complianceService->updateWorkTrackerItem($trackerId, [
    'status' => 'filed',
    'acknowledgment_no' => 'ACK-GST-2026-SEP-001',
    'filing_date' => '2026-10-12',
]);
assertCheck($filed['status'] === 'filed', "Status updated to 'filed'");
assertCheck($filed['acknowledgment_no'] === 'ACK-GST-2026-SEP-001', "Acknowledgment number saved");
assertCheck($filed['filing_date'] === '2026-10-12', "Filing date saved");

// Acknowledged
$acked = $complianceService->updateWorkTrackerItem($trackerId, [
    'status' => 'acknowledged',
]);
assertCheck($acked['status'] === 'acknowledged', "Status updated to 'acknowledged'");

// List work tracker items
$list = $complianceService->listWorkTracker($clientId, $subId);
assertCheck(count($list) >= 1, "Work tracker list returns entries");
assertCheck($list[0]['period'] === 'Sep-2026', "Work tracker period is 'Sep-2026'");

echo "\n========================================\n";
echo "ALL PHASE 3 INTEGRATION CHECKS PASSED!\n";
echo "========================================\n";
exit(0);
