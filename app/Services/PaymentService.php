<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Helpers\PdfReceipt;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;
use RuntimeException;

class PaymentService
{
    private Payment $paymentModel;
    private Invoice $invoiceModel;
    private InvoiceInstallment $installmentModel;
    private Client $clientModel;
    private Student $studentModel;
    private ActivityLog $activityLog;

    public function __construct(
        ?Payment $paymentModel = null,
        ?Invoice $invoiceModel = null,
        ?InvoiceInstallment $installmentModel = null,
        ?Client $clientModel = null,
        ?Student $studentModel = null,
        ?ActivityLog $activityLog = null
    ) {
        $this->paymentModel = $paymentModel ?? new Payment();
        $this->invoiceModel = $invoiceModel ?? new Invoice();
        $this->installmentModel = $installmentModel ?? new InvoiceInstallment();
        $this->clientModel = $clientModel ?? new Client();
        $this->studentModel = $studentModel ?? new Student();
        $this->activityLog = $activityLog ?? new ActivityLog();
    }

    /**
     * List payment transactions and fee records with filters and financial totals.
     */
    public function listPayments(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $this->ensureCanViewFinancials();

        $result = $this->paymentModel->listFiltered($filters, $page, $perPage);
        $totals = $this->paymentModel->getTotals($filters);
        $result['totals'] = $totals;

        return $result;
    }

    /**
     * Get single payment record.
     */
    public function getPayment(int|string $id): ?array
    {
        $this->ensureCanViewFinancials();
        return $this->paymentModel->findWithDetails((int)$id);
    }

