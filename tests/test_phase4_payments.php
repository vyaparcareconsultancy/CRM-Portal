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
use App\Helpers\IndianNumberToWords;
use App\Helpers\PdfReceipt;
use App\Models\Setting;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\PermissionService;

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
echo "Testing Phase 4: Invoices, Payments, Installments & Receipts\n";
echo "========================================\n\n";

$pdo = Database::getConnection();
$invoiceService = new InvoiceService();
$paymentService = new PaymentService();

$adminId = setUser($pdo, 'admin');
$accountantId = setUser($pdo, 'accountant');
$counselorId = setUser($pdo, 'counselor');
$trainerId = setUser($pdo, 'trainer');

// Setup test client & student
setUser($pdo, 'admin');
$clientStmt = $pdo->query("SELECT id FROM clients WHERE client_code = 'CL-PHASE4-TEST' LIMIT 1");
$clientId = (int)$clientStmt->fetchColumn();
if (!$clientId) {
    $pdo->prepare("
        INSERT INTO clients (client_code, name, email, mobile, assigned_to, created_by, status)
        VALUES ('CL-PHASE4-TEST', 'Phase 4 Logistics Pvt Ltd', 'phase4@test.local', '9911882233', ?, ?, 'active')
    ")->execute([$accountantId, $adminId]);
    $clientId = (int)$pdo->lastInsertId();
}

$studentStmt = $pdo->query("SELECT id FROM students WHERE student_code = 'ST-PHASE4-TEST' LIMIT 1");
$studentId = (int)$studentStmt->fetchColumn();
if (!$studentId) {
    $pdo->prepare("
        INSERT INTO students (student_code, name, email, mobile, course_name, created_by, status)
        VALUES ('ST-PHASE4-TEST', 'Simran Kaur', 'simran@test.local', '9922883344', 'Tax Consultant Certification', ?, 'enrolled')
    ")->execute([$adminId]);
    $studentId = (int)$pdo->lastInsertId();
}

// 1. Test Invoice Creation & Accurate Calculations
echo "--- 1. Testing Invoice Creation & Math (DECIMAL 12,2) ---\n";
setUser($pdo, 'accountant');

$gross = 20000.00;
$discount = 2000.00;
$gstRate = 18.00; // Taxable = 18000, GST = 3240, Net = 21240.00
$expectedNet = 21240.00;

$inv = $invoiceService->createInvoice([
    'client_id' => $clientId,
    'title' => 'Yearly Corporate Tax Audit & Filings',
    'total_amount' => $gross,
    'discount_amount' => $discount,
    'gst_rate_pct' => $gstRate,
    'issue_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
]);

assertCheck(!empty($inv['id']), "Invoice created successfully");
$invId = (int)$inv['id'];
assertCheck((float)$inv['total_amount'] === $gross, "Gross amount matches");
assertCheck((float)$inv['discount_amount'] === $discount, "Discount amount matches");
assertCheck((float)$inv['gst_amount'] === 3240.00, "GST amount accurately calculated as ₹3,240.00");
assertCheck((float)$inv['net_amount'] === $expectedNet, "Net payable accurately calculated as ₹21,240.00");
assertCheck($inv['status'] === 'unpaid', "Initial status is 'unpaid'");
assertCheck((float)$inv['balance_amount'] === $expectedNet, "Initial balance due equals net amount");

// 2. Test Multiple Partial Payments & Auto Status Transitions
echo "\n--- 2. Testing Multiple Partial Payments & Auto Status Transitions ---\n";

// Payment 1: 10,000.00 via Bank Transfer
$p1 = $paymentService->recordPayment([
    'invoice_id' => $invId,
    'amount' => 10000.00,
    'payment_date' => date('Y-m-d'),
    'payment_mode' => 'bank_transfer',
    'reference_no' => 'NEFT-AXIS-00192',
]);
assertCheck(!empty($p1['id']), "Payment 1 recorded");
$invCheck1 = $invoiceService->getInvoice($invId);
assertCheck($invCheck1['status'] === 'partially_paid', "Status transitioned to 'partially_paid'");
assertCheck((float)$invCheck1['paid_amount'] === 10000.00, "Paid amount updated to ₹10,000.00");
assertCheck((float)$invCheck1['balance_amount'] === 11240.00, "Balance due updated to ₹11,240.00");

// Payment 2: 11,240.00 via UPI (Full remaining payment)
$p2 = $paymentService->recordPayment([
    'invoice_id' => $invId,
    'amount' => 11240.00,
    'payment_date' => date('Y-m-d'),
    'payment_mode' => 'upi',
    'reference_no' => 'UPI/AXIS/11240',
]);
assertCheck(!empty($p2['id']), "Payment 2 recorded");
$invCheck2 = $invoiceService->getInvoice($invId);
assertCheck($invCheck2['status'] === 'paid', "Status transitioned to 'paid'");
assertCheck((float)$invCheck2['paid_amount'] === $expectedNet, "Paid amount equals net amount");
assertCheck((float)$invCheck2['balance_amount'] === 0.00, "Balance due is ₹0.00");

// 3. Test Course Fee Installment Plans
echo "\n--- 3. Testing Course Fee Installment Plans ---\n";
$courseTotal = 24000.00;
$dueDate1 = date('Y-m-d');
$dueDate2 = date('Y-m-d', strtotime('+30 days'));

$courseInv = $invoiceService->createInvoice([
    'student_id' => $studentId,
    'title' => 'Advanced Accounting & GST Diploma Fee',
    'total_amount' => $courseTotal,
    'discount_amount' => 0.00,
    'gst_rate_pct' => 0.00,
    'issue_date' => $dueDate1,
    'due_date' => $dueDate2,
    'installments' => [
        ['due_date' => $dueDate1, 'amount' => 12000.00, 'notes' => 'Installment 1 of 2'],
        ['due_date' => $dueDate2, 'amount' => 12000.00, 'notes' => 'Installment 2 of 2'],
    ],
]);
$courseInvId = (int)$courseInv['id'];
assertCheck(count($courseInv['installments']) === 2, "2 installments configured");
assertCheck($courseInv['installments'][0]['status'] === 'pending', "Installment 1 starts pending");

// Pay 18,000: Installment 1 fully paid (12k), Installment 2 partially paid (6k of 12k)
$paymentService->recordPayment([
    'invoice_id' => $courseInvId,
    'amount' => 18000.00,
    'payment_mode' => 'cheque',
    'reference_no' => 'CHQ-550101',
]);
$updatedCourseInv = $invoiceService->getInvoice($courseInvId);
assertCheck($updatedCourseInv['installments'][0]['status'] === 'paid', "Installment 1 is 'paid' (12,000 / 12,000)");
assertCheck((float)$updatedCourseInv['installments'][0]['paid_amount'] === 12000.00, "Installment 1 paid amount matches");
assertCheck($updatedCourseInv['installments'][1]['status'] === 'partially_paid', "Installment 2 is 'partially_paid' (6,000 / 12,000)");
assertCheck((float)$updatedCourseInv['installments'][1]['paid_amount'] === 6000.00, "Installment 2 paid amount is ₹6,000.00");

// 4. Test Receipt Series (Configurable Prefix, Never Reused)
echo "\n--- 4. Testing Receipt Series & Numbering ---\n";
Setting::set('receipt_prefix', 'VCR');

$dummyInv = $invoiceService->createInvoice([
    'client_id' => $clientId,
    'title' => 'Series Verification Fee',
    'total_amount' => 5000.00,
    'due_date' => date('Y-m-d'),
]);
$dId = (int)$dummyInv['id'];

$recA = $paymentService->recordPayment(['invoice_id' => $dId, 'amount' => 1000.00, 'payment_mode' => 'cash']);
$recB = $paymentService->recordPayment(['invoice_id' => $dId, 'amount' => 1000.00, 'payment_mode' => 'cash']);
assertCheck(str_starts_with($recA['receipt_no'], 'VCR-'), "Receipt uses custom prefix 'VCR'");
assertCheck($recA['receipt_no'] !== $recB['receipt_no'], "Receipt numbers are unique");

// Soft delete recB
$paymentService->deletePayment((int)$recB['id']);

// Issue recC: must NOT reuse recB's number
$recC = $paymentService->recordPayment(['invoice_id' => $dId, 'amount' => 1000.00, 'payment_mode' => 'cash']);
assertCheck($recC['receipt_no'] !== $recB['receipt_no'], "Receipt number is NEVER reused even after deletion");
Setting::set('receipt_prefix', 'REC');

// 5. Test Indian Amount to Words & PDF Generation
echo "\n--- 5. Testing Amount in Words & PDF Generation ---\n";
$inWords = IndianNumberToWords::toIndianRupees(21240.00);
assertCheck($inWords === 'Twenty-One Thousand Two Hundred Forty Rupees Only', "Indian words converter: {$inWords}");

$pdf = $paymentService->generateReceiptPdf((int)$p1['id']);
assertCheck(str_starts_with($pdf, '%PDF-1.4'), "Receipt PDF is valid PDF 1.4 binary");
assertCheck(str_ends_with(trim($pdf), '%%EOF'), "Receipt PDF ends with EOF");
assertCheck(str_contains($pdf, $p1['receipt_no']), "PDF contains receipt number");
assertCheck(str_contains($pdf, 'Ten Thousand Rupees Only'), "PDF contains amount in words");

// 6. Test Role Scoping & Activity Logging
echo "\n--- 6. Testing Role Permissions & Audit Logging ---\n";

// Counselor cannot record payments
setUser($pdo, 'counselor');
$counselorRejected = false;
try {
    $paymentService->recordPayment(['invoice_id' => $invId, 'amount' => 500.00]);
} catch (RuntimeException $e) {
    $counselorRejected = ($e->getCode() === 403);
}
assertCheck($counselorRejected, "Counselor cannot record payments (403)");

// Trainer cannot view payments
setUser($pdo, 'trainer');
$trainerRejected = false;
try {
    $paymentService->listPayments();
} catch (RuntimeException $e) {
    $trainerRejected = ($e->getCode() === 403);
}
assertCheck($trainerRejected, "Trainer cannot view payments (403)");

// Verify activity log entry exists for payment creation
$audit = $pdo->query("SELECT * FROM activity_log WHERE entity_type = 'payment' AND action = 'create' LIMIT 1")->fetch();
assertCheck(!empty($audit), "Payment creation logged in activity_log");

// 7. Test Financial Ledgers
echo "\n--- 7. Testing Client & Student Ledgers ---\n";
setUser($pdo, 'accountant');

$clientLedger = $paymentService->getClientLedger($clientId);
assertCheck(!empty($clientLedger['invoices']), "Client ledger includes invoices");
assertCheck(!empty($clientLedger['payments']), "Client ledger includes payments");
assertCheck(isset($clientLedger['summary']['total_invoiced']), "Client ledger includes total_invoiced");
assertCheck(isset($clientLedger['summary']['balance_due']), "Client ledger includes balance_due");

$studentLedger = $paymentService->getStudentLedger($studentId);
assertCheck(!empty($studentLedger['invoices']), "Student ledger includes invoices with installments");
assertCheck(!empty($studentLedger['payments']), "Student ledger includes payments");

echo "\n========================================\n";
echo "ALL PHASE 4 INTEGRATION CHECKS PASSED!\n";
echo "========================================\n";
exit(0);
