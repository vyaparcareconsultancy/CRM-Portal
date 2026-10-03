<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Helpers\Crypto;
use App\Models\Document;
use App\Models\Note;
use App\Models\Notification;
use App\Services\DocumentService;
use App\Services\TimelineService;

$pdo = Database::getConnection();

function assertTrue($cond, string $msg): void {
    if (!$cond) {
        echo "❌ FAILED: {$msg}\n";
        exit(1);
    }
    echo "✅ PASSED: {$msg}\n";
}

echo "=== PHASE 6 INTEGRATION TESTS: DOCUMENTS, TIMELINE & NOTES ===\n";

// 1. Setup Test Users: Admin, Accountant, Counselor, Trainer
$rolesStmt = $pdo->query("SELECT id, name FROM roles");
$rolesMap = [];
while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
    $rolesMap[$r['name']] = (int)$r['id'];
}

function getOrCreateUser(PDO $pdo, string $email, string $name, int $roleId): int {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }
    $ins = $pdo->prepare("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)");
    $ins->execute([$roleId, $name, $email, password_hash('Secret123456!', PASSWORD_BCRYPT)]);
    return (int)$pdo->lastInsertId();
}

$adminId = getOrCreateUser($pdo, 'phase6_admin@crm.local', 'Admin Phase6', $rolesMap['admin']);
$accountantId = getOrCreateUser($pdo, 'phase6_acc@crm.local', 'Accountant Phase6', $rolesMap['accountant']);
$counselorId = getOrCreateUser($pdo, 'phase6_counselor@crm.local', 'Rahul Counselor', $rolesMap['counselor']);
$trainerId = getOrCreateUser($pdo, 'phase6_trainer@crm.local', 'Trainer Phase6', $rolesMap['trainer']);

// 2. Setup Test Entities: Client, Lead, Student
$randSuffix = bin2hex(random_bytes(4));
$randMobile1 = (string)random_int(7000000000, 7999999999);
$randMobile2 = (string)random_int(8000000000, 8999999999);
$randMobile3 = (string)random_int(9000000000, 9999999999);

$pdo->prepare("INSERT INTO clients (client_code, name, email, mobile, client_type, status) VALUES (?, ?, ?, ?, 'individual', 'active')")
    ->execute(["CL-P6-{$randSuffix}", 'Rohan Sharma', "rohan_{$randSuffix}@example.com", $randMobile1]);
$clientId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO leads (lead_code, name, mobile, status, interested_in) VALUES (?, ?, ?, 'new', 'GST')")
    ->execute(["LD-P6-{$randSuffix}", 'Priya Lead', $randMobile2]);
$leadId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO students (student_code, name, mobile, email, status) VALUES (?, ?, ?, ?, 'active')")
    ->execute(["ST-P6-{$randSuffix}", 'Amit Student', $randMobile3, "amit_{$randSuffix}@student.local"]);
$studentId = (int)$pdo->lastInsertId();

echo "Entities created: Client {$clientId}, Lead {$leadId}, Student {$studentId}\n";

$docService = new DocumentService();
$timelineService = new TimelineService();

// =========================================================================
// TEST 1: Document Upload & Storage Outside Web Root with Encryption
// =========================================================================
echo "\n--- TEST 1: Document Upload & Encryption At Rest ---\n";

$plainPanText = "%PDF-1.4 PAN CARD DUMMY CONTENT 123456789";
$panDoc = $docService->uploadDocument([
    'entity_type' => 'client',
    'entity_id' => $clientId,
    'document_type' => 'pan',
    'title' => 'Personal PAN Card',
    'document_number' => 'ABCDE1234F',
    'financial_year' => '2025-26',
    'expiry_date' => '2035-12-31',
    'file_name' => 'client_pan.pdf',
    'mime_type' => 'application/pdf',
], $plainPanText, $adminId);

assertTrue($panDoc['document_type'] === 'pan', "Document type set to pan");
assertTrue($panDoc['document_number'] === 'ABCDE1234F', "PAN number normalized uppercase");
assertTrue((int)$panDoc['is_encrypted'] === 1, "PAN file marked as encrypted at rest in DB");
assertTrue($panDoc['financial_year'] === '2025-26', "Financial year saved");
assertTrue($panDoc['expiry_date'] === '2035-12-31', "Expiry date saved");

