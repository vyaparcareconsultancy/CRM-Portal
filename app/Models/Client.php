<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Crypto;
use PDO;
use Throwable;

class Client extends BaseModel
{
    protected string $table = 'clients';
    protected bool $softDelete = true;

    public function find(int|string $id): ?array
    {
        $row = parent::find($id);
        if ($row !== null && isset($row['pan_no'])) {
            $row['pan_no'] = Crypto::decryptPan($row['pan_no']);
        }
        return $row;
    }

    public function insert(array $data): int|string
    {
        if (isset($data['pan_no']) && $data['pan_no'] !== null && $data['pan_no'] !== '') {
            if (preg_match('/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/', (string)$data['pan_no'])) {
                $data['pan_no'] = Crypto::encryptPan((string)$data['pan_no']);
            }
        }
        return parent::insert($data);
    }

    public function update(int|string $id, array $data): bool
    {
        if (isset($data['pan_no'])) {
            if (Crypto::isMasked((string)$data['pan_no'])) {
                unset($data['pan_no']);
            } elseif ($data['pan_no'] !== null && $data['pan_no'] !== '') {
                if (preg_match('/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/', (string)$data['pan_no'])) {
                    $data['pan_no'] = Crypto::encryptPan((string)$data['pan_no']);
                }
            }
        }
        return parent::update($id, $data);
    }

    public function findScoped(int|string $id, int $userId, bool $viewAll): ?array
    {
        $sql = "SELECT c.*, u.name AS assigned_to_name, u.email AS assigned_to_email
                FROM `{$this->table}` c
                LEFT JOIN `users` u ON c.assigned_to = u.id
                WHERE c.id = ? AND c.deleted_at IS NULL";
        $params = [$id];

        if (!$viewAll) {
            $sql .= " AND c.assigned_to = ?";
            $params[] = $userId;
        }

        $stmt = $this->getPdo()->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();

        if ($row !== false && isset($row['pan_no'])) {
            $row['pan_no'] = Crypto::decryptPan($row['pan_no']);
        }

        return $row !== false ? $row : null;
    }

