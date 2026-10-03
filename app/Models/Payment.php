<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Payment extends BaseModel
{
    protected string $table = 'payments';
    protected bool $softDelete = true;
    protected array $fillable = [
        'receipt_no',
        'invoice_id',
        'client_id',
        'student_id',
        'amount',
        'payment_date',
        'payment_mode',
        'reference_no',
        'received_by',
        'notes',
        'created_by',
        'deleted_at',
    ];

    /**
     * Generate next receipt number in series (never reuses deleted receipt numbers).
     */
    public function getNextReceiptNo(): string
    {
        $prefix = Setting::get('receipt_prefix', 'REC');
        $year = date('Y');
        $pattern = "{$prefix}-{$year}-%";

        $stmt = $this->pdo->prepare("
            SELECT `receipt_no` FROM `payments` 
            WHERE `receipt_no` LIKE ? 
            ORDER BY `id` DESC LIMIT 1
        ");
        $stmt->execute([$pattern]);
        $last = $stmt->fetchColumn();

        $nextNum = 1;
        if ($last) {
            $parts = explode('-', (string)$last);
            $seq = (int)end($parts);
            if ($seq > 0) {
                $nextNum = $seq + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNum);
    }

    /**
     * Find single payment with joined invoice, client/student, and receiver details.
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.*,
                i.`invoice_no`,
                i.`title` AS invoice_title,
                i.`total_amount` AS invoice_total,
                i.`discount_amount` AS invoice_discount,
                i.`gst_rate_pct` AS invoice_gst_rate,
                i.`gst_amount` AS invoice_gst_amount,
                i.`net_amount` AS invoice_net,
                i.`paid_amount` AS invoice_paid,
                i.`balance_amount` AS invoice_balance,
                i.`status` AS invoice_status,
                c.`name` AS client_name,
                c.`client_code`,
                c.`email` AS client_email,
                c.`mobile` AS client_mobile,
                s.`name` AS student_name,
                s.`student_code`,
                s.`email` AS student_email,
                s.`mobile` AS student_mobile,
                u.`name` AS received_by_name,
                uc.`name` AS created_by_name
            FROM `payments` p
            JOIN `invoices` i ON p.`invoice_id` = i.`id`
            LEFT JOIN `clients` c ON p.`client_id` = c.`id`
            LEFT JOIN `students` s ON p.`student_id` = s.`id`
            LEFT JOIN `users` u ON p.`received_by` = u.`id`
            LEFT JOIN `users` uc ON p.`created_by` = uc.`id`
            WHERE p.`id` = ? AND p.`deleted_at` IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Find payment by receipt number.
     */
    public function findByReceiptNo(string $receiptNo): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `payments` WHERE `receipt_no` = ? LIMIT 1");
        $stmt->execute([$receiptNo]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Get active payments for an invoice.
     */
    public function getByInvoiceId(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.`name` AS received_by_name
            FROM `payments` p
            LEFT JOIN `users` u ON p.`received_by` = u.`id`
            WHERE p.`invoice_id` = ? AND p.`deleted_at` IS NULL
            ORDER BY p.`payment_date` DESC, p.`id` DESC
        ");
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get active payments for a client.
     */
    public function getByClientId(int $clientId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, i.`invoice_no`, i.`title` AS invoice_title, u.`name` AS received_by_name
            FROM `payments` p
            JOIN `invoices` i ON p.`invoice_id` = i.`id`
            LEFT JOIN `users` u ON p.`received_by` = u.`id`
            WHERE p.`client_id` = ? AND p.`deleted_at` IS NULL
            ORDER BY p.`payment_date` DESC, p.`id` DESC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get active payments for a student.
     */
    public function getByStudentId(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, i.`invoice_no`, i.`title` AS invoice_title, u.`name` AS received_by_name
            FROM `payments` p
            JOIN `invoices` i ON p.`invoice_id` = i.`id`
            LEFT JOIN `users` u ON p.`received_by` = u.`id`
            WHERE p.`student_id` = ? AND p.`deleted_at` IS NULL
            ORDER BY p.`payment_date` DESC, p.`id` DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * List filtered payments with pagination.
     */
    public function listFiltered(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['p.`deleted_at` IS NULL'];
        $params = [];

        if (!empty($filters['client_id'])) {
            $where[] = 'p.`client_id` = ?';
            $params[] = (int)$filters['client_id'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'p.`student_id` = ?';
            $params[] = (int)$filters['student_id'];
        }

        if (!empty($filters['invoice_id'])) {
            $where[] = 'p.`invoice_id` = ?';
            $params[] = (int)$filters['invoice_id'];
        }

        if (!empty($filters['payment_mode'])) {
            $where[] = 'p.`payment_mode` = ?';
            $params[] = strtolower(trim((string)$filters['payment_mode']));
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'p.`payment_date` >= ?';
            $params[] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'p.`payment_date` <= ?';
            $params[] = trim((string)$filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where[] = '(p.`receipt_no` LIKE ? OR p.`reference_no` LIKE ? OR c.`name` LIKE ? OR s.`name` LIKE ? OR i.`invoice_no` LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countSql = "
            SELECT COUNT(*) 
            FROM `payments` p
            JOIN `invoices` i ON p.`invoice_id` = i.`id`
            LEFT JOIN `clients` c ON p.`client_id` = c.`id`
            LEFT JOIN `students` s ON p.`student_id` = s.`id`
            WHERE {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch records
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "
            SELECT 
                p.*,
                i.`invoice_no`,
                i.`title` AS invoice_title,
                i.`net_amount` AS invoice_net,
                c.`name` AS client_name,
                c.`client_code`,
                s.`name` AS student_name,
                s.`student_code`,
                u.`name` AS received_by_name
            FROM `payments` p
            JOIN `invoices` i ON p.`invoice_id` = i.`id`
            LEFT JOIN `clients` c ON p.`client_id` = c.`id`
            LEFT JOIN `students` s ON p.`student_id` = s.`id`
            LEFT JOIN `users` u ON p.`received_by` = u.`id`
            WHERE {$whereClause}
            ORDER BY p.`payment_date` DESC, p.`id` DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll() ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * Compute financial totals for filtered payments.
     */
    public function getTotals(array $filters = []): array
    {
        $where = ['p.`deleted_at` IS NULL'];
        $params = [];

        if (!empty($filters['client_id'])) {
            $where[] = 'p.`client_id` = ?';
            $params[] = (int)$filters['client_id'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'p.`student_id` = ?';
            $params[] = (int)$filters['student_id'];
        }

        if (!empty($filters['invoice_id'])) {
            $where[] = 'p.`invoice_id` = ?';
            $params[] = (int)$filters['invoice_id'];
        }

        if (!empty($filters['payment_mode'])) {
            $where[] = 'p.`payment_mode` = ?';
            $params[] = strtolower(trim((string)$filters['payment_mode']));
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'p.`payment_date` >= ?';
            $params[] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'p.`payment_date` <= ?';
            $params[] = trim((string)$filters['date_to']);
        }

        $whereClause = implode(' AND ', $where);
        $today = date('Y-m-d');

        $sql = "
            SELECT 
                COALESCE(SUM(p.`amount`), 0.00) AS total_collected,
                COALESCE(SUM(CASE WHEN p.`payment_mode` = 'cash' THEN p.`amount` ELSE 0 END), 0.00) AS cash_total,
                COALESCE(SUM(CASE WHEN p.`payment_mode` IN ('upi', 'bank_transfer', 'cheque', 'card') THEN p.`amount` ELSE 0 END), 0.00) AS bank_upi_total,
                COALESCE(SUM(CASE WHEN p.`payment_date` = '{$today}' THEN p.`amount` ELSE 0 END), 0.00) AS today_total
            FROM `payments` p
            JOIN `invoices` i ON p.`invoice_id` = i.`id`
            WHERE {$whereClause}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total_collected' => round((float)($row['total_collected'] ?? 0), 2),
            'cash_total' => round((float)($row['cash_total'] ?? 0), 2),
            'bank_upi_total' => round((float)($row['bank_upi_total'] ?? 0), 2),
            'today_total' => round((float)($row['today_total'] ?? 0), 2),
        ];
    }
}
