<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\Client;
use App\Models\FollowUp;
use App\Services\Cache\Cache;

class DashboardService
{
    private Client $clientModel;
    private FollowUp $followUpModel;

    public function __construct(?Client $clientModel = null, ?FollowUp $followUpModel = null)
    {
        $this->clientModel = $clientModel ?? new Client();
        $this->followUpModel = $followUpModel ?? new FollowUp();
    }

    /**
     * Get aggregated statistics for the dashboard, strictly scoped by role.
     * Cached for 5 minutes per user scope.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $viewAll = PermissionService::can('client.view_all');

        $cacheKey = $viewAll ? 'dashboard:stats:view_all' : 'dashboard:stats:user_' . $userId;

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, 300, function () use ($userId, $viewAll): array {
            return $this->computeStats($userId, $viewAll);
        });
    }

    /**
     * Compute fresh dashboard statistics.
     *
     * @return array<string, mixed>
     */
    public function computeStats(int $userId, bool $viewAll): array
    {
        $monthStart = date('Y-m-01 00:00:00');

        // 1. Client totals
        $clientTotals = $this->clientModel->getClientTotals($userId, $viewAll, $monthStart);

        // 2. Status breakdown
        $byStatus = $this->clientModel->getCountsByStatus($userId, $viewAll);

        // 3. Lead source breakdown
        $byLeadSource = $this->clientModel->getCountsByLeadSource($userId, $viewAll);

        // 4. Past 12 months new clients trend
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $time = strtotime("-{$i} months");
            $ym = date('Y-m', $time);
            $label = date('M Y', $time);
            $months[$ym] = [
                'month' => $ym,
                'label' => $label,
                'count' => 0,
            ];
        }
        $startDate = array_key_first($months) . '-01 00:00:00';
        $monthlyDbCounts = $this->clientModel->getMonthlyNewClients($userId, $viewAll, $startDate);
        foreach ($monthlyDbCounts as $ym => $cnt) {
            if (isset($months[$ym])) {
                $months[$ym]['count'] = (int)$cnt;
            }
        }
        $monthlyTrend = array_values($months);

        // 5. Follow-ups stats & today's list
        $followUpCounts = $this->followUpModel->getTabCounts($userId, $viewAll);
        $todayFollowUps = $this->followUpModel->getTodayPendingFollowUps($userId, $viewAll, 6);

        // 6. Recent clients
        $recentClients = $this->clientModel->getRecentClients($userId, $viewAll, 6);

        // 7. Top staff this month (Admin & Manager only)
        $topStaff = $viewAll ? $this->clientModel->getTopStaffThisMonth($monthStart, 5) : [];

        return [
            'total_clients' => $clientTotals['total'],
            'new_this_month' => $clientTotals['new_this_month'],
            'by_status' => $byStatus,
            'by_lead_source' => $byLeadSource,
            'monthly_new_clients' => $monthlyTrend,
            'follow_ups' => [
                'today_count' => $followUpCounts['today'],
                'overdue_count' => $followUpCounts['overdue'],
                'upcoming_count' => $followUpCounts['upcoming'],
                'done_count' => $followUpCounts['done'],
                'today_list' => $todayFollowUps,
            ],
            'recent_clients' => $recentClients,
            'top_staff' => $topStaff,
            'can_view_all' => $viewAll,
        ];
    }
}
