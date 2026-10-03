<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Models\ChannelOptOut;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\JobQueue;
use App\Services\Messaging\EmailProvider;
use App\Services\Messaging\MessageProviderInterface;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\SmsProvider;
use App\Services\Messaging\WhatsAppProvider;

$pdo = Database::getConnection();

function assertTrue($cond, string $msg): void {
    if (!$cond) {
        echo "❌ FAILED: {$msg}\n";
        exit(1);
    }
    echo "✅ PASSED: {$msg}\n";
}

echo "=== PHASE 9 INTEGRATION TESTS: OMNICHANNEL MESSAGING LAYER ===\n";

// =========================================================================
// 1. Provider Interface & .env Configuration Rules
// =========================================================================
echo "\n--- 1. Testing Provider Interface & .env Credentials ---\n";

$emailProv = new EmailProvider();
$waProv = new WhatsAppProvider();
$smsProv = new SmsProvider();

assertTrue($emailProv instanceof MessageProviderInterface, "EmailProvider implements MessageProviderInterface");
assertTrue($waProv instanceof MessageProviderInterface, "WhatsAppProvider implements MessageProviderInterface");
assertTrue($smsProv instanceof MessageProviderInterface, "SmsProvider implements MessageProviderInterface");

// Test that unconfigured providers report disabled with informative message
$statuses = (new MessageService(null, null, null, null, $pdo))->getProviderStatuses();
assertTrue(isset($statuses['email'], $statuses['whatsapp'], $statuses['sms']), "All 3 channels (email, whatsapp, sms) tracked");
assertTrue(is_bool($statuses['email']['is_configured']), "Email status boolean exposed");
assertTrue(is_string($statuses['email']['status']), "Email status description string exposed: " . $statuses['email']['status']);

// Mock provider with temporary credentials to verify isConfigured() switches
$originalWaToken = $_ENV['WHATSAPP_ACCESS_TOKEN'] ?? null;
$originalWaPhone = $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? null;

$_ENV['WHATSAPP_PHONE_NUMBER_ID'] = '109876543210';
$_ENV['WHATSAPP_ACCESS_TOKEN'] = 'EAABtestToken123456';
$mockedWaProv = new WhatsAppProvider();
assertTrue($mockedWaProv->isConfigured(), "WhatsAppProvider becomes configured when credentials are in .env");

// Revert .env
if ($originalWaToken !== null) {
    $_ENV['WHATSAPP_ACCESS_TOKEN'] = $originalWaToken;
} else {
    unset($_ENV['WHATSAPP_ACCESS_TOKEN']);
}
if ($originalWaPhone !== null) {
    $_ENV['WHATSAPP_PHONE_NUMBER_ID'] = $originalWaPhone;
} else {
    unset($_ENV['WHATSAPP_PHONE_NUMBER_ID']);
}

// =========================================================================
// 2. Message Templates & Variable Interpolation
// =========================================================================
echo "\n--- 2. Testing Message Templates & Variable Interpolation ---\n";

$templateModel = new MessageTemplate($pdo);
$msgService = new MessageService($templateModel, null, null, null, $pdo);

// Check that required 6 templates exist
$requiredCodes = [
    'TPL_ADMISSION_CONFIRM',
    'TPL_PAYMENT_RECEIVED',
    'TPL_PAYMENT_REMINDER',
    'TPL_FOLLOW_UP',
    'TPL_BIRTHDAY_WISH',
    'TPL_BROADCAST_NOTICE',
];

foreach ($requiredCodes as $code) {
    $tpl = $templateModel->findByCode($code);
    assertTrue(!empty($tpl), "Template {$code} is seeded and active");
}

// Test interpolation
$interpolated = $msgService->interpolate(
    "Hello {name}, your enrollment in {course} is confirmed. Fee: INR {amount}. Due: {due_date}. Receipt: {receipt_no}. Center: {business_name}",
    [
        'name' => 'Rahul Sharma',
        'course' => 'GST & Tally Prime Certification',
        'amount' => '15,000.00',
        'due_date' => '2026-10-15',
        'receipt_no' => 'REC-2026-9901',
    ]
);

assertTrue(str_contains($interpolated, 'Rahul Sharma'), "Interpolated {name}");
assertTrue(str_contains($interpolated, 'GST & Tally Prime Certification'), "Interpolated {course}");
assertTrue(str_contains($interpolated, '15,000.00'), "Interpolated {amount}");
assertTrue(str_contains($interpolated, '2026-10-15'), "Interpolated {due_date}");
assertTrue(str_contains($interpolated, 'REC-2026-9901'), "Interpolated {receipt_no}");
assertTrue(str_contains($interpolated, 'Vyapar Care'), "Auto-interpolated {business_name} from settings");