// Check file on disk outside web root is encrypted (not equal to plain text)
$storedPath = dirname(__DIR__) . "/storage/uploads/documents/client/{$clientId}/" . $panDoc['stored_name'];
assertTrue(file_exists($storedPath), "Document stored on disk outside web root at {$storedPath}");
$rawOnDisk = file_get_contents($storedPath);
assertTrue($rawOnDisk !== $plainPanText, "File on disk is encrypted at rest (binary does not match plaintext)");

// Download and verify transparent decryption
$downloaded = $docService->downloadDocument($panDoc['id'], $adminId, 'admin');
assertTrue($downloaded['content'] === $plainPanText, "Download transparently decrypts file content for authorized user");

// =========================================================================
// TEST 2: Aadhaar Number Masking & Admin/Accountant Strict Access Check
// =========================================================================
echo "\n--- TEST 2: Aadhaar Masking & Role Restriction ---\n";

$rawAadhaarNumber = "1234 5678 9012";
$plainAadhaarPdf = "%PDF-1.4 AADHAAR CARD BIOMETRIC CONTENT PRIVATE";
$aadhaarDoc = $docService->uploadDocument([
    'entity_type' => 'student',
    'entity_id' => $studentId,
    'document_type' => 'aadhaar',
    'title' => 'Student Aadhaar Proof',
    'document_number' => $rawAadhaarNumber,
    'file_name' => 'student_aadhaar.pdf',
    'mime_type' => 'application/pdf',
], $plainAadhaarPdf, $adminId);

// Check Masking in DB
assertTrue($aadhaarDoc['document_number'] === 'XXXX-XXXX-9012', "Aadhaar number stored strictly masked with last 4 digits only");
assertTrue(!str_contains($aadhaarDoc['document_number'], '1234'), "Full Aadhaar first digits never exist in DB");

// Check Admin Access -> 200
$adminDownload = $docService->downloadDocument($aadhaarDoc['id'], $adminId, 'admin');
assertTrue($adminDownload['content'] === $plainAadhaarPdf, "Admin can view/download Aadhaar file");

// Check Accountant Access -> 200
$accDownload = $docService->downloadDocument($aadhaarDoc['id'], $accountantId, 'accountant');
assertTrue($accDownload['content'] === $plainAadhaarPdf, "Accountant can view/download Aadhaar file");

// Check Counselor Access -> Must throw 403 Forbidden
$counselorBlocked = false;
try {
    $docService->downloadDocument($aadhaarDoc['id'], $counselorId, 'counselor');
} catch (RuntimeException $e) {
    if ($e->getCode() === 403) {
        $counselorBlocked = true;
    }
}
assertTrue($counselorBlocked, "Counselor access to Aadhaar file is strictly forbidden (403 Forbidden)");

// Check Trainer Access -> Must throw 403 Forbidden
$trainerBlocked = false;
try {
    $docService->downloadDocument($aadhaarDoc['id'], $trainerId, 'trainer');
} catch (RuntimeException $e) {
    if ($e->getCode() === 403) {
        $trainerBlocked = true;
    }
}
assertTrue($trainerBlocked, "Trainer access to Aadhaar file is strictly forbidden (403 Forbidden)");

// =========================================================================
// TEST 3: Audit Logging for Views & Downloads
// =========================================================================
echo "\n--- TEST 3: Audit Logging for Document Downloads & Views ---\n";

$auditStmt = $pdo->prepare("SELECT * FROM activity_log WHERE entity_type = 'document' AND entity_id = ? ORDER BY id DESC");
$auditStmt->execute([$aadhaarDoc['id']]);
$logs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

assertTrue(count($logs) >= 2, "Activity log records both upload and download events");
$hasDownloadLog = false;
foreach ($logs as $l) {
    if ($l['action'] === 'document_downloaded') {
        $hasDownloadLog = true;
        break;
    }
}
assertTrue($hasDownloadLog, "Audit log records document_downloaded with user and timestamp");

// =========================================================================
// TEST 4: Quick Add Note with Staff @mention & In-App Notification
// =========================================================================
echo "\n--- TEST 4: Quick Add Note with @mention Notifications ---\n";

// Clear previous test notifications
$pdo->exec("DELETE FROM notifications WHERE user_id = {$counselorId}");

$noteText = "Spoke to candidate regarding enrollment, @Rahul please review documents and finalize registration";
$addedNote = $timelineService->addNote('student', $studentId, $adminId, $noteText, true);

assertTrue($addedNote['is_pinned'] === true, "Note created with is_pinned = true");
assertTrue(count($addedNote['mentioned_users']) === 1, "Detected 1 mentioned staff member");
assertTrue($addedNote['mentioned_users'][0] === $counselorId, "Correctly resolved @Rahul to counselor user ID");

