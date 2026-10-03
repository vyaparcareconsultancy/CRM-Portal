<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Cache\Cache;
use PDO;

class ReportService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Get report data cached for 10 minutes (600 seconds).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getReport(string $type, array $filters = []): array
    {
        $cacheKey = "report:{$type}:" . md5(json_encode($filters));

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, 600, function () use ($type, $filters): array {
            return match ($type) {
                'sales_collections' => $this->getSalesAndCollectionsReport($filters),
                'lead_source_conversion' => $this->getLeadSourcePerformanceReport($filters),
                'course_admissions' => $this->getCourseAdmissionsReport($filters),
                'client_service_revenue' => $this->getClientServiceRevenueReport($filters),
                'payment_aging' => $this->getPaymentAgingReport($filters),
                'staff_performance' => $this->getStaffPerformanceReport($filters),
                'attendance_summary' => $this->getAttendanceSummaryReport($filters),
                default => throw new \InvalidArgumentException("Unknown report type: {$type}"),
            };
        });
    }

    /**
     * Lookups for report filters (staff, sources, courses, services).
     *
     * @return array<string, mixed>
     */
    public function getFilterLookups(): array
    {
        return Cache::remember('report:filter_lookups', 600, function (): array {
            $staff = $this->pdo->query("SELECT u.id, u.name, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $sources = $this->pdo->query("SELECT id, name FROM lead_sources WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $courses = $this->pdo->query("SELECT id, name, course_code FROM courses WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $services = $this->pdo->query("SELECT id, name, code FROM services WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'staff' => $staff,
                'sources' => $sources,
                'courses' => $courses,
                'services' => $services,
            ];
        });
    }

    // =========================================================================
    // 1. DAILY / MONTHLY SALES & COLLECTIONS
    // =========================================================================
    public function getSalesAndCollectionsReport(array $filters = []): array
    {
        $sql = "
            SELECT p.payment_date,
                   COUNT(p.id) AS tx_count,
                   SUM(p.amount) AS total_collected,
                   SUM(CASE WHEN p.payment_mode = 'cash' THEN p.amount ELSE 0 END) AS cash_amount,
                   SUM(CASE WHEN p.payment_mode = 'upi' THEN p.amount ELSE 0 END) AS upi_amount,
                   SUM(CASE WHEN p.payment_mode = 'bank_transfer' THEN p.amount ELSE 0 END) AS bank_amount,
                   SUM(CASE WHEN p.payment_mode = 'cheque' THEN p.amount ELSE 0 END) AS cheque_amount,
                   SUM(CASE WHEN p.payment_mode = 'card' THEN p.amount ELSE 0 END) AS card_amount
            FROM `payments` p
            WHERE p.deleted_at IS NULL
        ";
        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND p.payment_date >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND p.payment_date <= ?";
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['staff_id'])) {
            $sql .= " AND p.received_by = ?";
            $params[] = (int)$filters['staff_id'];
        }

        $sql .= " GROUP BY p.payment_date ORDER BY p.payment_date DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalSum = 0.0;
        $totalTx = 0;
        foreach ($rows as $r) {
            $totalSum += (float)$r['total_collected'];
            $totalTx += (int)$r['tx_count'];
        }

        return [
            'title' => 'Daily & Monthly Sales & Collections',
            'rows' => $rows,
            'summary' => [
                'total_collected' => $totalSum,
                'total_transactions' => $totalTx,
            ],
        ];
    }

    // =========================================================================
    // 2. LEAD SOURCE PERFORMANCE & CONVERSION %
    // =========================================================================
    public function getLeadSourcePerformanceReport(array $filters = []): array
    {
        $sql = "
            SELECT COALESCE(ls.name, 'Direct / Walk-in') AS source_name,
                   COUNT(l.id) AS total_leads,
                   SUM(CASE WHEN l.status = 'contacted' THEN 1 ELSE 0 END) AS contacted_count,
                   SUM(CASE WHEN l.status = 'interested' THEN 1 ELSE 0 END) AS interested_count,
                   SUM(CASE WHEN l.status = 'follow_up' THEN 1 ELSE 0 END) AS follow_up_count,
                   SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) AS converted_count,
                   SUM(CASE WHEN l.status = 'lost' THEN 1 ELSE 0 END) AS lost_count
            FROM `leads` l
            LEFT JOIN `lead_sources` ls ON l.lead_source_id = ls.id
            WHERE l.deleted_at IS NULL
        ";
        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(l.created_at) >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(l.created_at) <= ?";
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['staff_id'])) {
            $sql .= " AND l.assigned_to = ?";
            $params[] = (int)$filters['staff_id'];
        }
        if (!empty($filters['lead_source'])) {
            $sql .= " AND ls.name = ?";
            $params[] = $filters['lead_source'];
        }

        $sql .= " GROUP BY COALESCE(ls.name, 'Direct / Walk-in') ORDER BY total_leads DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalLeadsAll = 0;
        $totalConvertedAll = 0;

        $rows = array_map(static function ($r) use (&$totalLeadsAll, &$totalConvertedAll) {
            $total = (int)$r['total_leads'];
            $conv = (int)$r['converted_count'];
            $totalLeadsAll += $total;
            $totalConvertedAll += $conv;
            $pct = $total > 0 ? round(($conv / $total) * 100, 1) : 0.0;

            return array_merge($r, [
                'conversion_rate_pct' => $pct,
            ]);
        }, $rawRows);

        $overallRate = $totalLeadsAll > 0 ? round(($totalConvertedAll / $totalLeadsAll) * 100, 1) : 0.0;

        return [
            'title' => 'Lead Source Performance and Conversion %',
            'rows' => $rows,
            'summary' => [
                'total_leads' => $totalLeadsAll,
                'total_converted' => $totalConvertedAll,
                'overall_conversion_pct' => $overallRate,
            ],
        ];
    }

    // =========================================================================
    // 3. COURSE-WISE ADMISSIONS & BATCH STRENGTH
    // =========================================================================
    public function getCourseAdmissionsReport(array $filters = []): array
    {
        $sql = "
            SELECT c.id AS course_id,
                   c.course_code,
                   c.name AS course_name,
                   c.fee AS course_default_fee,
                   c.duration,
                   COUNT(DISTINCT b.id) AS batch_count,
                   COUNT(DISTINCT e.id) AS total_admissions,
                   SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) AS active_students,
                   SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed_students,
                   SUM(CASE WHEN e.status = 'dropped' THEN 1 ELSE 0 END) AS dropped_students,
                   COALESCE(SUM(e.agreed_fee), 0) AS total_agreed_revenue
            FROM `courses` c
            LEFT JOIN `batches` b ON c.id = b.course_id AND b.deleted_at IS NULL
            LEFT JOIN `enrollments` e ON c.id = e.course_id AND e.deleted_at IS NULL
            WHERE c.deleted_at IS NULL
        ";
        $params = [];

        if (!empty($filters['course_id'])) {
            $sql .= " AND c.id = ?";
            $params[] = (int)$filters['course_id'];
        }
        if (!empty($filters['start_date'])) {
            $sql .= " AND e.admission_date >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND e.admission_date <= ?";
            $params[] = $filters['end_date'];
        }

        $sql .= " GROUP BY c.id, c.course_code, c.name, c.fee, c.duration ORDER BY total_admissions DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalAdmissions = 0;
        $totalRev = 0.0;
        foreach ($rows as $r) {
            $totalAdmissions += (int)$r['total_admissions'];
            $totalRev += (float)$r['total_agreed_revenue'];
        }

        return [
            'title' => 'Course-wise Admissions and Batch Strength',
            'rows' => $rows,
            'summary' => [
                'total_admissions' => $totalAdmissions,
                'total_revenue' => $totalRev,
            ],
        ];
    }

    // =========================================================================
    // 4. CLIENT-WISE & SERVICE-WISE REVENUE
    // =========================================================================
    public function getClientServiceRevenueReport(array $filters = []): array
    {
        $sql = "
            SELECT s.id AS service_id,
                   s.name AS service_name,
                   s.code AS service_code,
                   s.type AS service_type,
                   s.frequency,
                   COUNT(DISTINCT cs.id) AS active_subscriptions,
                   COALESCE(SUM(inv.net_amount), 0) AS total_billed,
                   COALESCE(SUM(inv.paid_amount), 0) AS total_collected,
                   COALESCE(SUM(inv.balance_amount), 0) AS total_due
            FROM `services` s
            LEFT JOIN `client_services` cs ON s.id = cs.service_id AND cs.status = 'active' AND cs.deleted_at IS NULL
            LEFT JOIN `invoices` inv ON cs.id = inv.client_service_id AND inv.deleted_at IS NULL
            WHERE s.deleted_at IS NULL
        ";
        $params = [];

        if (!empty($filters['service_id'])) {
            $sql .= " AND s.id = ?";
            $params[] = (int)$filters['service_id'];
        }
        if (!empty($filters['staff_id'])) {
            $sql .= " AND cs.assigned_accountant_id = ?";
            $params[] = (int)$filters['staff_id'];
        }
        if (!empty($filters['start_date'])) {
            $sql .= " AND inv.issue_date >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND inv.issue_date <= ?";
            $params[] = $filters['end_date'];
        }

        $sql .= " GROUP BY s.id, s.name, s.code, s.type, s.frequency ORDER BY total_billed DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalBilled = 0.0;
        $totalCollected = 0.0;
        $totalDue = 0.0;
        foreach ($rows as $r) {
            $totalBilled += (float)$r['total_billed'];
            $totalCollected += (float)$r['total_collected'];
            $totalDue += (float)$r['total_due'];
        }

        return [
            'title' => 'Client-wise and Service-wise Revenue',
            'rows' => $rows,
            'summary' => [
                'total_billed' => $totalBilled,
                'total_collected' => $totalCollected,
                'total_due' => $totalDue,
            ],
        ];
    }

    // =========================================================================
    // 5. PENDING PAYMENTS / AGING (0-30, 31-60, 60+ DAYS)
    // =========================================================================
    public function getPaymentAgingReport(array $filters = []): array
    {
        $today = date('Y-m-d');
        $sql = "
            SELECT inv.id,
                   inv.invoice_no,
                   inv.title,
                   inv.net_amount,
                   inv.paid_amount,
                   inv.balance_amount,
                   inv.due_date,
                   inv.status,
                   DATEDIFF(?, inv.due_date) AS days_overdue,
                   CASE 
                       WHEN inv.client_id IS NOT NULL THEN c.name 
                       WHEN inv.student_id IS NOT NULL THEN s.name 
                       ELSE 'Unknown' 
                   END AS customer_name,
                   CASE 
                       WHEN inv.client_id IS NOT NULL THEN c.client_code 
                       WHEN inv.student_id IS NOT NULL THEN s.student_code 
                       ELSE 'N/A' 
                   END AS customer_code,
                   CASE 
                       WHEN inv.due_date >= ? THEN 'Current'
                       WHEN DATEDIFF(?, inv.due_date) BETWEEN 1 AND 30 THEN '0-30 Days'
                       WHEN DATEDIFF(?, inv.due_date) BETWEEN 31 AND 60 THEN '31-60 Days'
                       ELSE '60+ Days'
                   END AS aging_bucket
            FROM `invoices` inv
            LEFT JOIN `clients` c ON inv.client_id = c.id
            LEFT JOIN `students` s ON inv.student_id = s.id
            WHERE inv.status IN ('unpaid', 'partially_paid', 'overdue')
              AND inv.balance_amount > 0
              AND inv.deleted_at IS NULL
        ";
        $params = [$today, $today, $today, $today];

        if (!empty($filters['aging_bucket'])) {
            if ($filters['aging_bucket'] === '0-30') {
                $sql .= " AND DATEDIFF(?, inv.due_date) BETWEEN 1 AND 30";
                $params[] = $today;
            } elseif ($filters['aging_bucket'] === '31-60') {
                $sql .= " AND DATEDIFF(?, inv.due_date) BETWEEN 31 AND 60";
                $params[] = $today;
            } elseif ($filters['aging_bucket'] === '60+') {
                $sql .= " AND DATEDIFF(?, inv.due_date) > 60";
                $params[] = $today;
            }
        }

        $sql .= " ORDER BY days_overdue DESC, inv.due_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $buckets = [
            'current' => 0.0,
            '0-30' => 0.0,
            '31-60' => 0.0,
            '60+' => 0.0,
            'total_pending' => 0.0,
        ];

        foreach ($rows as $r) {
            $amt = (float)$r['balance_amount'];
            $buckets['total_pending'] += $amt;
            match ($r['aging_bucket']) {
                'Current' => $buckets['current'] += $amt,
                '0-30 Days' => $buckets['0-30'] += $amt,
                '31-60 Days' => $buckets['31-60'] += $amt,
                '60+ Days' => $buckets['60+'] += $amt,
                default => null,
            };
        }

        return [
            'title' => 'Pending Payments & Aging (0-30, 31-60, 60+ Days)',
            'rows' => $rows,
            'summary' => $buckets,
        ];
    }

    // =========================================================================
    // 6. STAFF PERFORMANCE (LEADS HANDLED, CONVERSIONS, COLLECTIONS)
    // =========================================================================
    public function getStaffPerformanceReport(array $filters = []): array
    {
        $sql = "
            SELECT u.id AS user_id,
                   u.name AS staff_name,
                   u.email AS staff_email,
                   r.name AS role_name,
                   (SELECT COUNT(*) FROM `leads` l WHERE l.assigned_to = u.id AND l.deleted_at IS NULL" . (!empty($filters['start_date']) ? " AND DATE(l.created_at) >= '{$filters['start_date']}'" : "") . (!empty($filters['end_date']) ? " AND DATE(l.created_at) <= '{$filters['end_date']}'" : "") . ") AS leads_handled,
                   (SELECT COUNT(*) FROM `leads` l WHERE l.assigned_to = u.id AND l.status = 'converted' AND l.deleted_at IS NULL" . (!empty($filters['start_date']) ? " AND DATE(l.created_at) >= '{$filters['start_date']}'" : "") . (!empty($filters['end_date']) ? " AND DATE(l.created_at) <= '{$filters['end_date']}'" : "") . ") AS conversions_count,
                   COALESCE((SELECT SUM(p.amount) FROM `payments` p WHERE p.received_by = u.id AND p.deleted_at IS NULL" . (!empty($filters['start_date']) ? " AND p.payment_date >= '{$filters['start_date']}'" : "") . (!empty($filters['end_date']) ? " AND p.payment_date <= '{$filters['end_date']}'" : "") . "), 0) AS total_collected
            FROM `users` u
            JOIN `roles` r ON u.role_id = r.id
            WHERE u.is_active = 1
        ";

        if (!empty($filters['staff_id'])) {
            $sql .= " AND u.id = " . (int)$filters['staff_id'];
        }

        $sql .= " ORDER BY total_collected DESC, conversions_count DESC";

        $stmt = $this->pdo->query($sql);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalLeads = 0;
        $totalConversions = 0;
        $totalCollections = 0.0;

        $rows = array_map(static function ($r) use (&$totalLeads, &$totalConversions, &$totalCollections) {
            $leads = (int)$r['leads_handled'];
            $conv = (int)$r['conversions_count'];
            $coll = (float)$r['total_collected'];

            $totalLeads += $leads;
            $totalConversions += $conv;
            $totalCollections += $coll;

            $rate = $leads > 0 ? round(($conv / $leads) * 100, 1) : 0.0;

            return array_merge($r, [
                'conversion_rate_pct' => $rate,
            ]);
        }, $rawRows);

        return [
            'title' => 'Staff Performance (Leads, Conversions, Collections)',
            'rows' => $rows,
            'summary' => [
                'total_leads_handled' => $totalLeads,
                'total_conversions' => $totalConversions,
                'total_collections' => $totalCollections,
            ],
        ];
    }

    // =========================================================================
    // 7. ATTENDANCE SUMMARY
    // =========================================================================
    public function getAttendanceSummaryReport(array $filters = []): array
    {
        $sql = "
            SELECT b.id AS batch_id,
                   b.batch_code,
                   b.name AS batch_name,
                   c.name AS course_name,
                   u.name AS trainer_name,
                   COUNT(a.id) AS total_marked_records,
                   SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                   SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                   SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_count,
                   SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) AS excused_count
            FROM `batches` b
            JOIN `courses` c ON b.course_id = c.id
            LEFT JOIN `users` u ON b.trainer_id = u.id
            LEFT JOIN `attendance` a ON b.id = a.batch_id
            WHERE b.deleted_at IS NULL
        ";
        $params = [];

        if (!empty($filters['course_id'])) {
            $sql .= " AND b.course_id = ?";
            $params[] = (int)$filters['course_id'];
        }
        if (!empty($filters['staff_id'])) {
            $sql .= " AND b.trainer_id = ?";
            $params[] = (int)$filters['staff_id'];
        }
        if (!empty($filters['start_date'])) {
            $sql .= " AND a.session_date >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND a.session_date <= ?";
            $params[] = $filters['end_date'];
        }

        $sql .= " GROUP BY b.id, b.batch_code, b.name, c.name, u.name ORDER BY b.start_date DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalRecords = 0;
        $totalPresent = 0;
        $totalLate = 0;

        $rows = array_map(static function ($r) use (&$totalRecords, &$totalPresent, &$totalLate) {
            $rec = (int)$r['total_marked_records'];
            $pres = (int)$r['present_count'];
            $late = (int)$r['late_count'];

            $totalRecords += $rec;
            $totalPresent += $pres;
            $totalLate += $late;

            $pct = $rec > 0 ? round((($pres + $late) / $rec) * 100, 1) : 0.0;

            return array_merge($r, [
                'attendance_pct' => $pct,
            ]);
        }, $rawRows);

        $overallPct = $totalRecords > 0 ? round((($totalPresent + $totalLate) / $totalRecords) * 100, 1) : 0.0;

        return [
            'title' => 'Attendance Summary by Batch',
            'rows' => $rows,
            'summary' => [
                'total_records' => $totalRecords,
                'overall_attendance_pct' => $overallPct,
            ],
        ];
    }
}