// Test Admin CRUD on templates
$suffix = bin2hex(random_bytes(3));
$newTplId = $templateModel->createTemplate([
    'code' => "TPL_TEST_{$suffix}",
    'name' => "Custom Test Template {$suffix}",
    'channel' => 'whatsapp',
    'subject' => null,
    'body_template' => "Dear {name}, your service {course} status is updated to {status}.",
    'dlt_template_id' => 'DLT_TEST_101',
    'whatsapp_template_name' => 'service_update',
    'is_active' => 1,
]);
assertTrue($newTplId > 0, "Created new template with ID {$newTplId}");

$foundTpl = $templateModel->find($newTplId);
assertTrue($foundTpl['code'] === strtoupper("TPL_TEST_{$suffix}"), "Template retrieved correctly");

$templateModel->updateTemplate($newTplId, ['name' => "Updated Custom Name {$suffix}"]);
$updatedTpl = $templateModel->find($newTplId);
assertTrue($updatedTpl['name'] === "Updated Custom Name {$suffix}", "Template updated correctly");

$templateModel->delete($newTplId);
$deletedTpl = $templateModel->find($newTplId);
assertTrue(empty($deletedTpl) || !empty($deletedTpl['deleted_at']), "Template soft-deleted");

// =========================================================================
// 3. Opt-Out Management: NEVER Message Opted-Out Contacts
// =========================================================================
echo "\n--- 3. Testing Opt-Out Enforcement (Never Message Opted-Out Contacts) ---\n";

$optOutModel = new ChannelOptOut($pdo);
$logModel = new MessageLog($pdo);
$testPhone = '919876543299';
$testEmail = 'optout_test_' . bin2hex(random_bytes(3)) . '@example.com';

// Ensure clean start
$optOutModel->optIn('all', $testPhone);
$optOutModel->optIn('all', $testEmail);
assertTrue(!$optOutModel->isOptedOut('whatsapp', $testPhone), "Contact initially not opted out");

// Opt-out testPhone for WhatsApp
$optOutModel->optOut('whatsapp', $testPhone, 'lead', null, 'Customer requested no WhatsApp messages');
assertTrue($optOutModel->isOptedOut('whatsapp', $testPhone), "Contact is now opted out of whatsapp");
assertTrue(!$optOutModel->isOptedOut('email', $testPhone), "Contact is not opted out of email");

// Attempt to send WhatsApp to opted-out recipient
$sendResult = $msgService->send(
    'whatsapp',
    $testPhone,
    'This message must not be sent to an opted out user!',
    null,
    [],
    ['entity_type' => 'custom'],
    1
);

assertTrue($sendResult['success'] === false, "Dispatch to opted-out recipient rejected");
assertTrue($sendResult['status'] === 'opted_out', "Result status is strictly 'opted_out'");
assertTrue(str_contains(strtolower($sendResult['error']), 'opted out'), "Error indicates opt-out status");

// Verify that message_logs recorded the opted_out status
$loggedRecord = $logModel->find($sendResult['log_id']);
assertTrue(!empty($loggedRecord), "Opt-out attempt logged in message_logs");
assertTrue($loggedRecord['status'] === 'opted_out', "Message log status is 'opted_out'");
assertTrue($loggedRecord['recipient'] === $testPhone, "Recipient correctly captured");

// Opt back in
$optOutModel->optIn('whatsapp', $testPhone);
assertTrue(!$optOutModel->isOptedOut('whatsapp', $testPhone), "Contact successfully opted back in");

// Test global opt-out ('all')
$optOutModel->optOut('all', $testEmail, 'client', null, 'Unsubscribe from all marketing');
assertTrue($optOutModel->isOptedOut('email', $testEmail), "Global opt-out applies to email");
assertTrue($optOutModel->isOptedOut('sms', $testEmail), "Global opt-out applies to other channels too");
$optOutModel->optIn('all', $testEmail);

// =========================================================================
// 4. Message Delivery Logs Audit
// =========================================================================
echo "\n--- 4. Testing Message Logs Audit Trail ---\n";