    public function deleteScoped(int|string $id, int $userId, bool $viewAll): bool
    {
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = ? WHERE `id` = ? AND `deleted_at` IS NULL";
        $params = [date('Y-m-d H:i:s'), $id];

        if (!$viewAll) {
            $sql .= " AND `assigned_to` = ?";
            $params[] = $userId;
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function updateScoped(int|string $id, int $userId, bool $viewAll, array $data): bool
    {
        if (!$viewAll) {
            $client = $this->findScoped($id, $userId, false);
            if ($client === null) {
                return false;
            }
        }

        return $this->update($id, $data);
    }

    public function searchClients(
        array $filters,
        int $userId,
        bool $viewAll,
        int $page = 1,
        int $perPage = 15,
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        $pdo = $this->getPdo();
        $where = ["c.deleted_at IS NULL"];
        $params = [];

        // Data scoping
        if (!$viewAll) {
            $where[] = "c.assigned_to = ?";
            $params[] = $userId;
        } elseif (!empty($filters['assigned_to'])) {
            $where[] = "c.assigned_to = ?";
            $params[] = (int)$filters['assigned_to'];
        }

        // Search term
        $searchTerm = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($searchTerm !== '') {
            $like = '%' . $searchTerm . '%';
            $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.mobile LIKE ? OR c.client_code LIKE ? OR c.gst_no LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Filters
        if (!empty($filters['status']) && in_array($filters['status'], ['new', 'active', 'inactive'], true)) {
            $where[] = "c.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['city'])) {
            $where[] = "c.city LIKE ?";
            $params[] = '%' . trim((string)$filters['city']) . '%';
        }

        if (!empty($filters['state'])) {
            $where[] = "c.state = ?";
            $params[] = trim((string)$filters['state']);
        }

        if (!empty($filters['lead_source'])) {
            $where[] = "c.lead_source = ?";
            $params[] = trim((string)$filters['lead_source']);
        }

        if (!empty($filters['date_from'])) {
            $from = trim((string)$filters['date_from']);
            if (strlen($from) === 10) {
                $from .= ' 00:00:00';
            }
            $where[] = "c.created_at >= ?";
            $params[] = $from;
        }

        if (!empty($filters['date_to'])) {
            $to = trim((string)$filters['date_to']);
            if (strlen($to) === 10) {
                $to .= ' 23:59:59';
            }
            $where[] = "c.created_at <= ?";
            $params[] = $to;
        }

        $whereSql = implode(' AND ', $where);

        // 1. Total records (unfiltered by search/filters, respecting only data scoping)
        $unfilteredSql = "SELECT COUNT(*) FROM `{$this->table}` c WHERE c.deleted_at IS NULL";
        $unfilteredParams = [];
        if (!$viewAll) {
            $unfilteredSql .= " AND c.assigned_to = ?";
            $unfilteredParams[] = $userId;
        }
        $unfilteredStmt = $pdo->prepare($unfilteredSql);
        $unfilteredStmt->execute($unfilteredParams);
        $recordsTotal = (int)$unfilteredStmt->fetchColumn();

        // 2. Filtered count
        $countSql = "SELECT COUNT(*) FROM `{$this->table}` c WHERE {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $recordsFiltered = (int)$countStmt->fetchColumn();

        // 3. Sorting & Pagination
        $allowedSort = [
            'id' => 'c.id',
            'client_code' => 'c.client_code',
            'name' => 'c.name',
            'email' => 'c.email',
            'mobile' => 'c.mobile',
            'city' => 'c.city',
            'state' => 'c.state',
            'status' => 'c.status',
            'created_at' => 'c.created_at',
        ];
        $sortColumn = $allowedSort[strtolower($sortBy)] ?? 'c.id';
        $sortDirection = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int)ceil($recordsFiltered / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $dataSql = "SELECT c.*, u.name AS assigned_to_name, u.email AS assigned_to_email
                    FROM `{$this->table}` c
                    LEFT JOIN `users` u ON c.assigned_to = u.id
                    WHERE {$whereSql}
                    ORDER BY {$sortColumn} {$sortDirection}
                    LIMIT {$perPage} OFFSET {$offset}";

        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll();

        foreach ($items as &$item) {
            if (isset($item['pan_no']) && $item['pan_no'] !== null && $item['pan_no'] !== '') {
                $item['pan_no'] = Crypto::maskPan((string)$item['pan_no']);
            }
        }
        unset($item);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $recordsFiltered,
                'total_pages' => $totalPages,
            ],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
        ];
    }

    public function getClientsForExport(array $filters, int $userId, bool $viewAll): array
    {
        $pdo = $this->getPdo();
        $where = ["c.deleted_at IS NULL"];
        $params = [];

        // Data scoping
        if (!$viewAll) {
            $where[] = "c.assigned_to = ?";
            $params[] = $userId;
        } elseif (!empty($filters['assigned_to'])) {
            $where[] = "c.assigned_to = ?";
            $params[] = (int)$filters['assigned_to'];
        }

        // Search term
        $searchTerm = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($searchTerm !== '') {
            $like = '%' . $searchTerm . '%';
            $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.mobile LIKE ? OR c.client_code LIKE ? OR c.gst_no LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Filters
        if (!empty($filters['status']) && in_array($filters['status'], ['new', 'active', 'inactive'], true)) {
            $where[] = "c.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['city'])) {
            $where[] = "c.city LIKE ?";
            $params[] = '%' . trim((string)$filters['city']) . '%';
        }

        if (!empty($filters['state'])) {
            $where[] = "c.state = ?";
            $params[] = trim((string)$filters['state']);
        }

        if (!empty($filters['lead_source'])) {
            $where[] = "c.lead_source = ?";
            $params[] = trim((string)$filters['lead_source']);
        }

        if (!empty($filters['date_from'])) {
            $from = trim((string)$filters['date_from']);
            if (strlen($from) === 10) {
                $from .= ' 00:00:00';
            }
            $where[] = "c.created_at >= ?";
            $params[] = $from;
        }

        if (!empty($filters['date_to'])) {
            $to = trim((string)$filters['date_to']);
            if (strlen($to) === 10) {
                $to .= ' 23:59:59';
            }
            $where[] = "c.created_at <= ?";
            $params[] = $to;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT c.*, u.name AS assigned_to_name
                FROM `{$this->table}` c
                LEFT JOIN `users` u ON c.assigned_to = u.id
                WHERE {$whereSql}
                ORDER BY c.id DESC
                LIMIT 5000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function existsByEmail(string $email, ?int $ignoreId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE `email` = ?";
        $params = [trim(strtolower($email))];
        if ($ignoreId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $ignoreId;
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function existsByMobile(string $mobile, ?int $ignoreId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE `mobile` = ?";
        $params = [preg_replace('/\D/', '', $mobile)];
        if ($ignoreId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $ignoreId;
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function existsByGst(string $gst, ?int $ignoreId = null): bool
    {
        $cleaned = trim(strtoupper($gst));
        if ($cleaned === '') {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE `gst_no` = ?";
        $params = [$cleaned];
        if ($ignoreId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $ignoreId;
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function generateClientCode(int $year): string
    {
        $pdo = $this->getPdo();
        $prefix = sprintf('CL-%d-', $year);

        // In MySQL, lock the rows for update; in SQLite (tests) execute standard select
        $sql = "SELECT `client_code` FROM `{$this->table}` WHERE `client_code` LIKE ? ORDER BY `id` DESC LIMIT 1";
        try {
            $stmt = $pdo->prepare($sql . " FOR UPDATE");
            $stmt->execute([$prefix . '%']);
        } catch (Throwable) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$prefix . '%']);
        }

        $lastCode = $stmt->fetchColumn();

        if (!$lastCode || !is_string($lastCode)) {
            return sprintf('CL-%d-0001', $year);
        }

        $parts = explode('-', $lastCode);
        $seq = isset($parts[2]) ? (int)$parts[2] + 1 : 1;

        return sprintf('CL-%d-%04d', $year, $seq);
    }

    /**
     * Dashboard: Total clients and new clients this month in a single query.
     *
     * @return array{total: int, new_this_month: int}
     */
    public function getClientTotals(int $userId, bool $viewAll, string $monthStart): array
    {
        $sql = "SELECT COUNT(*) AS total, 
                       SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS new_this_month
                FROM `{$this->table}`
                WHERE deleted_at IS NULL";
        $params = [$monthStart];

        if (!$viewAll) {
            $sql .= " AND assigned_to = ?";
            $params[] = $userId;
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total' => (int)($row['total'] ?? 0),
            'new_this_month' => (int)($row['new_this_month'] ?? 0),
        ];
    }

    /**
     * Dashboard: Group client counts by status.
     *
     * @return array<string, int>
     */
    public function getCountsByStatus(int $userId, bool $viewAll): array
    {
        $sql = "SELECT status, COUNT(*) AS count
                FROM `{$this->table}`
                WHERE deleted_at IS NULL";
        $params = [];

        if (!$viewAll) {
            $sql .= " AND assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY status";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $result = ['new' => 0, 'active' => 0, 'inactive' => 0];
        foreach ($rows as $r) {
            $st = strtolower((string)$r['status']);
            $result[$st] = (int)$r['count'];
        }

        return $result;
    }

    /**
     * Dashboard: Group client counts by lead source.
     *
     * @return array<string, int>
     */
    public function getCountsByLeadSource(int $userId, bool $viewAll): array
    {
        $sql = "SELECT COALESCE(NULLIF(lead_source, ''), 'Direct') AS source, COUNT(*) AS count
                FROM `{$this->table}`
                WHERE deleted_at IS NULL";
        $params = [];

        if (!$viewAll) {
            $sql .= " AND assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY source ORDER BY count DESC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $r) {
            $result[(string)$r['source']] = (int)$r['count'];
        }

        return $result;
    }

    /**
     * Dashboard: Monthly new clients trend over a date range.
     *
     * @return array<string, int>
     */
    public function getMonthlyNewClients(int $userId, bool $viewAll, string $startDate): array
    {
        $sql = "SELECT SUBSTR(created_at, 1, 7) AS ym, COUNT(*) AS count
                FROM `{$this->table}`
                WHERE deleted_at IS NULL AND created_at >= ?";
        $params = [$startDate];

        if (!$viewAll) {
            $sql .= " AND assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY ym ORDER BY ym ASC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $r) {
            $result[(string)$r['ym']] = (int)$r['count'];
        }

        return $result;
    }

    /**
     * Dashboard: Recent clients.
     */
    public function getRecentClients(int $userId, bool $viewAll, int $limit = 5): array
    {
        $sql = "SELECT c.id, c.client_code, c.name, c.email, c.mobile, c.status, c.created_at,
                       u.name AS assigned_to_name
                FROM `{$this->table}` c
                LEFT JOIN `users` u ON c.assigned_to = u.id
                WHERE c.deleted_at IS NULL";
        $params = [];

        if (!$viewAll) {
            $sql .= " AND c.assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY c.id DESC LIMIT {$limit}";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Dashboard: Top staff by clients assigned/added this month (Admin/Manager only).
     */
    public function getTopStaffThisMonth(string $monthStart, int $limit = 5): array
    {
        $sql = "SELECT u.id, u.name, u.email, COUNT(c.id) AS client_count
                FROM `users` u
                JOIN `{$this->table}` c ON c.assigned_to = u.id
                WHERE c.deleted_at IS NULL 
                  AND c.created_at >= ?
                  AND u.deleted_at IS NULL
                  AND u.is_active = 1
                GROUP BY u.id, u.name, u.email
                ORDER BY client_count DESC, u.name ASC
                LIMIT {$limit}";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$monthStart]);
        return $stmt->fetchAll();
    }

    /**
     * Find client by mobile number.
     */
    public function findByMobile(string $mobile): ?array
    {
        $clean = preg_replace('/[^0-9]/', '', $mobile);
        $stmt = $this->getPdo()->prepare("SELECT * FROM `{$this->table}` WHERE `mobile` = ? AND `deleted_at` IS NULL LIMIT 1");
        $stmt->execute([$clean]);
        $row = $stmt->fetch();
        if ($row !== false && isset($row['pan_no'])) {
            $row['pan_no'] = Crypto::decryptPan($row['pan_no']);
        }
        return $row !== false ? $row : null;
    }
}
