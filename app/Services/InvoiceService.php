<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Models\Student;
use RuntimeException;

class InvoiceService
{
    private Invoice $invoiceModel;
    private InvoiceInstallment $installmentModel;
    private Payment $paymentModel;
    private Client $clientModel;
    private Student $studentModel;
    private ActivityLog $activityLog;

    public function __construct(
        ?Invoice $invoiceModel = null,
        ?InvoiceInstallment $installmentModel = null,
        ?Payment $paymentModel = null,
        ?Client $clientModel = null,
        ?Student $studentModel = null,
        ?ActivityLog $activityLog = null
    ) {
        $this->invoiceModel = $invoiceModel ?? new Invoice();
        $this->installmentModel = $installmentModel ?? new InvoiceInstallment();
        $this->paymentModel = $paymentModel ?? new Payment();
        $this->clientModel = $clientModel ?? new Client();
        $this->studentModel = $studentModel ?? new Student();
        $this->activityLog = $activityLog ?? new ActivityLog();
    }

    /**
     * List invoices with filters and financial totals.
     */
    public function listInvoices(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $this->ensureCanViewFinancials();

        $result = $this->invoiceModel->listFiltered($filters, $page, $perPage);
        $totals = $this->invoiceModel->getTotals($filters);
        $result['totals'] = $totals;

        return $result;
    }

    /**
     * Get single invoice with joined items, installments, and payment history.
     */
    public function getInvoice(int $id): array
    {
        $this->ensureCanViewFinancials();

        $invoice = $this->invoiceModel->findWithDetails($id);
        if (!$invoice) {
            throw new RuntimeException("Invoice not found.", 404);
        }

        $invoice['installments'] = $this->installmentModel->getByInvoiceId($id);
        $invoice['payments'] = $this->paymentModel->getByInvoiceId($id);

        return $invoice;
    }

    /**
     * Create a new invoice/fee record.
     * Only Admin and Accountant can create invoices.
     */
    public function createInvoice(array $data): array
    {
        $this->ensureCanManageFinancials();

        $errors = [];
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Invoice description/title is required.';
        }

        $clientId = !empty($data['client_id']) ? (int)$data['client_id'] : null;
        $studentId = !empty($data['student_id']) ? (int)$data['student_id'] : null;
        $clientServiceId = !empty($data['client_service_id']) ? (int)$data['client_service_id'] : null;
        $courseId = !empty($data['course_id']) ? (int)$data['course_id'] : null;

        if (!$clientId && !$studentId) {
            $errors['entity'] = 'Invoice must be linked to either a Client or a Student.';
        }

        if ($clientId) {
            $client = $this->clientModel->find($clientId);
            if (!$client) {
                $errors['client_id'] = 'Selected client does not exist.';
            }
        }

        if ($studentId) {
            $student = $this->studentModel->find($studentId);
            if (!$student) {
                $errors['student_id'] = 'Selected student does not exist.';
            }
        }

        $totalAmount = round((float)($data['total_amount'] ?? 0.00), 2);
        if ($totalAmount <= 0.00) {
            $errors['total_amount'] = 'Total amount must be greater than zero.';
        }

        $discountAmount = round((float)($data['discount_amount'] ?? 0.00), 2);
        if ($discountAmount < 0.00) {
            $errors['discount_amount'] = 'Discount amount cannot be negative.';
        } elseif ($discountAmount > $totalAmount) {
            $errors['discount_amount'] = 'Discount amount cannot exceed total amount.';
        }

        $gstRatePct = round((float)($data['gst_rate_pct'] ?? 0.00), 2);
        if ($gstRatePct < 0.00 || $gstRatePct > 100.00) {
            $errors['gst_rate_pct'] = 'GST rate must be between 0% and 100%.';
        }

