<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Invoice extends BaseModel
{
    protected string $table = 'invoices';
    protected bool $softDelete = true;
    protected array $fillable = [
        'invoice_no',
        'client_id',
        'client_service_id',
        'student_id',
        'course_id',
        'title',
        'total_amount',
        'discount_amount',
        'gst_rate_pct',
        'gst_amount',
        'net_amount',
        'paid_amount',
        'balance_amount',
        'issue_date',
        'due_date',
        'status',
        'notes',
        'created_by',
        'deleted_at',
    ];

    /**
     * Generate the next unique invoice number in series (never reuses deleted numbers).
     */
    public function getNextInvoiceNo(): string
    {
        $prefix = Setting::get('invoice_prefix', 'INV');
        $year = date('Y');
        $pattern = "{$prefix}-{$year}-%";

        $stmt = $this->pdo->prepare("
            SELECT `invoice_no` FROM `invoices` 
            WHERE `invoice_no` LIKE ? 
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
     * Find single invoice with joined client/student/creator details.
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                i.*,
                c.`name` AS client_name,
                c.`client_code`,
                c.`email` AS client_email,
                c.`mobile` AS client_mobile,
                c.`gst_no` AS client_gst_no,
                s.`name` AS student_name,
                s.`student_code`,
                s.`email` AS student_email,
                s.`mobile` AS student_mobile,
                u.`name` AS created_by_name,
                cs.`frequency` AS service_frequency,
                srv.`name` AS service_name,
                crs.`name` AS course_title
            FROM `invoices` i
            LEFT JOIN `clients` c ON i.`client_id` = c.`id`
            LEFT JOIN `students` s ON i.`student_id` = s.`id`
            LEFT JOIN `users` u ON i.`created_by` = u.`id`
            LEFT JOIN `client_services` cs ON i.`client_service_id` = cs.`id`
            LEFT JOIN `services` srv ON cs.`service_id` = srv.`id`
            LEFT JOIN `courses` crs ON i.`course_id` = crs.`id`
            WHERE i.`id` = ? AND i.`deleted_at` IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            return null;
        }

        return $invoice;
    }

    /**
     * Recalculate invoice paid amount, balance due, and auto-update status.
     */
    public function recalculateTotals(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `invoices` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            return [];
        }

        // Calculate total payments received (active only)
        $pmtStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(`amount`), 0.00) 
            FROM `payments` 
            WHERE `invoice_id` = ? AND `deleted_at` IS NULL
        ");
        $pmtStmt->execute([$id]);
        $paidAmount = round((float)$pmtStmt->fetchColumn(), 2);

        $netAmount = round((float)$invoice['net_amount'], 2);
        $balanceAmount = max(0.00, round($netAmount - $paidAmount, 2));

        $status = $invoice['status'];
        if ($status !== 'cancelled') {
            if ($balanceAmount <= 0.00 || $paidAmount >= $netAmount) {
                $status = 'paid';
            } elseif ($paidAmount > 0.00) {
                $status = 'partially_paid';
            } elseif ($invoice['due_date'] < date('Y-m-d')) {
                $status = 'overdue';
            } else {
                $status = 'unpaid';
            }
        }

        $updateStmt = $this->pdo->prepare("
            UPDATE `invoices` 
            SET `paid_amount` = ?, `balance_amount` = ?, `status` = ?, `updated_at` = CURRENT_TIMESTAMP
            WHERE `id` = ?
        ");
        $updateStmt->execute([$paidAmount, $balanceAmount, $status, $id]);

        $invoice['paid_amount'] = $paidAmount;
        $invoice['balance_amount'] = $balanceAmount;
        $invoice['status'] = $status;

        return $invoice;
    }

    /**
     * Get all invoices for a client.
     */
    public function getByClientId(int $clientId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, 
                   srv.`name` AS service_name,
                   (SELECT COUNT(*) FROM `payments` p WHERE p.`invoice_id` = i.`id` AND p.`deleted_at` IS NULL) AS payment_count
            FROM `invoices` i
            LEFT JOIN `client_services` cs ON i.`client_service_id` = cs.`id`
            LEFT JOIN `services` srv ON cs.`service_id` = srv.`id`
            WHERE i.`client_id` = ? AND i.`deleted_at` IS NULL
            ORDER BY i.`id` DESC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all invoices for a student.
     */
    public function getByStudentId(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, 
                   crs.`name` AS course_title,
                   (SELECT COUNT(*) FROM `payments` p WHERE p.`invoice_id` = i.`id` AND p.`deleted_at` IS NULL) AS payment_count
            FROM `invoices` i
            LEFT JOIN `courses` crs ON i.`course_id` = crs.`id`
            WHERE i.`student_id` = ? AND i.`deleted_at` IS NULL
            ORDER BY i.`id` DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * List filtered invoices with pagination.
     */
    public function listFiltered(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['i.`deleted_at` IS NULL'];
        $params = [];

        if (!empty($filters['client_id'])) {
            $where[] = 'i.`client_id` = ?';
            $params[] = (int)$filters['client_id'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'i.`student_id` = ?';
            $params[] = (int)$filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.`status` = ?';
            $params[] = strtolower(trim((string)$filters['status']));
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'i.`issue_date` >= ?';
            $params[] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'i.`issue_date` <= ?';
            $params[] = trim((string)$filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where[] = '(i.`invoice_no` LIKE ? OR i.`title` LIKE ? OR c.`name` LIKE ? OR s.`name` LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countSql = "
            SELECT COUNT(*) 
            FROM `invoices` i
            LEFT JOIN `clients` c ON i.`client_id` = c.`id`
            LEFT JOIN `students` s ON i.`student_id` = s.`id`
            WHERE {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch records
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "
            SELECT 
                i.*,
                c.`name` AS client_name,
                c.`client_code`,
                s.`name` AS student_name,
                s.`student_code`
            FROM `invoices` i
            LEFT JOIN `clients` c ON i.`client_id` = c.`id`
            LEFT JOIN `students` s ON i.`student_id` = s.`id`
            WHERE {$whereClause}
            ORDER BY i.`id` DESC
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
     * Get aggregate totals for filtered invoices.
     */
    public function getTotals(array $filters = []): array
    {
        $where = ['i.`deleted_at` IS NULL'];
        $params = [];

        if (!empty($filters['client_id'])) {
            $where[] = 'i.`client_id` = ?';
            $params[] = (int)$filters['client_id'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'i.`student_id` = ?';
            $params[] = (int)$filters['student_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'i.`status` = ?';
            $params[] = strtolower(trim((string)$filters['status']));
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'i.`issue_date` >= ?';
            $params[] = trim((string)$filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'i.`issue_date` <= ?';
            $params[] = trim((string)$filters['date_to']);
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                COALESCE(SUM(i.`total_amount`), 0.00) AS total_gross,
                COALESCE(SUM(i.`discount_amount`), 0.00) AS total_discount,
                COALESCE(SUM(i.`gst_amount`), 0.00) AS total_gst,
                COALESCE(SUM(i.`net_amount`), 0.00) AS total_net,
                COALESCE(SUM(i.`paid_amount`), 0.00) AS total_paid,
                COALESCE(SUM(i.`balance_amount`), 0.00) AS total_balance
            FROM `invoices` i
            WHERE {$whereClause}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total_gross' => round((float)($row['total_gross'] ?? 0), 2),
            'total_discount' => round((float)($row['total_discount'] ?? 0), 2),
            'total_gst' => round((float)($row['total_gst'] ?? 0), 2),
            'total_net' => round((float)($row['total_net'] ?? 0), 2),
            'total_paid' => round((float)($row['total_paid'] ?? 0), 2),
            'total_balance' => round((float)($row['total_balance'] ?? 0), 2),
        ];
    }
}
