<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\Client;
use App\Models\FollowUp;
use App\Services\Cache\Cache;
use PDO;

class DashboardService
{
    private PDO $pdo;
    private Client $clientModel;
    private FollowUp $followUpModel;

    public function __construct(?Client $clientModel = null, ?FollowUp $followUpModel = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->clientModel = $clientModel ?? new Client($this->pdo);
        $this->followUpModel = $followUpModel ?? new FollowUp($this->pdo);
    }

    /**
     * Get aggregated statistics for the dashboard, strictly scoped by role.
     * Cached for 5 minutes (300 seconds) per user & role scope.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('role', 'counselor');
        $viewAll = PermissionService::can('client.view_all');

        $cacheKey = "dashboard:stats:{$userRole}:" . ($viewAll ? 'all' : (string)$userId);

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, 300, function () use ($userId, $userRole, $viewAll): array {
            return $this->computeStats($userId, $userRole, $viewAll);
        });
    }

    /**
     * Compute fresh dashboard statistics.
     *
     * @return array<string, mixed>
     */
    public function computeStats(int $userId, string $userRole, bool $viewAll): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01 00:00:00');
        $isTrainer = ($userRole === 'trainer');
        $isFinancialUser = in_array($userRole, ['admin', 'manager', 'accountant'], true);

