<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

class Lead extends BaseModel
{
    protected string $table = 'leads';
    protected bool $softDelete = true;

    /**
     * Generate sequential lead code like LD-2026-0001
     */
    public function generateLeadCode(int $year): string
    {
        $pdo = $this->getPdo();
        $prefix = sprintf('LD-%d-', $year);

        $sql = "SELECT `lead_code` FROM `{$this->table}` WHERE `lead_code` LIKE ? ORDER BY `id` DESC LIMIT 1";
        try {
            $stmt = $pdo->prepare($sql . " FOR UPDATE");
            $stmt->execute([$prefix . '%']);
        } catch (Throwable) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$prefix . '%']);
        }

        $lastCode = $stmt->fetchColumn();

        if (!$lastCode || !is_string($lastCode)) {
            return sprintf('LD-%d-0001', $year);
        }

        $parts = explode('-', $lastCode);
        $seq = isset($parts[2]) ? (int)$parts[2] + 1 : 1;

        return sprintf('LD-%d-%04d', $year, $seq);
    }

    /**
     * Find lead by mobile number (checks active and non-deleted leads).
     */
    public function findByMobile(string $mobile): ?array
    {
        $clean = preg_replace('/[^0-9]/', '', $mobile);
        $stmt = $this->getPdo()->prepare("SELECT * FROM `{$this->table}` WHERE `mobile` = ? AND `deleted_at` IS NULL LIMIT 1");
        $stmt->execute([$clean]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Find scoped single lead.
     */
    public function findScoped(int|string $id, int $userId, bool $viewAll): ?array
    {
        $sql = "SELECT l.*,
                       ls.name AS source_name,
                       u.name AS assigned_to_name, u.email AS assigned_to_email,
                       cb.name AS created_by_name,
                       s.name AS service_name,
                       c.name AS course_name_detail,
                       cl.client_code AS converted_client_code, cl.name AS converted_client_name,
                       st.student_code AS converted_student_code, st.name AS converted_student_name
                FROM `{$this->table}` l
                LEFT JOIN `lead_sources` ls ON l.lead_source_id = ls.id
                LEFT JOIN `users` u ON l.assigned_to = u.id
                LEFT JOIN `users` cb ON l.created_by = cb.id
                LEFT JOIN `services` s ON l.service_id = s.id
                LEFT JOIN `courses` c ON l.course_id = c.id
                LEFT JOIN `clients` cl ON l.converted_client_id = cl.id
                LEFT JOIN `students` st ON l.converted_student_id = st.id
                WHERE l.id = ? AND l.deleted_at IS NULL";
        $params = [$id];

        if (!$viewAll) {
            $sql .= " AND (l.assigned_to = ? OR l.created_by = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        $stmt = $this->getPdo()->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Search and list leads with pagination and filters.
     *
     * @return array{items: array, total: int, counts: array<string, int>, page: int, per_page: int}
     */
    public function search(
        array $filters,
        int $userId,
        bool $viewAll,
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $pdo = $this->getPdo();
        $where = ["l.deleted_at IS NULL"];
        $params = [];

        // Scoping
        if (!$viewAll) {
            $where[] = "(l.assigned_to = ? OR l.created_by = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "l.status = ?";
            $params[] = $filters['status'];
        }

        // Source filter
        if (!empty($filters['lead_source_id'])) {
            $where[] = "l.lead_source_id = ?";
            $params[] = (int)$filters['lead_source_id'];
        } elseif (!empty($filters['source'])) {
            $where[] = "(ls.name = ? OR l.lead_source_id = ?)";
            $params[] = $filters['source'];
            $params[] = (int)$filters['source'];
        }

        // Assigned counselor filter
        if (!empty($filters['assigned_to'])) {
            $where[] = "l.assigned_to = ?";
            $params[] = (int)$filters['assigned_to'];
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $where[] = "l.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "l.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Search keyword
        $searchTerm = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($searchTerm !== '') {
            $like = '%' . $searchTerm . '%';
            $where[] = "(l.name LIKE ? OR l.mobile LIKE ? OR l.whatsapp_number LIKE ? OR l.email LIKE ? OR l.lead_code LIKE ? OR l.interested_in LIKE ? OR l.notes LIKE ?)";
            for ($i = 0; $i < 7; $i++) {
                $params[] = $like;
            }
        }

        $whereSql = implode(' AND ', $where);

        // Count total
        $countSql = "SELECT COUNT(*) FROM `{$this->table}` l 
                     LEFT JOIN `lead_sources` ls ON l.lead_source_id = ls.id 
                     WHERE {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Allowed sorts
        $allowedSorts = [
            'id' => 'l.id',
            'lead_code' => 'l.lead_code',
            'name' => 'l.name',
            'mobile' => 'l.mobile',
            'status' => 'l.status',
            'created_at' => 'l.created_at',
            'interested_in' => 'l.interested_in',
        ];
        $sortCol = $allowedSorts[$sortBy] ?? 'l.created_at';
        $sortDirection = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $offset = max(0, ($page - 1) * $perPage);
        $limit = max(1, min($perPage, 100));

        $dataSql = "SELECT l.*,
                           ls.name AS source_name,
                           u.name AS assigned_to_name,
                           cl.client_code AS converted_client_code,
                           st.student_code AS converted_student_code
                    FROM `{$this->table}` l
                    LEFT JOIN `lead_sources` ls ON l.lead_source_id = ls.id
                    LEFT JOIN `users` u ON l.assigned_to = u.id
                    LEFT JOIN `clients` cl ON l.converted_client_id = cl.id
                    LEFT JOIN `students` st ON l.converted_student_id = st.id
                    WHERE {$whereSql}
                    ORDER BY {$sortCol} {$sortDirection}, l.id DESC
                    LIMIT {$limit} OFFSET {$offset}";

        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll();

        // Status counts
        $counts = $this->getCountsByStatus($userId, $viewAll);

        return [
            'items' => $items,
            'total' => $total,
            'counts' => $counts,
            'page' => $page,
            'per_page' => $limit,
        ];
    }

    /**
     * Get grouped leads for Kanban board.
     */
    public function getKanbanData(array $filters, int $userId, bool $viewAll): array
    {
        $pdo = $this->getPdo();
        $where = ["l.deleted_at IS NULL"];
        $params = [];

        if (!$viewAll) {
            $where[] = "(l.assigned_to = ? OR l.created_by = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        if (!empty($filters['lead_source_id'])) {
            $where[] = "l.lead_source_id = ?";
            $params[] = (int)$filters['lead_source_id'];
        } elseif (!empty($filters['source'])) {
            $where[] = "(ls.name = ? OR l.lead_source_id = ?)";
            $params[] = $filters['source'];
            $params[] = (int)$filters['source'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = "l.assigned_to = ?";
            $params[] = (int)$filters['assigned_to'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "l.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "l.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $searchTerm = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($searchTerm !== '') {
            $like = '%' . $searchTerm . '%';
            $where[] = "(l.name LIKE ? OR l.mobile LIKE ? OR l.whatsapp_number LIKE ? OR l.email LIKE ? OR l.lead_code LIKE ? OR l.interested_in LIKE ? OR l.notes LIKE ?)";
            for ($i = 0; $i < 7; $i++) {
                $params[] = $like;
            }
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT l.*,
                       ls.name AS source_name,
                       u.name AS assigned_to_name,
                       cl.client_code AS converted_client_code,
                       st.student_code AS converted_student_code
                FROM `{$this->table}` l
                LEFT JOIN `lead_sources` ls ON l.lead_source_id = ls.id
                LEFT JOIN `users` u ON l.assigned_to = u.id
                LEFT JOIN `clients` cl ON l.converted_client_id = cl.id
                LEFT JOIN `students` st ON l.converted_student_id = st.id
                WHERE {$whereSql}
                ORDER BY l.updated_at DESC, l.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $columns = [
            'new' => [],
            'contacted' => [],
            'interested' => [],
            'follow_up' => [],
            'converted' => [],
            'lost' => [],
        ];

        foreach ($rows as $row) {
            $status = $row['status'] ?? 'new';
            if (isset($columns[$status])) {
                $columns[$status][] = $row;
            } else {
                $columns['new'][] = $row;
            }
        }

        return $columns;
    }

    /**
     * Compute counts for each status.
     */
    public function getCountsByStatus(int $userId, bool $viewAll): array
    {
        $pdo = $this->getPdo();
        $where = "deleted_at IS NULL";
        $params = [];

        if (!$viewAll) {
            $where .= " AND (assigned_to = ? OR created_by = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        $sql = "SELECT 
            SUM(CASE WHEN `status` = 'new' THEN 1 ELSE 0 END) AS count_new,
            SUM(CASE WHEN `status` = 'contacted' THEN 1 ELSE 0 END) AS count_contacted,
            SUM(CASE WHEN `status` = 'interested' THEN 1 ELSE 0 END) AS count_interested,
            SUM(CASE WHEN `status` = 'follow_up' THEN 1 ELSE 0 END) AS count_follow_up,
            SUM(CASE WHEN `status` = 'converted' THEN 1 ELSE 0 END) AS count_converted,
            SUM(CASE WHEN `status` = 'lost' THEN 1 ELSE 0 END) AS count_lost,
            COUNT(*) AS count_all
        FROM `{$this->table}`
        WHERE {$where}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'new' => (int)($row['count_new'] ?? 0),
            'contacted' => (int)($row['count_contacted'] ?? 0),
            'interested' => (int)($row['count_interested'] ?? 0),
            'follow_up' => (int)($row['count_follow_up'] ?? 0),
            'converted' => (int)($row['count_converted'] ?? 0),
            'lost' => (int)($row['count_lost'] ?? 0),
            'all' => (int)($row['count_all'] ?? 0),
        ];
    }
}
