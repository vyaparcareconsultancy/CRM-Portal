<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class InvoiceInstallment extends BaseModel
{
    protected string $table = 'invoice_installments';
    protected bool $softDelete = true;
    protected array $fillable = [
        'invoice_id',
        'installment_no',
        'due_date',
        'amount',
        'paid_amount',
        'status',
        'notes',
        'deleted_at',
    ];

    /**
     * Get all installments for an invoice.
     */
    public function getByInvoiceId(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `invoice_installments` 
            WHERE `invoice_id` = ? AND `deleted_at` IS NULL 
            ORDER BY `installment_no` ASC
        ");
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Create an installment plan for an invoice.
     *
     * @param array<int, array{due_date: string, amount: float|int|string, notes?: string}> $installments
     */
    public function createPlan(int $invoiceId, array $installments): array
    {
        // Remove any old un-paid installments
        $delStmt = $this->pdo->prepare("DELETE FROM `invoice_installments` WHERE `invoice_id` = ?");
        $delStmt->execute([$invoiceId]);

        $created = [];
        $seq = 1;

        $insertStmt = $this->pdo->prepare("
            INSERT INTO `invoice_installments` 
            (`invoice_id`, `installment_no`, `due_date`, `amount`, `paid_amount`, `status`, `notes`)
            VALUES (?, ?, ?, ?, 0.00, 'pending', ?)
        ");

        foreach ($installments as $inst) {
            $amount = round((float)($inst['amount'] ?? 0), 2);
            $dueDate = trim((string)($inst['due_date'] ?? date('Y-m-d')));
            $notes = !empty($inst['notes']) ? trim((string)$inst['notes']) : null;

            if ($amount <= 0) {
                continue;
            }

            $insertStmt->execute([$invoiceId, $seq, $dueDate, $amount, $notes]);
            $created[] = [
                'id' => (int)$this->pdo->lastInsertId(),
                'installment_no' => $seq,
                'due_date' => $dueDate,
                'amount' => $amount,
                'paid_amount' => 0.00,
                'status' => 'pending',
                'notes' => $notes,
            ];
            $seq++;
        }

        // Sync in case payments already exist
        $this->syncWithPayments($invoiceId);

        return $this->getByInvoiceId($invoiceId);
    }

    /**
     * Distribute total payments received across installments in chronological sequence.
     */
    public function syncWithPayments(int $invoiceId): void
    {
        // 1. Get total paid on invoice
        $pmtStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(`amount`), 0.00) 
            FROM `payments` 
            WHERE `invoice_id` = ? AND `deleted_at` IS NULL
        ");
        $pmtStmt->execute([$invoiceId]);
        $remainingPool = round((float)$pmtStmt->fetchColumn(), 2);

        // 2. Fetch installments
        $installments = $this->getByInvoiceId($invoiceId);
        if (empty($installments)) {
            return;
        }

        $updateStmt = $this->pdo->prepare("
            UPDATE `invoice_installments` 
            SET `paid_amount` = ?, `status` = ?, `updated_at` = CURRENT_TIMESTAMP 
            WHERE `id` = ?
        ");

        $today = date('Y-m-d');

        foreach ($installments as $inst) {
            $instAmount = round((float)$inst['amount'], 2);
            $instPaid = 0.00;
            $status = 'pending';

            if ($remainingPool >= $instAmount) {
                $instPaid = $instAmount;
                $remainingPool = round($remainingPool - $instAmount, 2);
                $status = 'paid';
            } elseif ($remainingPool > 0.00) {
                $instPaid = $remainingPool;
                $remainingPool = 0.00;
                $status = 'partially_paid';
            } else {
                $instPaid = 0.00;
                $status = ($inst['due_date'] < $today) ? 'overdue' : 'pending';
            }

            $updateStmt->execute([$instPaid, $status, $inst['id']]);
        }
    }
}
