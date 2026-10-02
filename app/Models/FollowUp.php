<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

class FollowUp extends BaseModel
{
    protected string $table = 'follow_ups';
    protected bool $softDelete = true;

    private ?bool $leadsTableExists = null;

    private function hasLeadsTable(): bool
    {
        if ($this->leadsTableExists !== null) {
            return $this->leadsTableExists;
        }
        try {
            $this->getPdo()->query("SELECT 1 FROM `leads` LIMIT 1");
            $this->leadsTableExists = true;
        } catch (Throwable) {
            $this->leadsTableExists = false;
        }
        return $this->leadsTableExists;
    }

    /**
     * Find single follow-up with client/lead and user details, enforcing scoping.
     */
    public function findScoped(int|string $id, int $userId, bool $viewAll): ?array
    {
        $hasLeads = $this->hasLeadsTable();

        if ($hasLeads) {
            $sql = "SELECT f.*, 
                           c.name AS client_name, c.client_code, c.mobile AS client_mobile, c.assigned_to AS client_assigned_to,
                           l.name AS lead_name, l.lead_code, l.mobile AS lead_mobile, l.assigned_to AS lead_assigned_to,
                           COALESCE(c.name, l.name) AS contact_name,
                           COALESCE(c.client_code, l.lead_code) AS contact_code,
                           COALESCE(c.whatsapp_number, l.whatsapp_number, c.mobile, l.mobile) AS contact_whatsapp,
                           COALESCE(c.mobile, l.mobile) AS contact_mobile,
                           CASE WHEN f.lead_id IS NOT NULL THEN 'lead' ELSE 'client' END AS entity_type,
                           u.name AS user_name, u.email AS user_email
                    FROM `{$this->table}` f
                    LEFT JOIN `clients` c ON f.client_id = c.id
                    LEFT JOIN `leads` l ON f.lead_id = l.id
                    LEFT JOIN `users` u ON f.user_id = u.id
                    WHERE f.id = ? AND f.deleted_at IS NULL 
                      AND (c.id IS NULL OR c.deleted_at IS NULL)
                      AND (l.id IS NULL OR l.deleted_at IS NULL)";
            $params = [$id];

            if (!$viewAll) {
                $sql .= " AND (c.assigned_to = ? OR l.assigned_to = ? OR f.user_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            }
        } else {
            $sql = "SELECT f.*, 
                           c.name AS client_name, c.client_code, c.assigned_to AS client_assigned_to,
                           c.name AS contact_name, c.client_code AS contact_code,
                           c.mobile AS contact_mobile, c.mobile AS contact_whatsapp,
                           'client' AS entity_type,
                           u.name AS user_name, u.email AS user_email
                    FROM `{$this->table}` f
                    JOIN `clients` c ON f.client_id = c.id
                    LEFT JOIN `users` u ON f.user_id = u.id
                    WHERE f.id = ? AND f.deleted_at IS NULL AND c.deleted_at IS NULL";
            $params = [$id];

            if (!$viewAll) {
                $sql .= " AND (c.assigned_to = ? OR f.user_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
            }
        }

        $stmt = $this->getPdo()->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Get all follow-ups for a specific client, scoped by user permissions.
     */
    public function getByClient(int|string $clientId, int $userId, bool $viewAll): array
    {
        $sql = "SELECT f.*, 
                       c.name AS client_name, c.client_code, c.assigned_to AS client_assigned_to,
                       c.name AS contact_name, c.client_code AS contact_code,
                       c.mobile AS contact_mobile,
                       'client' AS entity_type,
                       u.name AS user_name, u.email AS user_email
                FROM `{$this->table}` f
                JOIN `clients` c ON f.client_id = c.id
                LEFT JOIN `users` u ON f.user_id = u.id
                WHERE f.client_id = ? AND f.deleted_at IS NULL AND c.deleted_at IS NULL";
        $params = [$clientId];

        if (!$viewAll) {
            $sql .= " AND (c.assigned_to = ? OR f.user_id = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        $sql .= " ORDER BY f.due_at DESC, f.id DESC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all follow-ups for a specific lead, scoped by user permissions.
     */
    public function getByLead(int|string $leadId, int $userId, bool $viewAll): array
    {
        if (!$this->hasLeadsTable()) {
            return [];
        }

        $sql = "SELECT f.*, 
                       l.name AS lead_name, l.lead_code, l.assigned_to AS lead_assigned_to,
                       l.name AS contact_name, l.lead_code AS contact_code,
                       l.mobile AS contact_mobile,
                       'lead' AS entity_type,
                       u.name AS user_name, u.email AS user_email
                FROM `{$this->table}` f
                JOIN `leads` l ON f.lead_id = l.id
                LEFT JOIN `users` u ON f.user_id = u.id
                WHERE f.lead_id = ? AND f.deleted_at IS NULL AND l.deleted_at IS NULL";
        $params = [$leadId];

        if (!$viewAll) {
            $sql .= " AND (l.assigned_to = ? OR f.user_id = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        $sql .= " ORDER BY f.due_at DESC, f.id DESC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Search and list follow-ups with tab, type, client/lead filters and scoping.
     *
     * @return array{items: array, total: int, counts: array<string, int>, page: int, per_page: int}
     */
    public function search(
        array $filters,
        int $userId,
        bool $viewAll,
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'due_at',
        string $sortDir = 'ASC'
    ): array {
        $pdo = $this->getPdo();
        $now = date('Y-m-d H:i:s');
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $hasLeads = $this->hasLeadsTable();

        $where = ["f.deleted_at IS NULL"];
        $params = [];

        if ($hasLeads) {
            $where[] = "(c.id IS NULL OR c.deleted_at IS NULL)";
            $where[] = "(l.id IS NULL OR l.deleted_at IS NULL)";
            $where[] = "(f.client_id IS NOT NULL OR f.lead_id IS NOT NULL)";

            if (!$viewAll) {
                $where[] = "(c.assigned_to = ? OR l.assigned_to = ? OR f.user_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            }

            if (!empty($filters['lead_id'])) {
                $where[] = "f.lead_id = ?";
                $params[] = (int)$filters['lead_id'];
            }
        } else {
            $where[] = "c.deleted_at IS NULL";
            if (!$viewAll) {
                $where[] = "(c.assigned_to = ? OR f.user_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
            }
        }

        // Specific client filter
        if (!empty($filters['client_id'])) {
            $where[] = "f.client_id = ?";
            $params[] = (int)$filters['client_id'];
        }

        // Specific type filter
        if (!empty($filters['type'])) {
            $where[] = "f.type = ?";
            $params[] = $filters['type'];
        }

        // Search term
        $searchTerm = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($searchTerm !== '') {
            $like = '%' . $searchTerm . '%';
            if ($hasLeads) {
                $where[] = "(c.name LIKE ? OR c.client_code LIKE ? OR l.name LIKE ? OR l.lead_code LIKE ? OR f.notes LIKE ? OR f.remarks LIKE ?)";
                for ($i = 0; $i < 6; $i++) {
                    $params[] = $like;
                }
            } else {
                $where[] = "(c.name LIKE ? OR c.client_code LIKE ? OR f.notes LIKE ?)";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        // Tab or status filtering
        $tab = strtolower(trim((string)($filters['tab'] ?? '')));
        if ($tab === 'today') {
            $where[] = "f.due_at >= ? AND f.due_at <= ?";
            $params[] = $todayStart;
            $params[] = $todayEnd;
        } elseif ($tab === 'upcoming') {
            $where[] = "f.due_at > ? AND f.status = 'pending'";
            $params[] = $now;
        } elseif ($tab === 'overdue') {
            $where[] = "f.due_at < ? AND f.status = 'pending'";
            $params[] = $now;
        } elseif ($tab === 'done') {
            $where[] = "f.status = 'done'";
        } elseif (!empty($filters['status']) && in_array($filters['status'], ['pending', 'done', 'missed'], true)) {
            $where[] = "f.status = ?";
            $params[] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $fromSql = "FROM `{$this->table}` f LEFT JOIN `clients` c ON f.client_id = c.id";
        if ($hasLeads) {
            $fromSql .= " LEFT JOIN `leads` l ON f.lead_id = l.id";
        }

        // Count total
        $countSql = "SELECT COUNT(*) {$fromSql} WHERE {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Sorting
        $allowedSorts = [
            'id' => 'f.id',
            'due_at' => 'f.due_at',
            'type' => 'f.type',
            'status' => 'f.status',
            'created_at' => 'f.created_at',
            'client_name' => 'c.name',
        ];
        $sortColumn = $allowedSorts[$sortBy] ?? 'f.due_at';
        $sortDirection = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $offset = max(0, ($page - 1) * $perPage);
        $limit = max(1, min($perPage, 100));

        if ($hasLeads) {
            $dataSql = "SELECT f.*, 
                               c.name AS client_name, c.client_code, c.mobile AS client_mobile, c.assigned_to AS client_assigned_to,
                               l.name AS lead_name, l.lead_code, l.mobile AS lead_mobile, l.assigned_to AS lead_assigned_to,
                               COALESCE(c.name, l.name) AS contact_name,
                               COALESCE(c.client_code, l.lead_code) AS contact_code,
                               COALESCE(c.whatsapp_number, l.whatsapp_number, c.mobile, l.mobile) AS contact_whatsapp,
                               COALESCE(c.mobile, l.mobile) AS contact_mobile,
                               CASE WHEN f.lead_id IS NOT NULL THEN 'lead' ELSE 'client' END AS entity_type,
                               u.name AS user_name, u.email AS user_email
                        {$fromSql}
                        LEFT JOIN `users` u ON f.user_id = u.id
                        WHERE {$whereSql}
                        ORDER BY {$sortColumn} {$sortDirection}, f.id DESC
                        LIMIT {$limit} OFFSET {$offset}";
        } else {
            $dataSql = "SELECT f.*, 
                               c.name AS client_name, c.client_code, c.assigned_to AS client_assigned_to,
                               c.name AS contact_name, c.client_code AS contact_code,
                               c.mobile AS contact_mobile, c.mobile AS contact_whatsapp,
                               'client' AS entity_type,
                               u.name AS user_name, u.email AS user_email
                        {$fromSql}
                        LEFT JOIN `users` u ON f.user_id = u.id
                        WHERE {$whereSql}
                        ORDER BY {$sortColumn} {$sortDirection}, f.id DESC
                        LIMIT {$limit} OFFSET {$offset}";
        }

        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll() ?: [];

        // Calculate counts for tabs
        $counts = $this->getTabCounts(
            $userId,
            $viewAll,
            !empty($filters['client_id']) ? (int)$filters['client_id'] : null,
            !empty($filters['lead_id']) ? (int)$filters['lead_id'] : null
        );

        return [
            'items' => $items,
            'total' => $total,
            'counts' => $counts,
            'page' => $page,
            'per_page' => $limit,
        ];
    }

    /**
     * Compute badge counts for Today, Upcoming, Overdue, and Done tabs.
     */
    public function getTabCounts(int $userId, bool $viewAll, ?int $clientId = null, ?int $leadId = null): array
    {
        $pdo = $this->getPdo();
        $now = date('Y-m-d H:i:s');
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $hasLeads = $this->hasLeadsTable();

        $baseWhere = ["f.deleted_at IS NULL"];
        $baseParams = [];

        if ($hasLeads) {
            $baseWhere[] = "(c.id IS NULL OR c.deleted_at IS NULL)";
            $baseWhere[] = "(l.id IS NULL OR l.deleted_at IS NULL)";
            $baseWhere[] = "(f.client_id IS NOT NULL OR f.lead_id IS NOT NULL)";

            if (!$viewAll) {
                $baseWhere[] = "(c.assigned_to = ? OR l.assigned_to = ? OR f.user_id = ?)";
                $baseParams[] = $userId;
                $baseParams[] = $userId;
                $baseParams[] = $userId;
            }

            if ($leadId !== null && $leadId > 0) {
                $baseWhere[] = "f.lead_id = ?";
                $baseParams[] = $leadId;
            }
        } else {
            $baseWhere[] = "c.deleted_at IS NULL";
            if (!$viewAll) {
                $baseWhere[] = "(c.assigned_to = ? OR f.user_id = ?)";
                $baseParams[] = $userId;
                $baseParams[] = $userId;
            }
        }

        if ($clientId !== null && $clientId > 0) {
            $baseWhere[] = "f.client_id = ?";
            $baseParams[] = $clientId;
        }

        $baseWhereSql = implode(' AND ', $baseWhere);
        $fromSql = "FROM `{$this->table}` f LEFT JOIN `clients` c ON f.client_id = c.id";
        if ($hasLeads) {
            $fromSql .= " LEFT JOIN `leads` l ON f.lead_id = l.id";
        }

        $sql = "SELECT 
            SUM(CASE WHEN f.due_at >= ? AND f.due_at <= ? THEN 1 ELSE 0 END) AS count_today,
            SUM(CASE WHEN f.due_at > ? AND f.status = 'pending' THEN 1 ELSE 0 END) AS count_upcoming,
            SUM(CASE WHEN f.due_at < ? AND f.status = 'pending' THEN 1 ELSE 0 END) AS count_overdue,
            SUM(CASE WHEN f.status = 'done' THEN 1 ELSE 0 END) AS count_done,
            COUNT(*) AS count_all
        {$fromSql}
        WHERE {$baseWhereSql}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$todayStart, $todayEnd, $now, $now], $baseParams));
        $row = $stmt->fetch();

        return [
            'today' => (int)($row['count_today'] ?? 0),
            'upcoming' => (int)($row['count_upcoming'] ?? 0),
            'overdue' => (int)($row['count_overdue'] ?? 0),
            'done' => (int)($row['count_done'] ?? 0),
            'all' => (int)($row['count_all'] ?? 0),
        ];
    }

    /**
     * Mark follow-up as done with outcome, remarks, and outcome note.
     */
    public function markDone(
        int|string $id,
        ?string $outcomeNote = null,
        ?string $outcome = null,
        ?string $remarks = null,
        ?string $nextFollowUpAt = null
    ): bool {
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }

        $notes = $existing['notes'] ?? '';
        if ($outcomeNote !== null && trim($outcomeNote) !== '') {
            $trimmedOutcome = trim($outcomeNote);
            $notes = $notes !== '' ? $notes . "\n\n[Outcome]: " . $trimmedOutcome : "[Outcome]: " . $trimmedOutcome;
        }

        $updateData = [
            'status' => 'done',
            'notes' => $notes,
        ];

        if ($outcome !== null && $outcome !== '') {
            $updateData['outcome'] = $outcome;
        }
        if ($remarks !== null && $remarks !== '') {
            $updateData['remarks'] = $remarks;
        }
        if ($nextFollowUpAt !== null && $nextFollowUpAt !== '') {
            $updateData['next_follow_up_at'] = $nextFollowUpAt;
        }

        return $this->update($id, $updateData);
    }

    /**
     * Cron helper: update past-due pending follow-ups to 'missed'.
     */
    public function markMissedPastDue(?string $threshold = null): int
    {
        $threshold = $threshold ?? date('Y-m-d H:i:s');
        $sql = "UPDATE `{$this->table}` 
                SET `status` = 'missed', `updated_at` = ? 
                WHERE `status` = 'pending' AND `due_at` < ? AND `deleted_at` IS NULL";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([date('Y-m-d H:i:s'), $threshold]);
        return $stmt->rowCount();
    }

    /**
     * Cron helper: get pending follow-ups due today grouped by user.
     */
    public function getPendingDueToday(): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $sql = "SELECT f.*, 
                       COALESCE(c.name, l.name) AS contact_name,
                       COALESCE(c.client_code, l.lead_code) AS contact_code,
                       COALESCE(c.mobile, l.mobile) AS contact_mobile,
                       u.id AS user_id, u.name AS user_name, u.email AS user_email
                FROM `{$this->table}` f
                LEFT JOIN `clients` c ON f.client_id = c.id
                LEFT JOIN `leads` l ON f.lead_id = l.id
                JOIN `users` u ON f.user_id = u.id
                WHERE f.due_at >= ? AND f.due_at <= ?
                  AND f.status = 'pending'
                  AND f.deleted_at IS NULL
                  AND (c.id IS NULL OR c.deleted_at IS NULL)
                  AND (l.id IS NULL OR l.deleted_at IS NULL)
                  AND u.deleted_at IS NULL
                  AND u.is_active = 1
                ORDER BY f.user_id ASC, f.due_at ASC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$todayStart, $todayEnd]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Dashboard: Get pending follow-ups due today scoped by permissions.
     */
    public function getTodayPendingFollowUps(int $userId, bool $viewAll, int $limit = 5): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $hasLeads = $this->hasLeadsTable();

        if ($hasLeads) {
            $sql = "SELECT f.*, 
                           COALESCE(c.name, l.name) AS contact_name,
                           COALESCE(c.client_code, l.lead_code) AS contact_code,
                           c.name AS client_name, c.client_code,
                           u.name AS user_name
                    FROM `{$this->table}` f
                    LEFT JOIN `clients` c ON f.client_id = c.id
                    LEFT JOIN `leads` l ON f.lead_id = l.id
                    LEFT JOIN `users` u ON f.user_id = u.id
                    WHERE f.due_at >= ? AND f.due_at <= ?
                      AND f.status = 'pending'
                      AND f.deleted_at IS NULL
                      AND (c.id IS NULL OR c.deleted_at IS NULL)
                      AND (l.id IS NULL OR l.deleted_at IS NULL)";
            $params = [$todayStart, $todayEnd];

            if (!$viewAll) {
                $sql .= " AND (c.assigned_to = ? OR l.assigned_to = ? OR f.user_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            }
        } else {
            $sql = "SELECT f.*, 
                           c.name AS client_name, c.client_code, c.name AS contact_name,
                           u.name AS user_name
                    FROM `{$this->table}` f
                    JOIN `clients` c ON f.client_id = c.id
                    LEFT JOIN `users` u ON f.user_id = u.id
                    WHERE f.due_at >= ? AND f.due_at <= ?
                      AND f.status = 'pending'
                      AND f.deleted_at IS NULL
                      AND c.deleted_at IS NULL";
            $params = [$todayStart, $todayEnd];

            if (!$viewAll) {
                $sql .= " AND (c.assigned_to = ? OR f.user_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
            }
        }

        $safeLimit = max(1, min((int)$limit, 100));
        $sql .= " ORDER BY f.due_at ASC LIMIT {$safeLimit}";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}