        // =====================================================================
        // 1. LEADS METRICS
        // =====================================================================
        $leadSql = "SELECT COUNT(*) AS total,
                           SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) AS today_count,
                           SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS month_count,
                           SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted_count
                    FROM `leads`
                    WHERE `deleted_at` IS NULL";
        $leadParams = [$today, $monthStart];

        if (!$viewAll && $userRole === 'counselor') {
            $leadSql .= " AND (assigned_to = ? OR assigned_to IS NULL)";
            $leadParams[] = $userId;
        } elseif ($isTrainer) {
            // Trainers do not manage leads
            $leadSql .= " AND 1=0";
        }

        $stmt = $this->pdo->prepare($leadSql);
        $stmt->execute($leadParams);
        $leadStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalLeads = (int)($leadStats['total'] ?? 0);
        $newLeadsToday = (int)($leadStats['today_count'] ?? 0);
        $newLeadsMonth = (int)($leadStats['month_count'] ?? 0);
        $convertedCustomers = (int)($leadStats['converted_count'] ?? 0);

        // Also add active clients count if financial user or viewAll
        if ($viewAll || $isFinancialUser) {
            $clientCountStmt = $this->pdo->query("SELECT COUNT(*) FROM `clients` WHERE `status` = 'active' AND `deleted_at` IS NULL");
            $activeClientsCount = (int)$clientCountStmt->fetchColumn();
            $convertedCustomers = max($convertedCustomers, $activeClientsCount);
        }

        // =====================================================================
        // 2. FOLLOW-UPS METRICS
        // =====================================================================
        $followUpCounts = $this->followUpModel->getTabCounts($userId, $viewAll);
        $todayFollowUps = $this->followUpModel->getTodayPendingFollowUps($userId, $viewAll, 5);

        // =====================================================================
        // 3. ACTIVE STUDENTS METRICS
        // =====================================================================
        if ($isTrainer) {
            // Scoped to trainer's batches
            $studentSql = "SELECT COUNT(DISTINCT e.student_id)
                           FROM `enrollments` e
                           JOIN `batches` b ON e.batch_id = b.id
                           WHERE b.trainer_id = ?
                             AND e.status = 'active'
                             AND e.deleted_at IS NULL
                             AND b.deleted_at IS NULL";
            $stmt = $this->pdo->prepare($studentSql);
            $stmt->execute([$userId]);
            $activeStudents = (int)$stmt->fetchColumn();
        } else {
            $studentSql = "SELECT COUNT(*) FROM `students` WHERE `status` IN ('active', 'enrolled') AND `deleted_at` IS NULL";
            $activeStudents = (int)$this->pdo->query($studentSql)->fetchColumn();
        }

        // =====================================================================
        // 4. FINANCIAL METRICS (Redacted for Trainer)
        // =====================================================================
        $revenueThisMonth = null;
        $totalDue = null;

        if ($isFinancialUser || $userRole === 'counselor') {
            // Revenue this month (payments recorded)
            $paySql = "SELECT COALESCE(SUM(amount), 0)
                       FROM `payments`
                       WHERE `payment_date` >= ? AND `deleted_at` IS NULL";
            $stmt = $this->pdo->prepare($paySql);
            $stmt->execute([substr($monthStart, 0, 10)]);
            $revenueThisMonth = (float)$stmt->fetchColumn();

            // Total due across active invoices
            $invSql = "SELECT COALESCE(SUM(balance_amount), 0)
                       FROM `invoices`
                       WHERE `status` IN ('unpaid', 'partially_paid', 'overdue')
                         AND `deleted_at` IS NULL";
            $totalDue = (float)$this->pdo->query($invSql)->fetchColumn();
        }

        // =====================================================================
        // 5. UPCOMING COMPLIANCE REMINDERS
        // =====================================================================
        $remSql = "SELECT COUNT(*) FROM `reminders` WHERE `status` != 'done' AND `due_date` >= ? AND `deleted_at` IS NULL";
        $remParams = [$today];
        if (!$viewAll && in_array($userRole, ['counselor', 'trainer'], true)) {
            $remSql .= " AND assigned_user_id = ?";
            $remParams[] = $userId;
        }
        $stmt = $this->pdo->prepare($remSql);
        $stmt->execute($remParams);
        $upcomingRemindersCount = (int)$stmt->fetchColumn();

        // Get top 5 upcoming reminders for quick widget
        $remListSql = "SELECT r.id, r.title, r.due_date, r.period, r.entity_type,
                              CASE WHEN r.entity_type = 'client' THEN c.name ELSE s.name END AS entity_name
                       FROM `reminders` r
                       LEFT JOIN `clients` c ON r.entity_type = 'client' AND r.entity_id = c.id
                       LEFT JOIN `students` s ON r.entity_type = 'student' AND r.entity_id = s.id
                       WHERE r.status != 'done' AND r.due_date >= ? AND r.deleted_at IS NULL";
        if (!$viewAll && in_array($userRole, ['counselor', 'trainer'], true)) {
            $remListSql .= " AND r.assigned_user_id = {$userId}";
        }
        $remListSql .= " ORDER BY r.due_date ASC LIMIT 5";
        $stmt = $this->pdo->prepare($remListSql);
        $stmt->execute([$today]);
        $upcomingRemindersList = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // =====================================================================
        // 6. CHARTS DATA
        // =====================================================================

        // Chart 1: Leads by Source
        $sourceSql = "SELECT COALESCE(ls.name, 'Direct / Walk-in') AS source_name, COUNT(l.id) AS count
                      FROM `leads` l
                      LEFT JOIN `lead_sources` ls ON l.lead_source_id = ls.id
                      WHERE l.deleted_at IS NULL
                      GROUP BY COALESCE(ls.name, 'Direct / Walk-in')
                      ORDER BY count DESC";
        $leadsBySource = $this->pdo->query($sourceSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Chart 2: Lead Conversion Funnel
        $funnelStages = [
            'new' => 'New Inquiries',
            'contacted' => 'Contacted',
            'interested' => 'Interested / Qualified',
            'follow_up' => 'Follow-up Active',
            'converted' => 'Converted',
            'lost' => 'Lost / Dropped',
        ];
        $funnelSql = "SELECT status, COUNT(*) AS count FROM `leads` WHERE `deleted_at` IS NULL GROUP BY status";
        $stageCountsRaw = $this->pdo->query($funnelSql)->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $conversionFunnel = [];
        foreach ($funnelStages as $k => $label) {
            $conversionFunnel[] = [
                'stage' => $k,
                'label' => $label,
                'count' => (int)($stageCountsRaw[$k] ?? 0),
            ];
        }

        // Chart 3: Monthly Revenue Trend (Last 6 Months)
        $monthlyRevenue = [];
        if ($isFinancialUser || $userRole === 'counselor') {
            for ($i = 5; $i >= 0; $i--) {
                $time = strtotime("-{$i} months");
                $ym = date('Y-m', $time);
                $label = date('M Y', $time);
                $monthlyRevenue[$ym] = [
                    'month' => $ym,
                    'label' => $label,
                    'revenue' => 0.00,
                ];
            }
            $startDate = array_key_first($monthlyRevenue) . '-01';
            $revSql = "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total
                       FROM `payments`
                       WHERE payment_date >= ? AND deleted_at IS NULL
                       GROUP BY DATE_FORMAT(payment_date, '%Y-%m')";
            $stmt = $this->pdo->prepare($revSql);
            $stmt->execute([$startDate]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($monthlyRevenue[$row['ym']])) {
                    $monthlyRevenue[$row['ym']]['revenue'] = (float)$row['total'];
                }
            }
            $monthlyRevenue = array_values($monthlyRevenue);
        }

        return [
            'role' => $userRole,
            'is_trainer' => $isTrainer,
            'can_view_financial' => ($revenueThisMonth !== null),
            'metrics' => [
                'total_leads' => $totalLeads,
                'new_leads_today' => $newLeadsToday,
                'new_leads_month' => $newLeadsMonth,
                'follow_ups_today' => $followUpCounts['today'] ?? 0,
                'follow_ups_overdue' => $followUpCounts['overdue'] ?? 0,
                'converted_customers' => $convertedCustomers,
                'active_students' => $activeStudents,
                'revenue_this_month' => $revenueThisMonth,
                'total_due' => $totalDue,
                'upcoming_compliance_reminders' => $upcomingRemindersCount,
            ],
            'charts' => [
                'leads_by_source' => $leadsBySource,
                'conversion_funnel' => $conversionFunnel,
                'monthly_revenue' => $monthlyRevenue,
            ],
            'today_follow_ups' => $todayFollowUps,
            'upcoming_reminders' => $upcomingRemindersList,
        ];
    }
}