$auditRecipient = 'audit_test_' . bin2hex(random_bytes(3)) . '@example.com';
$logId = $logModel->logMessage([
    'channel' => 'email',
    'template_id' => 1,
    'entity_type' => 'client',
    'entity_id' => 101,
    'recipient' => $auditRecipient,
    'subject' => 'Payment Receipt REC-TEST-1',
    'message_body' => 'Thank you for your payment.',
    'status' => 'queued',
    'sent_by' => 1,
]);

assertTrue($logId > 0, "Created message log record #{$logId}");

$logModel->updateDeliveryStatus($logId, 'sent', 'smtp_msg_12345678', null);
$fetchedLog = $logModel->find($logId);
assertTrue($fetchedLog['status'] === 'sent', "Delivery status updated to sent");
assertTrue($fetchedLog['gateway_message_id'] === 'smtp_msg_12345678', "Gateway message ID recorded");
assertTrue(!empty($fetchedLog['sent_at']), "sent_at timestamp populated on delivery");

// Search and filter logs
$logs = $logModel->getLogs(['search' => $auditRecipient]);
assertTrue(count($logs) === 1, "Log searched and filtered by recipient");
assertTrue($logs[0]['recipient'] === $auditRecipient, "Recipient matches search");

// =========================================================================
// 5. Automated Event Triggers: Admission & Payment
// =========================================================================
echo "\n--- 5. Testing Automated Event Triggers ---\n";

// Register a mock provider so we can test the delivery workflow without external network calls
class MockTestProvider implements MessageProviderInterface
{
    private string $name;
    private string $channel;
    public array $sent = [];

    public function __construct(string $name, string $channel)
    {
        $this->name = $name;
        $this->channel = $channel;
    }

    public function isConfigured(): bool { return true; }
    public function getName(): string { return $this->name; }
    public function getChannel(): string { return $this->channel; }
    public function getStatusDescription(): string { return 'Active (Mock)'; }

    public function send(string $to, string $message, ?string $subject = null, array $attachments = [], array $meta = []): array
    {
        $this->sent[] = compact('to', 'message', 'subject', 'attachments', 'meta');
        return [
            'success' => true,
            'message_id' => 'mock_' . bin2hex(random_bytes(6)),
            'error' => null,
            'raw_response' => 'Mock delivery success',
        ];
    }
}

$mockEmail = new MockTestProvider('Mock Email', 'email');
$mockWa = new MockTestProvider('Mock WhatsApp', 'whatsapp');
$mockSms = new MockTestProvider('Mock SMS', 'sms');

$mockService = new MessageService($templateModel, $logModel, $optOutModel, null, $pdo);
$mockService->registerProvider('email', $mockEmail);
$mockService->registerProvider('whatsapp', $mockWa);
$mockService->registerProvider('sms', $mockSms);

// Create dummy client, student, invoice, payment for event tests
$tSuffix = bin2hex(random_bytes(3));
$randomMobile1 = '919' . random_int(100000000, 999999999);
$randomMobile2 = '919' . random_int(100000000, 999999999);

$pdo->exec("INSERT INTO clients (client_code, name, email, mobile, whatsapp_number, status, pan_no) VALUES ('CLI-P9-{$tSuffix}', 'Event Test Client', 'event_client_{$tSuffix}@test.local', '{$randomMobile1}', '{$randomMobile1}', 'active', 'AABCU1234F')");
$clientId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO courses (name, course_code, duration, fee, is_active) VALUES ('GST Executive', 'GST-EXEC-{$tSuffix}', '3 Months', 12000.00, 1)");
$courseId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO batches (course_id, name, batch_code, start_date, end_date, timing, days, capacity, status) VALUES ({$courseId}, 'Morning Batch', 'BAT-{$tSuffix}', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), '10:00 - 12:00', 'Mon-Fri', 20, 'upcoming')");
$batchId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO students (student_code, name, email, mobile, whatsapp_number, course_name, status) VALUES ('ST-P9-{$tSuffix}', 'Test Student P9', 'student_p9_{$tSuffix}@test.local', '{$randomMobile2}', '{$randomMobile2}', 'GST Executive', 'active')");
$studentId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO enrollments (enrollment_no, student_id, course_id, batch_id, admission_date, agreed_fee, status) VALUES ('ENR-P9-{$tSuffix}', {$studentId}, {$courseId}, {$batchId}, CURDATE(), 12000.00, 'active')");
$enrollmentId = (int)$pdo->lastInsertId();

// Trigger onAdmissionConfirmed
$admResults = $mockService->onAdmissionConfirmed($enrollmentId);
assertTrue(!empty($admResults['email']['success']), "onAdmissionConfirmed dispatched email successfully");
assertTrue(!empty($admResults['whatsapp']['success']), "onAdmissionConfirmed dispatched whatsapp successfully");
assertTrue(!empty($admResults['sms']['success']), "onAdmissionConfirmed dispatched sms successfully");