// Verify in-app notification in DB
$unreadNotifs = $timelineService->getUnreadNotifications($counselorId);
assertTrue(count($unreadNotifs) >= 1, "In-app notification created for mentioned staff member");
$firstNotif = $unreadNotifs[0];
assertTrue($firstNotif['type'] === 'mention', "Notification type is 'mention'");
assertTrue(str_contains($firstNotif['title'], 'mentioned you'), "Notification title indicates mention");
assertTrue((int)$firstNotif['is_read'] === 0, "Notification is unread");

// Mark as read
$marked = $timelineService->markNotificationAsRead($firstNotif['id'], $counselorId);
assertTrue($marked, "Notification marked as read successfully");

// Verify unread count decreases
$unreadAfter = $timelineService->getUnreadNotifications($counselorId);
assertTrue(count($unreadAfter) === 0, "Unread notifications list is now empty after marking read");

// =========================================================================
// TEST 5: Unified Timeline Aggregation & Filtering
// =========================================================================
echo "\n--- TEST 5: Unified Timeline Aggregation & Type Filtering ---\n";

// Add a follow-up for the client
$pdo->prepare("INSERT INTO follow_ups (client_id, type, status, outcome, remarks, due_at, user_id) VALUES (?, 'call', 'done', 'connected', 'Discussion on GST Audit', NOW(), ?)")
    ->execute([$clientId, $adminId]);

// Add an invoice and payment for the client
$pdo->prepare("INSERT INTO invoices (invoice_no, client_id, title, total_amount, net_amount, issue_date, due_date, status) VALUES (?, ?, 'GST Audit Fee', 5000.00, 5000.00, CURDATE(), CURDATE(), 'paid')")
    ->execute(["INV-P6-{$randSuffix}", $clientId]);
$invId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO payments (receipt_no, invoice_id, amount, payment_date, payment_mode, reference_no, received_by) VALUES (?, ?, 5000.00, CURDATE(), 'upi', 'UPI-REF-1234', ?)")
    ->execute(["RCP-P6-{$randSuffix}", $invId, $accountantId]);

// Add a note for client
$timelineService->addNote('client', $clientId, $adminId, "Client requested urgent filing before 20th", false);

// Fetch All Timeline Events
$allEvents = $timelineService->getTimeline('client', $clientId, 'all');
assertTrue(count($allEvents) >= 4, "Timeline aggregates notes, followups/calls, payments, and documents (total: " . count($allEvents) . ")");

// Verify types present in full timeline
$typesFound = array_unique(array_column($allEvents, 'type'));
assertTrue(in_array('note', $typesFound, true), "Timeline contains 'note' events");
assertTrue(in_array('call', $typesFound, true), "Timeline contains 'call' events");
assertTrue(in_array('payment', $typesFound, true), "Timeline contains 'payment' events");
assertTrue(in_array('document', $typesFound, true), "Timeline contains 'document' events");

// Test Filtering: Payments Only
$paymentEvents = $timelineService->getTimeline('client', $clientId, 'payment');
assertTrue(count($paymentEvents) >= 1, "Filtered timeline returns payments");
foreach ($paymentEvents as $pe) {
    assertTrue($pe['type'] === 'payment', "Every event in payment filter has type = 'payment'");
}

// Test Filtering: Notes Only
$noteEvents = $timelineService->getTimeline('client', $clientId, 'note');
assertTrue(count($noteEvents) >= 1, "Filtered timeline returns notes");
foreach ($noteEvents as $ne) {
    assertTrue($ne['type'] === 'note', "Every event in note filter has type = 'note'");
}

// Test Filtering: Calls Only
$callEvents = $timelineService->getTimeline('client', $clientId, 'call');
assertTrue(count($callEvents) >= 1, "Filtered timeline returns calls");
foreach ($callEvents as $ce) {
    assertTrue($ce['type'] === 'call', "Every event in call filter has type = 'call'");
}

// Test Newest First Sorting
for ($i = 0; $i < count($allEvents) - 1; $i++) {
    if (!$allEvents[$i]['is_pinned'] && !$allEvents[$i+1]['is_pinned']) {
        assertTrue(strcmp($allEvents[$i]['timestamp'], $allEvents[$i+1]['timestamp']) >= 0, "Events sorted newest first");
    }
}

echo "\n🎉 ALL PHASE 6 INTEGRATION TESTS COMPLETED SUCCESSFULLY!\n";