        $issueDate = trim((string)($data['issue_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
            $issueDate = date('Y-m-d');
        }

        $dueDate = trim((string)($data['due_date'] ?? $issueDate));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $dueDate = $issueDate;
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed for invoice creation.', $errors);
        }

        // Financial calculations using exact rounding
        $taxable = max(0.00, round($totalAmount - $discountAmount, 2));
        $gstAmount = round($taxable * ($gstRatePct / 100.00), 2);
        $netAmount = round($taxable + $gstAmount, 2);
        $paidAmount = 0.00;
        $balanceAmount = $netAmount;

        $status = ($dueDate < date('Y-m-d')) ? 'overdue' : 'unpaid';

        $invoiceNo = !empty($data['invoice_no'])
            ? strtoupper(trim((string)$data['invoice_no']))
            : $this->invoiceModel->getNextInvoiceNo();

        Session::start();
        $userId = (int)Session::get('user_id');

        $invoiceId = (int)$this->invoiceModel->insert([
            'invoice_no' => $invoiceNo,
            'client_id' => $clientId,
            'client_service_id' => $clientServiceId,
            'student_id' => $studentId,
            'course_id' => $courseId,
            'title' => $title,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'gst_rate_pct' => $gstRatePct,
            'gst_amount' => $gstAmount,
            'net_amount' => $netAmount,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balanceAmount,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'status' => $status,
            'notes' => !empty($data['notes']) ? trim((string)$data['notes']) : null,
            'created_by' => $userId > 0 ? $userId : null,
        ]);

        // Optional installment plan for course fees
        if (!empty($data['installments']) && is_array($data['installments'])) {
            $this->installmentModel->createPlan($invoiceId, $data['installments']);
        }

        $this->logActivity('invoice', $invoiceId, 'create', [
            'invoice_no' => $invoiceNo,
            'net_amount' => $netAmount,
            'due_date' => $dueDate,
            'client_id' => $clientId,
            'student_id' => $studentId,
        ]);

        return $this->getInvoice($invoiceId);
    }

    /**
     * Update existing invoice.
     * Only Admin and Accountant can edit invoices.
     */
    public function updateInvoice(int $id, array $data): array
    {
        $this->ensureCanManageFinancials();

        $existing = $this->invoiceModel->findWithDetails($id);
        if (!$existing) {
            throw new RuntimeException("Invoice not found.", 404);
        }

        $updates = [];

        if (isset($data['title'])) {
            $updates['title'] = trim((string)$data['title']);
        }

        if (isset($data['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$data['due_date']))) {
            $updates['due_date'] = trim((string)$data['due_date']);
        }

        if (isset($data['notes'])) {
            $updates['notes'] = trim((string)$data['notes']);
        }

        $totalAmount = isset($data['total_amount']) ? round((float)$data['total_amount'], 2) : (float)$existing['total_amount'];
        $discountAmount = isset($data['discount_amount']) ? round((float)$data['discount_amount'], 2) : (float)$existing['discount_amount'];
        $gstRatePct = isset($data['gst_rate_pct']) ? round((float)$data['gst_rate_pct'], 2) : (float)$existing['gst_rate_pct'];

        if (isset($data['total_amount']) || isset($data['discount_amount']) || isset($data['gst_rate_pct'])) {
            $taxable = max(0.00, round($totalAmount - $discountAmount, 2));
            $gstAmount = round($taxable * ($gstRatePct / 100.00), 2);
            $netAmount = round($taxable + $gstAmount, 2);

            $updates['total_amount'] = $totalAmount;
            $updates['discount_amount'] = $discountAmount;
            $updates['gst_rate_pct'] = $gstRatePct;
            $updates['gst_amount'] = $gstAmount;
            $updates['net_amount'] = $netAmount;
        }

        if (!empty($updates)) {
            $this->invoiceModel->update($id, $updates);
            $this->invoiceModel->recalculateTotals($id);

            // Log activity
            $this->logActivity('invoice', $id, 'update', $updates, $existing);
        }

        // Update installments if provided
        if (isset($data['installments']) && is_array($data['installments'])) {
            $this->installmentModel->createPlan($id, $data['installments']);
        }

        return $this->getInvoice($id);
    }

    /**
     * Soft delete an invoice.
     * Only Admin and Accountant can delete invoices.
     */
    public function deleteInvoice(int $id): bool
    {
        $this->ensureCanManageFinancials();

        $existing = $this->invoiceModel->findWithDetails($id);
        if (!$existing) {
            throw new RuntimeException("Invoice not found.", 404);
        }

        // Check if there are active payments attached
        $payments = $this->paymentModel->getByInvoiceId($id);
        if (!empty($payments)) {
            throw new RuntimeException("Cannot delete invoice that has recorded payments. Delete payments first or cancel the invoice.", 422);
        }

        $deleted = $this->invoiceModel->update($id, [
            'status' => 'cancelled',
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        if ($deleted) {
            $this->logActivity('invoice', $id, 'delete', null, $existing);
        }

        return $deleted;
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

        if (in_array($userRole, ['admin', 'accountant', 'manager'], true) || PermissionService::can('payment.view')) {
            return;
        }

        throw new RuntimeException("Forbidden: insufficient permissions to view invoices.", 403);
    }

    private function ensureCanManageFinancials(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('user_role');

        if (!$userId) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        // Only Admin and Accountant can create, edit, or delete invoices and fees
        if (in_array($userRole, ['admin', 'accountant'], true) || PermissionService::can('invoice.manage')) {
            return;
        }

        throw new RuntimeException("Forbidden: only Admin and Accountant can manage invoices.", 403);
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
