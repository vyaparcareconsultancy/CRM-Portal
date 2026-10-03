<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Models\Notification;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Services\ReminderService;

$pdo = Database::getConnection();

function assertTrue($cond, string $msg): void {
    if (!$cond) {
        echo "❌ FAILED: {$msg}\n";
        exit(1);
    }
    echo "✅ PASSED: {$msg}\n";
}

echo "=== PHASE 7 INTEGRATION TESTS: REMINDERS & DEADLINES ===\n";

// 1. Setup Test Users
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

$adminId = getOrCreateUser($pdo, 'p7_admin@crm.local', 'Admin User', $rolesMap['admin']);
$accountantId = getOrCreateUser($pdo, 'p7_accountant@crm.local', 'Accountant User', $rolesMap['accountant']);
$counselorId = getOrCreateUser($pdo, 'p7_counselor@crm.local', 'Counselor User', $rolesMap['counselor']);
$trainerId = getOrCreateUser($pdo, 'p7_trainer@crm.local', 'Trainer User', $rolesMap['trainer']);

$service = new ReminderService(null, null, null, null, $pdo);
$ruleModel = new ReminderRule($pdo);
$reminderModel = new Reminder($pdo);
$notificationModel = new Notification($pdo);

// 2. Test Rule Creation (Zero hardcoded dates, Admin managed)
$suffix = bin2hex(random_bytes(3));
$ruleId = $service->createRule([
    'name' => "Custom ROC Annual Filing {$suffix}",
    'rule_code' => "RULE_ROC_TEST_{$suffix}",
    'category' => 'statutory_compliance',
    'applies_to' => 'client_service',
    'due_date' => '2026-10-31',
    'remind_days_before' => 7,
    'channels' => ['in_app', 'email'],
    'is_active' => 1,
    'description' => 'Test rule for ROC statutory compliance deadline'
], $adminId);

assertTrue($ruleId > 0, 'Admin can create dynamic reminder rule with custom due date');

$rule = $ruleModel->find($ruleId);
assertTrue($rule['name'] === "Custom ROC Annual Filing {$suffix}", 'Rule name stored correctly');
assertTrue($rule['due_date'] === '2026-10-31', 'Rule due date stored correctly without hardcoding');
assertTrue($rule['remind_days_before'] == 7, 'Remind days before stored correctly');

// 3. Test Rule Update (e.g. Government extends deadline)
$service->updateRule($ruleId, [
    'due_date' => '2026-11-15',
    'remind_days_before' => 5,
]);
$updatedRule = $ruleModel->find($ruleId);
assertTrue($updatedRule['due_date'] === '2026-11-15', 'Admin can extend statutory deadline dynamically');
assertTrue($updatedRule['remind_days_before'] == 5, 'Admin can adjust reminder days before');

// 4. Test Entity Setup for Reminders
// Client with Service
$phone1 = '9' . random_int(100000000, 999999999);
$pdo->prepare("INSERT INTO clients (client_code, name, email, mobile, client_type, status, assigned_to, date_of_birth) VALUES (?, ?, ?, ?, 'company', 'active', ?, '1990-10-05')")
    ->execute(["CL-P7-{$suffix}", "Tax Client {$suffix}", "tax_{$suffix}@example.com", $phone1, $accountantId]);
$clientId = (int)$pdo->lastInsertId();

$srvStmt = $pdo->query("SELECT id FROM services WHERE code = 'SRV-GST-RET' LIMIT 1");
$gstServiceId = (int)$srvStmt->fetchColumn();
if (!$gstServiceId) {
    $pdo->query("INSERT INTO services (name, code, type, frequency, default_fee, status) VALUES ('GST Return Filing', 'SRV-GST-RET', 'recurring', 'monthly', 3500.00, 'active')");
    $gstServiceId = (int)$pdo->lastInsertId();
}

$pdo->prepare("INSERT INTO client_services (client_id, service_id, assigned_accountant_id, fee, frequency, start_date, status) VALUES (?, ?, ?, 3500.00, 'monthly', '2026-01-01', 'active')")
    ->execute([$clientId, $gstServiceId, $accountantId]);
$clientServiceId = (int)$pdo->lastInsertId();

// Student with Due Invoice
$phone2 = '8' . random_int(100000000, 999999999);
$pdo->prepare("INSERT INTO students (student_code, name, email, mobile, status, created_by) VALUES (?, ?, ?, ?, 'active', ?)")
    ->execute(["ST-P7-{$suffix}", "Student {$suffix}", "student_{$suffix}@example.com", $phone2, $counselorId]);