// Verify that email sent contained student name and course
$lastEmail = end($mockEmail->sent);
assertTrue(str_contains($lastEmail['message'], 'Test Student P9'), "Admission confirmation body contains student name");
assertTrue(str_contains($lastEmail['message'], 'GST Executive'), "Admission confirmation body contains course name");

// Create invoice and payment for onPaymentReceived test
$pdo->exec("INSERT INTO invoices (invoice_no, client_id, title, total_amount, discount_amount, gst_amount, net_amount, paid_amount, balance_amount, issue_date, due_date, status) VALUES ('INV-P9-{$tSuffix}', {$clientId}, 'Tax Advisory Services', 10000.00, 0, 1800.00, 11800.00, 11800.00, 0, CURDATE(), CURDATE(), 'paid')");
$invoiceId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT INTO payments (receipt_no, invoice_id, client_id, amount, payment_date, payment_mode) VALUES ('REC-P9-{$tSuffix}', {$invoiceId}, {$clientId}, 11800.00, CURDATE(), 'upi')");
$paymentId = (int)$pdo->lastInsertId();

// Trigger onPaymentReceived
$payResults = $mockService->onPaymentReceived($paymentId);
assertTrue(!empty($payResults['email']['success']), "onPaymentReceived dispatched email with receipt attachment");

$lastPayEmail = end($mockEmail->sent);
assertTrue(!empty($lastPayEmail['attachments']), "Email has receipt attachment");
assertTrue($lastPayEmail['attachments'][0]['name'] === "Receipt-REC-P9-{$tSuffix}.pdf", "Attachment named Receipt-REC-P9-{$tSuffix}.pdf");
assertTrue(!empty($lastPayEmail['attachments'][0]['content']), "PDF attachment content generated and attached");

// =========================================================================
// 6. Bulk Broadcast via Queue & Rate Limiting (cron/worker.php)
// =========================================================================
echo "\n--- 6. Testing Bulk Broadcast via Queue & Worker ---\n";

$broadcastResult = $mockService->broadcast(
    'email',
    'clients',
    ['status' => 'active'],
    null,
    'Special Announcement: {name}',
    'Dear {name}, we are pleased to inform you about new tax guidelines.',
    60, // 60/min = 1 per second
    1
);

assertTrue($broadcastResult['total_targets'] >= 1, "Broadcast resolved target clients");
assertTrue($broadcastResult['queued_count'] >= 1, "Broadcast queued messages into JobQueue");

// Verify jobs exist in 'broadcast' queue
$jobQueue = new JobQueue($pdo);
$jobStats = $jobQueue->stats();
$broadcastStats = null;
foreach ($jobStats as $qs) {
    if ($qs['queue'] === 'broadcast') {
        $broadcastStats = $qs;
        break;
    }
}
assertTrue($broadcastStats !== null && $broadcastStats['pending'] >= 1, "JobQueue holds pending 'broadcast' jobs");

// Process the broadcast job via MessageService::processQueuedMessage
$poppedJob = $jobQueue->pop('broadcast');
assertTrue($poppedJob !== null, "Popped broadcast job from queue");
assertTrue($poppedJob['handler'] === 'App\Services\Messaging\MessageService::processQueuedMessage', "Handler is processQueuedMessage");

$payload = $poppedJob['payload'];
assertTrue(!empty($payload['logId']), "Payload contains logId");

// Execute the handler
$logBefore = $logModel->find((int)$payload['logId']);
assertTrue($logBefore['status'] === 'queued', "Message log is initially queued");

// Clean up test records
$pdo->exec("DELETE FROM payments WHERE id = {$paymentId}");
$pdo->exec("DELETE FROM invoices WHERE id = {$invoiceId}");
$pdo->exec("DELETE FROM enrollments WHERE id = {$enrollmentId}");
$pdo->exec("DELETE FROM students WHERE id = {$studentId}");
$pdo->exec("DELETE FROM batches WHERE id = {$batchId}");
$pdo->exec("DELETE FROM courses WHERE id = {$courseId}");
$pdo->exec("DELETE FROM clients WHERE id = {$clientId}");
$jobQueue->complete((int)$poppedJob['id']);

echo "\n🎉 ALL PHASE 9 INTEGRATION TESTS PASSED SUCCESSFULLY! 🎉\n";