    /**
     * Record a new payment (supports multiple partial payments per invoice).
     * Only Admin and Accountant can record payments.
     */
    public function recordPayment(array $data): array
    {
        $this->ensureCanManageFinancials();

        $errors = [];

        $invoiceId = (int)($data['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            $errors['invoice_id'] = 'Please select a valid invoice.';
        }

        $invoice = $invoiceId > 0 ? $this->invoiceModel->findWithDetails($invoiceId) : null;
        if (!$invoice) {
            $errors['invoice_id'] = 'The selected invoice was not found.';
        } elseif ($invoice['status'] === 'cancelled') {
            $errors['invoice_id'] = 'Cannot record payment against a cancelled invoice.';
        }

        $amount = round((float)($data['amount'] ?? 0.00), 2);
        if ($amount <= 0.00) {
            $errors['amount'] = 'Payment amount must be greater than zero.';
        }

        $paymentMode = strtolower(trim((string)($data['payment_mode'] ?? 'cash')));
        if (!in_array($paymentMode, ['cash', 'upi', 'bank_transfer', 'cheque', 'card'], true)) {
            $errors['payment_mode'] = 'Invalid payment mode selected.';
        }

        $paymentDate = trim((string)($data['payment_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $paymentDate = date('Y-m-d');
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed for payment record.', $errors);
        }

        Session::start();
        $currentUserId = (int)Session::get('user_id');
        $receivedBy = !empty($data['received_by']) ? (int)$data['received_by'] : ($currentUserId > 0 ? $currentUserId : null);

        $receiptNo = !empty($data['receipt_no'])
            ? strtoupper(trim((string)$data['receipt_no']))
            : $this->paymentModel->getNextReceiptNo();

        $clientId = $invoice['client_id'] ? (int)$invoice['client_id'] : null;
        $studentId = $invoice['student_id'] ? (int)$invoice['student_id'] : null;

        $paymentId = (int)$this->paymentModel->insert([
            'receipt_no' => $receiptNo,
            'invoice_id' => $invoiceId,
            'client_id' => $clientId,
            'student_id' => $studentId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'payment_mode' => $paymentMode,
            'reference_no' => !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
            'received_by' => $receivedBy,
            'notes' => !empty($data['notes']) ? trim((string)$data['notes']) : null,
            'created_by' => $currentUserId > 0 ? $currentUserId : null,
        ]);

        // Auto-recalculate invoice paid amount, balance due, and status
        $this->invoiceModel->recalculateTotals($invoiceId);

        // Auto-sync installment plan if invoice has installments
        $this->installmentModel->syncWithPayments($invoiceId);

        // Log audit record
        $this->logActivity('payment', $paymentId, 'create', [
            'receipt_no' => $receiptNo,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'payment_mode' => $paymentMode,
            'client_id' => $clientId,
            'student_id' => $studentId,
        ]);

        return $this->paymentModel->findWithDetails($paymentId) ?: [];
    }

    /**
     * Update an existing payment.
     * Only Admin and Accountant can edit payments.
     */
    public function updatePayment(int $id, array $data): array
    {
        $this->ensureCanManageFinancials();

        $existing = $this->paymentModel->findWithDetails($id);
        if (!$existing) {
            throw new RuntimeException("Payment record not found.", 404);
        }

        $updates = [];

        if (isset($data['amount'])) {
            $amount = round((float)$data['amount'], 2);
            if ($amount <= 0.00) {
                throw new ValidationException("Payment amount must be greater than zero.", ['amount' => 'Must be > 0']);
            }
            $updates['amount'] = $amount;
        }

        if (isset($data['payment_mode'])) {
            $mode = strtolower(trim((string)$data['payment_mode']));
            if (in_array($mode, ['cash', 'upi', 'bank_transfer', 'cheque', 'card'], true)) {
                $updates['payment_mode'] = $mode;
            }
        }

        if (isset($data['payment_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$data['payment_date']))) {
            $updates['payment_date'] = trim((string)$data['payment_date']);
        }

        if (array_key_exists('reference_no', $data)) {
            $updates['reference_no'] = !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null;
        }

        if (array_key_exists('notes', $data)) {
            $updates['notes'] = !empty($data['notes']) ? trim((string)$data['notes']) : null;
        }

        if (array_key_exists('received_by', $data)) {
            $updates['received_by'] = !empty($data['received_by']) ? (int)$data['received_by'] : null;
        }

        if (!empty($updates)) {
            $this->paymentModel->update($id, $updates);

            // Recalculate invoice & installment balances
            $invoiceId = (int)$existing['invoice_id'];
            $this->invoiceModel->recalculateTotals($invoiceId);
            $this->installmentModel->syncWithPayments($invoiceId);

            $this->logActivity('payment', $id, 'update', $updates, $existing);
        }

        return $this->paymentModel->findWithDetails($id) ?: [];
    }

    /**
     * Soft delete a payment.
     * Only Admin and Accountant can delete payments.
     */
    public function deletePayment(int $id): bool
    {
        $this->ensureCanManageFinancials();

        $existing = $this->paymentModel->findWithDetails($id);
        if (!$existing) {
            throw new RuntimeException("Payment record not found.", 404);
        }

        $deleted = $this->paymentModel->update($id, [
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        if ($deleted) {
            // Recalculate invoice and installments without this payment
            $invoiceId = (int)$existing['invoice_id'];
            $this->invoiceModel->recalculateTotals($invoiceId);
            $this->installmentModel->syncWithPayments($invoiceId);

            $this->logActivity('payment', $id, 'delete', null, $existing);
        }

        return $deleted;
    }

    /**
     * Generate PDF receipt binary for a payment.
     */
    public function generateReceiptPdf(int $paymentId): string
    {
        $this->ensureCanViewFinancials();

        $payment = $this->paymentModel->findWithDetails($paymentId);
        if (!$payment) {
            throw new RuntimeException("Payment not found.", 404);
        }

        $invoice = $this->invoiceModel->findWithDetails((int)$payment['invoice_id']);
        if (!$invoice) {
            throw new RuntimeException("Associated invoice not found.", 404);
        }

        $settings = Setting::getAll();

        $customer = null;
        if (!empty($payment['client_id'])) {
            $customer = $this->clientModel->find((int)$payment['client_id']);
        } elseif (!empty($payment['student_id'])) {
            $customer = $this->studentModel->find((int)$payment['student_id']);
        }

        return PdfReceipt::generate($payment, $invoice, $settings, $customer);
    }

    /**
     * Email payment receipt PDF to client or student.
     */
    public function emailReceipt(int $paymentId, ?string $toEmail = null): bool
    {
        $this->ensureCanViewFinancials();

        $payment = $this->paymentModel->findWithDetails($paymentId);
        if (!$payment) {
            throw new RuntimeException("Payment not found.", 404);
        }

        $invoice = $this->invoiceModel->findWithDetails((int)$payment['invoice_id']);
        if (!$invoice) {
            throw new RuntimeException("Associated invoice not found.", 404);
        }

        $customer = null;
        if (!empty($payment['client_id'])) {
            $customer = $this->clientModel->find((int)$payment['client_id']);
        } elseif (!empty($payment['student_id'])) {
            $customer = $this->studentModel->find((int)$payment['student_id']);
        }

        $recipientEmail = $toEmail ?: ($customer['email'] ?? null);
        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("A valid recipient email address is required.", ['email' => 'Valid email address required']);
        }

        $recipientName = $customer['name'] ?? 'Customer';
        $pdfData = $this->generateReceiptPdf($paymentId);

        $sent = MailService::sendPaymentReceipt($recipientEmail, $recipientName, $payment, $invoice, $pdfData);
        if ($sent) {
            $this->logActivity('payment', $paymentId, 'email_receipt', [
                'recipient' => $recipientEmail,
                'receipt_no' => $payment['receipt_no'],
            ]);
        }

        return $sent;
    }

    /**
     * Get complete financial ledger for a client (invoices, payments, totals).
     */
    public function getClientLedger(int $clientId): array
    {
        $this->ensureCanViewFinancials();

        $client = $this->clientModel->find($clientId);
        if (!$client) {
            throw new RuntimeException("Client not found.", 404);
        }

        $invoices = $this->invoiceModel->getByClientId($clientId);
        $payments = $this->paymentModel->getByClientId($clientId);

        $totalInvoiced = 0.00;
        $totalPaid = 0.00;
        $balanceDue = 0.00;

        foreach ($invoices as $inv) {
            $totalInvoiced += (float)$inv['net_amount'];
            $totalPaid += (float)$inv['paid_amount'];
            $balanceDue += (float)$inv['balance_amount'];
        }

        return [
            'client' => [
                'id' => $client['id'],
                'client_code' => $client['client_code'],
                'name' => $client['name'],
                'email' => $client['email'],
                'mobile' => $client['mobile'],
            ],
            'summary' => [
                'total_invoiced' => round($totalInvoiced, 2),
                'total_paid' => round($totalPaid, 2),
                'balance_due' => round($balanceDue, 2),
                'invoice_count' => count($invoices),
                'payment_count' => count($payments),
            ],
            'invoices' => $invoices,
            'payments' => $payments,
        ];
    }

    /**
     * Get complete financial ledger for a student (invoices, installments, payments, totals).
     */
    public function getStudentLedger(int $studentId): array
    {
        $this->ensureCanViewFinancials();

        $student = $this->studentModel->find($studentId);
        if (!$student) {
            throw new RuntimeException("Student not found.", 404);
        }

        $invoices = $this->invoiceModel->getByStudentId($studentId);
        $payments = $this->paymentModel->getByStudentId($studentId);

        $totalInvoiced = 0.00;
        $totalPaid = 0.00;
        $balanceDue = 0.00;

        foreach ($invoices as &$inv) {
            $totalInvoiced += (float)$inv['net_amount'];
            $totalPaid += (float)$inv['paid_amount'];
            $balanceDue += (float)$inv['balance_amount'];
            $inv['installments'] = $this->installmentModel->getByInvoiceId((int)$inv['id']);
        }
        unset($inv);

        return [
            'student' => [
                'id' => $student['id'],
                'student_code' => $student['student_code'],
                'name' => $student['name'],
                'email' => $student['email'],
                'mobile' => $student['mobile'],
            ],
            'summary' => [
                'total_invoiced' => round($totalInvoiced, 2),
                'total_paid' => round($totalPaid, 2),
                'balance_due' => round($balanceDue, 2),
                'invoice_count' => count($invoices),
                'payment_count' => count($payments),
            ],
            'invoices' => $invoices,
            'payments' => $payments,
        ];
    }

    // ==========================================
    // Security & Permission Enforcement
    // ==========================================

    private function ensureCanViewFinancials(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('user_role');

        if (!$userId) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        // Admin, Accountant, and Manager can view payments
        if (in_array($userRole, ['admin', 'accountant', 'manager'], true) || PermissionService::can('payment.view')) {
            return;
        }

        throw new RuntimeException("Unauthorized: you do not have permission to view payments", 403);
    }

    private function ensureCanManageFinancials(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('user_role');

        if (!$userId) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        // Only Admin and Accountant can record, edit, or delete payments
        if (in_array($userRole, ['admin', 'accountant'], true) || PermissionService::can('payment.manage')) {
            return;
        }

        throw new RuntimeException("Unauthorized: you do not have permission to record or manage payments", 403);
    }

    private function logActivity(string $entityType, int $entityId, string $action, ?array $newValues = null, ?array $oldValues = null): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $request = Request::createFromGlobals();

        $this->activityLog->log(
            $userId > 0 ? $userId : null,
            $entityType,
            $entityId,
            $action,
            $newValues,
            $oldValues,
            $request->ip(),
            $request->userAgent()
        );
    }
}