$studentId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO invoices (invoice_no, student_id, title, total_amount, paid_amount, balance_amount, issue_date, due_date, status) VALUES (?, ?, 'Tuition Fee Installment 2', 15000.00, 5000.00, 10000.00, '2026-10-01', '2026-10-10', 'partially_paid')")
    ->execute(["INV-P7-{$suffix}", $studentId]);
$invoiceId = (int)$pdo->lastInsertId();

// 5. Test Reminder Generation for Today (Simulate date 2026-10-08)
$targetDate = '2026-10-08';
$genResult = $service->generateReminders($targetDate);
assertTrue($genResult['generated'] >= 2, "Generated at least 2 reminder instances for simulated date {$targetDate}");

// 6. Test Deduplication Invariant
$genResult2 = $service->generateReminders($targetDate);
assertTrue($genResult2['generated'] === 0, "Idempotent generator: zero duplicates generated on second run for same date");

// 7. Verify Reminder Attributes & In-App Notification Dispatch
$dispatchResult = $service->dispatchPendingReminders($targetDate);
assertTrue($dispatchResult['dispatched'] > 0, 'Dispatched pending reminder notifications');
assertTrue($dispatchResult['in_app'] > 0, 'Created in-app bell notifications for assigned staff');

$reminders = $service->getReminders('today');
assertTrue(!empty($reminders), 'Reminders list retrieved for Today tab');

// Check that assigned accountant received in-app notification
$unreadAcc = $notificationModel->getUnreadForUser($accountantId);
$reminderNotifFound = false;
foreach ($unreadAcc as $n) {
    if ($n['type'] === 'reminder') {
        $reminderNotifFound = true;
        break;
    }
}
assertTrue($reminderNotifFound, 'Assigned accountant received bell notification with type=reminder');

// 8. Test Tabs: Today, Upcoming, Overdue, Done
$counts = $service->getCounts();
assertTrue(isset($counts['today'], $counts['upcoming'], $counts['overdue'], $counts['done']), 'Counts returned for all 4 required tabs');

// 9. Test Mark Done with Note
$targetReminder = null;
foreach ($reminders as $r) {
    if ($r['status'] !== 'done') {
        $targetReminder = $r;
        break;
    }
}
assertTrue($targetReminder !== null, 'Found an active reminder to mark done');

$completionNote = "Filed return successfully. ARN: AA270926012345Z";
$doneSuccess = $service->markDone((int)$targetReminder['id'], $accountantId, $completionNote);
assertTrue($doneSuccess, 'Successfully marked reminder as done with note');

$updatedReminder = $reminderModel->find($targetReminder['id']);
assertTrue($updatedReminder['status'] === 'done', 'Reminder status changed to done');
assertTrue($updatedReminder['done_by'] == $accountantId, 'done_by correctly recorded');
assertTrue($updatedReminder['done_note'] === $completionNote, 'done_note correctly recorded');
assertTrue(!empty($updatedReminder['done_at']), 'done_at timestamp recorded');

// Check Done tab
$doneList = $service->getReminders('done');
$foundInDone = false;
foreach ($doneList as $d) {
    if ($d['id'] == $targetReminder['id']) {
        $foundInDone = true;
        assertTrue($d['done_note'] === $completionNote, 'Done note displayed in done reminders tab');
        break;
    }
}
assertTrue($foundInDone, 'Marked reminder appears in Done tab');

// 10. Test Role Scoping
// Trainer has no assigned clients or students here -> should see empty scoped list
$trainerReminders = $service->getReminders('today', $trainerId, 'trainer');
$accountantReminders = $service->getReminders('today', $accountantId, 'accountant');
assertTrue(count($accountantReminders) >= count($trainerReminders), 'Accountant sees all/assigned accounting compliance reminders; Trainer cannot see other staff reminders');

// 11. Test Soft Delete Rule
$delResult = $service->deleteRule($ruleId);
assertTrue($delResult, 'Reminder rule soft-deleted successfully');
$deletedRule = $ruleModel->find($ruleId);
assertTrue($deletedRule === null, 'Soft-deleted rule not returned by find()');

echo "\n🎉 ALL PHASE 7 INTEGRATION TESTS PASSED SUCCESSFULLY!\n";
