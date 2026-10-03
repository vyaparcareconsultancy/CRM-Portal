<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Helpers\IndianNumberToWords;
use App\Helpers\PdfReceipt;
use App\Models\Setting;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\PermissionService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PaymentBillingTest extends TestCase
{
    private PDO $pdo;
    private InvoiceService $invoiceService;
    private PaymentService $paymentService;

    private int $adminId;
    private int $accountantId;
    private int $counselorId;
    private int $trainerId;
    private int $testClientId;
    private int $testStudentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = DatabaseResetter::reset(true) ?? Database::getConnection();

        $this->invoiceService = new InvoiceService();
        $this->paymentService = new PaymentService();

        // Retrieve seeded user IDs
        $this->adminId = (int)$this->pdo->query("SELECT id FROM users WHERE email = 'admin@crm.local'")->fetchColumn();
        $this->accountantId = (int)$this->pdo->query("SELECT id FROM users WHERE email = 'accountant@crm.local'")->fetchColumn();
        $this->counselorId = (int)$this->pdo->query("SELECT id FROM users WHERE email = 'counselor@crm.local'")->fetchColumn();
        $this->trainerId = (int)$this->pdo->query("SELECT id FROM users WHERE email = 'trainer@crm.local'")->fetchColumn();

        // Create or get a test client
        $clientId = $this->pdo->query("SELECT id FROM clients LIMIT 1")->fetchColumn();
        if (!$clientId) {
            $insert = $this->pdo->prepare("
                INSERT INTO clients (client_code, name, email, mobile, assigned_to, created_by, status)
                VALUES ('CL-BILLING-01', 'Apex Corp', 'apex@billing.local', '9811223344', ?, ?, 'active')
            ");
            $insert->execute([$this->accountantId ?: $this->adminId, $this->adminId]);
            $clientId = (int)$this->pdo->lastInsertId();
        }
        $this->testClientId = (int)$clientId;

        // Create or get a test student
        $studentId = $this->pdo->query("SELECT id FROM students LIMIT 1")->fetchColumn();
        if (!$studentId) {
            $insertStudent = $this->pdo->prepare("
                INSERT INTO students (student_code, name, email, mobile, course_name, created_by, status)
                VALUES ('ST-BILLING-01', 'Arjun Verma', 'arjun@student.local', '9822334455', 'GST & Tally Expert', ?, 'enrolled')
            ");
            $insertStudent->execute([$this->adminId]);
            $studentId = (int)$this->pdo->lastInsertId();
        }
        $this->testStudentId = (int)$studentId;
    }

    private function setUserSession(int $userId, string $roleName): void
    {
        Session::start();
        Session::set('user_id', $userId);
        Session::set('user_role', $roleName);

        $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $stmt->execute([$roleName]);
        $roleId = (int)$stmt->fetchColumn();
        Session::set('role_id', $roleId);

        PermissionService::refresh();
    }

    /**
     * Requirement: Invoice/fee record for client service:
     * total amount, discount, GST % (optional), net amount, due date.
     * Use DECIMAL(12,2) precision, never float rounding leakage.
     */
    public function testInvoiceCreationAndFinancialCalculations(): void
    {
        $this->setUserSession($this->accountantId, 'accountant');

        $totalGross = 10000.00;
        $discount = 1000.00;
        $gstRate = 18.00;
        // Taxable = 10000 - 1000 = 9000
        // GST = 9000 * 0.18 = 1620
        // Net = 9000 + 1620 = 10620.00
        $expectedNet = 10620.00;

        $invoice = $this->invoiceService->createInvoice([
            'client_id' => $this->testClientId,
            'title' => 'Quarterly GST Compliance & Bookkeeping',
            'total_amount' => $totalGross,
            'discount_amount' => $discount,
            'gst_rate_pct' => $gstRate,
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')),
        ]);

        $this->assertNotEmpty($invoice['id']);
        $this->assertSame('unpaid', $invoice['status']);
        $this->assertEquals(10000.00, (float)$invoice['total_amount']);
        $this->assertEquals(1000.00, (float)$invoice['discount_amount']);
        $this->assertEquals(18.00, (float)$invoice['gst_rate_pct']);
        $this->assertEquals(1620.00, (float)$invoice['gst_amount']);
        $this->assertEquals($expectedNet, (float)$invoice['net_amount']);
        $this->assertEquals(0.00, (float)$invoice['paid_amount']);
        $this->assertEquals($expectedNet, (float)$invoice['balance_amount']);
        $this->assertStringStartsWith('INV-', $invoice['invoice_no']);
    }

    /**
     * Requirement: Auto status: Unpaid / Partially paid / Paid / Overdue. Due = net − paid.
     * When due_date is in the past and unpaid, status must be Overdue.
     */
    public function testOverdueStatusComputation(): void
    {
        $this->setUserSession($this->accountantId, 'accountant');

        $pastDueDate = date('Y-m-d', strtotime('-5 days'));
        $invoice = $this->invoiceService->createInvoice([
            'client_id' => $this->testClientId,
            'title' => 'Past Due Statutory Filing',
            'total_amount' => 5000.00,
            'discount_amount' => 0.00,
            'gst_rate_pct' => 0.00,
            'issue_date' => date('Y-m-d', strtotime('-10 days')),
            'due_date' => $pastDueDate,
        ]);

        $this->assertSame('overdue', $invoice['status'], 'Invoice with past due date and 0 payments must be marked overdue');
        $this->assertEquals(5000.00, (float)$invoice['balance_amount']);
    }

    /**
     * Requirement: Multiple partial payments per invoice: amount, date, mode (Cash, UPI, Bank transfer, Cheque, Card), reference no., received by.
     * Auto status: Unpaid -> Partially paid -> Paid. Due = net - paid.
     */
    public function testMultiplePartialPaymentsAndAutoStatusTransitions(): void
    {
        $this->setUserSession($this->accountantId, 'accountant');

        // Create Invoice: Net = 11,800.00 (10000 + 18% GST)
        $invoice = $this->invoiceService->createInvoice([
            'client_id' => $this->testClientId,
            'title' => 'Tax Audit & Accounting Retainer',
            'total_amount' => 10000.00,
            'discount_amount' => 0.00,
            'gst_rate_pct' => 18.00,
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
        ]);
        $invoiceId = (int)$invoice['id'];
        $this->assertSame('unpaid', $invoice['status']);
        $this->assertEquals(11800.00, (float)$invoice['balance_amount']);

        // 1. Partial Payment 1: 4,000.00 via UPI
        $pmt1 = $this->paymentService->recordPayment([
            'invoice_id' => $invoiceId,
            'amount' => 4000.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'upi',
            'reference_no' => 'UPI/98877665/TXN',
            'received_by' => $this->accountantId,
        ]);
        $this->assertNotEmpty($pmt1['id']);
        $this->assertStringStartsWith('REC-', $pmt1['receipt_no']);

        // Check Invoice after Payment 1
        $invAfter1 = $this->invoiceService->getInvoice($invoiceId);
        $this->assertSame('partially_paid', $invAfter1['status']);
        $this->assertEquals(4000.00, (float)$invAfter1['paid_amount']);
        $this->assertEquals(7800.00, (float)$invAfter1['balance_amount']);

        // 2. Partial Payment 2: 5,000.00 via Bank Transfer
        $pmt2 = $this->paymentService->recordPayment([
            'invoice_id' => $invoiceId,
            'amount' => 5000.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'bank_transfer',
            'reference_no' => 'NEFT-HDFC-99112233',
            'received_by' => $this->accountantId,
        ]);

        // Check Invoice after Payment 2
        $invAfter2 = $this->invoiceService->getInvoice($invoiceId);
        $this->assertSame('partially_paid', $invAfter2['status']);
        $this->assertEquals(9000.00, (float)$invAfter2['paid_amount']);
        $this->assertEquals(2800.00, (float)$invAfter2['balance_amount']);

        // 3. Final Payment 3: 2,800.00 via Cash
        $pmt3 = $this->paymentService->recordPayment([
            'invoice_id' => $invoiceId,
            'amount' => 2800.00,
            'payment_date' => date('Y-m-d'),
            'payment_mode' => 'cash',
            'received_by' => $this->accountantId,
        ]);

        // Check Invoice after Final Payment
        $invAfter3 = $this->invoiceService->getInvoice($invoiceId);
        $this->assertSame('paid', $invAfter3['status']);
        $this->assertEquals(11800.00, (float)$invAfter3['paid_amount']);
        $this->assertEquals(0.00, (float)$invAfter3['balance_amount']);
    }

    /**
     * Requirement: Installment plans for course fees.
     */
    public function testInstallmentPlansForCourseFees(): void
    {
        $this->setUserSession($this->accountantId, 'accountant');

        // Create student course fee invoice with 3 installments
        $courseFee = 30000.00;
        $date1 = date('Y-m-d');
        $date2 = date('Y-m-d', strtotime('+30 days'));
        $date3 = date('Y-m-d', strtotime('+60 days'));

        $invoice = $this->invoiceService->createInvoice([
            'student_id' => $this->testStudentId,
            'title' => 'Master Diploma in GST & Business Accounting',
            'total_amount' => $courseFee,
            'discount_amount' => 0.00,
            'gst_rate_pct' => 0.00,
            'issue_date' => $date1,
            'due_date' => $date3,
            'installments' => [
                ['due_date' => $date1, 'amount' => 10000.00, 'notes' => 'Admission Installment'],
                ['due_date' => $date2, 'amount' => 10000.00, 'notes' => 'Mid-term Installment'],
                ['due_date' => $date3, 'amount' => 10000.00, 'notes' => 'Final Installment'],
            ],
        ]);
        $invoiceId = (int)$invoice['id'];

        $this->assertCount(3, $invoice['installments']);
        $this->assertSame('pending', $invoice['installments'][0]['status']);
        $this->assertSame('pending', $invoice['installments'][1]['status']);
        $this->assertSame('pending', $invoice['installments'][2]['status']);

        // Make payment of 15,000.00:
        // Installment 1 (10,000) should be fully paid.
        // Installment 2 (10,000) should be partially paid with 5,000 paid.
        // Installment 3 (10,000) should remain pending with 0 paid.
        $this->paymentService->recordPayment([
            'invoice_id' => $invoiceId,
            'amount' => 15000.00,
            'payment_date' => $date1,
            'payment_mode' => 'upi',
            'reference_no' => 'UPI/INST/15000',
        ]);

        $updatedInv = $this->invoiceService->getInvoice($invoiceId);
        $insts = $updatedInv['installments'];

        $this->assertSame('paid', $insts[0]['status']);
        $this->assertEquals(10000.00, (float)$insts[0]['paid_amount']);

        $this->assertSame('partially_paid', $insts[1]['status']);
        $this->assertEquals(5000.00, (float)$insts[1]['paid_amount']);

        $this->assertSame('pending', $insts[2]['status']);
        $this->assertEquals(0.00, (float)$insts[2]['paid_amount']);
    }

    /**
     * Requirement: Receipt number series (configurable prefix, never reused).
     */
    public function testReceiptNumberSeriesNeverReused(): void
    {
        $this->setUserSession($this->adminId, 'admin');

        // Set custom prefix in settings
        Setting::set('receipt_prefix', 'VCARE');

        $invoice = $this->invoiceService->createInvoice([
            'client_id' => $this->testClientId,
            'title' => 'Series Test Invoice',
            'total_amount' => 10000.00,
            'due_date' => date('Y-m-d'),
        ]);
        $invoiceId = (int)$invoice['id'];

        // Record Payment 1
        $p1 = $this->paymentService->recordPayment([
            'invoice_id' => $invoiceId,
            'amount' => 1000.00,
            'payment_mode' => 'cash',
        ]);
        $this->assertStringStartsWith('VCARE-', $p1['receipt_no']);
        $p1ReceiptNo = $p1['receipt_no'];

        // Record Payment 2
        $p2 = $this->paymentService->recordPayment([
            'invoice_id' => $invoiceId,
            'amount' => 1000.00,
            'payment_mode' => 'cash',
        ]);
        $p2ReceiptNo = $p2['receipt_no'];
        $this->assertNotEquals($p1ReceiptNo, $p2ReceiptNo);

        // Soft delete Payment 2
        $this->paymentService->deletePayment((int)$p2['id']);

        // Record Payment 3: It must NEVER reuse Payment 2's receipt number!
        $p3 = $this->paymentService->recordPayment([
            'invoice_id' => $invoiceId,
            'amount' => 1000.00,
            'payment_mode' => 'cash',
        ]);
        $p3ReceiptNo = $p3['receipt_no'];

        $this->assertNotEquals($p2ReceiptNo, $p3ReceiptNo, 'Receipt number series must NEVER reuse deleted receipt numbers');

        // Reset prefix back to REC
        Setting::set('receipt_prefix', 'REC');
    }

    /**
     * Requirement: Amount in words (Indian format).
     */
    public function testAmountInWordsIndianFormat(): void
    {
        $this->assertSame('Fifteen Thousand Rupees Only', IndianNumberToWords::toIndianRupees(15000));
        $this->assertSame('One Lakh Twenty-Five Thousand Four Hundred Fifty Rupees and Fifty Paise Only', IndianNumberToWords::toIndianRupees(125450.50));
        $this->assertSame('One Crore Rupees Only', IndianNumberToWords::toIndianRupees(10000000));
        $this->assertSame('Four Hundred Fifty Rupees Only', IndianNumberToWords::toIndianRupees(450));
        $this->assertSame('Zero Rupees Only', IndianNumberToWords::toIndianRupees(0));
    }

    /**
     * Requirement: Receipt PDF per payment with business name/logo/address from Settings,
     * receipt number series, amount in words.
     */
    public function testPdfReceiptGeneration(): void
    {
        $this->setUserSession($this->accountantId, 'accountant');

        $invoice = $this->invoiceService->createInvoice([
            'client_id' => $this->testClientId,
            'title' => 'ROC Compliance Filing',
            'total_amount' => 6000.00,
            'due_date' => date('Y-m-d'),
        ]);

        $payment = $this->paymentService->recordPayment([
            'invoice_id' => (int)$invoice['id'],
            'amount' => 6000.00,
            'payment_mode' => 'bank_transfer',
            'reference_no' => 'IMPS/998877/ROC',
        ]);

        $pdfBytes = $this->paymentService->generateReceiptPdf((int)$payment['id']);

        $this->assertNotEmpty($pdfBytes);
        $this->assertStringStartsWith('%PDF-1.4', $pdfBytes, 'Generated document must be a valid PDF 1.4 binary');
        $this->assertStringEndsWith('%%EOF', trim($pdfBytes), 'PDF must terminate with %%EOF marker');

        // Check presence of key contents in PDF stream
        $this->assertStringContainsString($payment['receipt_no'], $pdfBytes);
        $this->assertStringContainsString('OFFICIAL PAYMENT RECEIPT', $pdfBytes);
        $this->assertStringContainsString('Six Thousand Rupees Only', $pdfBytes);
    }

    /**
     * Requirement: Only Admin/Accountant can add/edit; edits and deletes are logged, deletes are soft.
     * Trainer cannot see payments or fees.
     */
    public function testRoleScopingAndActivityLogging(): void
    {
        // 1. Counselor cannot record payments
        $this->setUserSession($this->counselorId, 'counselor');

        $counselorBlocked = false;
        try {
            $this->paymentService->recordPayment([
                'invoice_id' => 1,
                'amount' => 500.00,
            ]);
        } catch (RuntimeException $e) {
            $counselorBlocked = ($e->getCode() === 403);
        }
        $this->assertTrue($counselorBlocked, 'Counselor must be blocked from recording payments (403)');

        // 2. Trainer cannot view payments
        $this->setUserSession($this->trainerId, 'trainer');
        $trainerBlocked = false;
        try {
            $this->paymentService->listPayments();
        } catch (RuntimeException $e) {
            $trainerBlocked = ($e->getCode() === 403);
        }
        $this->assertTrue($trainerBlocked, 'Trainer must be blocked from viewing payments (403)');

        // 3. Accountant can record and soft-delete payment, which writes audit log
        $this->setUserSession($this->accountantId, 'accountant');

        $invoice = $this->invoiceService->createInvoice([
            'client_id' => $this->testClientId,
            'title' => 'Audit Log Verification Invoice',
            'total_amount' => 2000.00,
            'due_date' => date('Y-m-d'),
        ]);

        $payment = $this->paymentService->recordPayment([
            'invoice_id' => (int)$invoice['id'],
            'amount' => 2000.00,
            'payment_mode' => 'cash',
        ]);
        $pmtId = (int)$payment['id'];

        // Soft delete
        $this->paymentService->deletePayment($pmtId);

        // Verify soft delete in DB
        $deletedAt = $this->pdo->query("SELECT deleted_at FROM payments WHERE id = {$pmtId}")->fetchColumn();
        $this->assertNotEmpty($deletedAt, 'Payment deletion must be a soft delete (deleted_at populated)');

        // Verify audit log has recorded the delete action
        $log = $this->pdo->query("
            SELECT * FROM activity_log 
            WHERE entity_type = 'payment' AND entity_id = {$pmtId} AND action = 'delete'
            LIMIT 1
        ")->fetch();
        $this->assertNotEmpty($log, 'Audit log must record payment deletion');
        $this->assertSame($this->accountantId, (int)$log['user_id']);
    }

    /**
     * Requirement: Payments list with filters and totals; client profile shows ledger.
     */
    public function testPaymentsFilteringAndClientLedger(): void
    {
        $this->setUserSession($this->accountantId, 'accountant');

        // Verify listPayments returns computed totals
        $list = $this->paymentService->listPayments();
        $this->assertArrayHasKey('items', $list);
        $this->assertArrayHasKey('totals', $list);
        $this->assertArrayHasKey('total_collected', $list['totals']);
        $this->assertArrayHasKey('cash_total', $list['totals']);
        $this->assertArrayHasKey('bank_upi_total', $list['totals']);

        // Verify client ledger
        $ledger = $this->paymentService->getClientLedger($this->testClientId);
        $this->assertArrayHasKey('client', $ledger);
        $this->assertArrayHasKey('summary', $ledger);
        $this->assertArrayHasKey('total_invoiced', $ledger['summary']);
        $this->assertArrayHasKey('total_paid', $ledger['summary']);
        $this->assertArrayHasKey('balance_due', $ledger['summary']);
        $this->assertArrayHasKey('invoices', $ledger);
        $this->assertArrayHasKey('payments', $ledger);
    }
}
