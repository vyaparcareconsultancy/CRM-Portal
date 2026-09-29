<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    protected bool $softDelete = true;
    protected string $deletedAtColumn = 'deleted_at';
    protected string $createdAtColumn = 'created_at';
    protected string $updatedAtColumn = 'updated_at';

    protected ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();

        if ($this->table === '') {
            $classBase = basename(str_replace('\\', '/', static::class));
            // e.g. Client -> clients
            $this->table = strtolower($classBase) . 's';
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo ?? Database::getConnection();
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function find(int|string $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        if ($this->softDelete) {
            $sql .= " AND `{$this->deletedAtColumn}` IS NULL";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function where(
        array $conditions,
        string $boolean = 'AND',
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            if (is_numeric($column) && is_array($value) && count($value) === 3) {
                // e.g. ['status', '=', 'active']
                [$col, $op, $val] = $value;
                $whereClauses[] = "`{$col}` {$op} ?";
                $params[] = $val;
            } elseif ($value === null) {
                $whereClauses[] = "`{$column}` IS NULL";
            } else {
                $whereClauses[] = "`{$column}` = ?";
                $params[] = $value;
            }
        }

        if ($this->softDelete) {
            $whereClauses[] = "`{$this->deletedAtColumn}` IS NULL";
        }

        $sql = "SELECT * FROM `{$this->table}`";
        if (!empty($whereClauses)) {
            $op = strtoupper($boolean) === 'OR' ? ' OR ' : ' AND ';
            $sql .= " WHERE " . implode($op, $whereClauses);
        }

        if (!empty($orderBy)) {
            $orderParts = [];
            foreach ($orderBy as $col => $dir) {
                $direction = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                $orderParts[] = "`{$col}` {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function first(array $conditions, string $boolean = 'AND', array $orderBy = []): ?array
    {
        $rows = $this->where($conditions, $boolean, $orderBy, 1);
        return $rows[0] ?? null;
    }

    public function all(array $orderBy = [], ?int $limit = null): array
    {
        return $this->where([], 'AND', $orderBy, $limit);
    }

    public function count(array $conditions = []): int
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            if (is_numeric($column) && is_array($value) && count($value) === 3) {
                [$col, $op, $val] = $value;
                $whereClauses[] = "`{$col}` {$op} ?";
                $params[] = $val;
            } elseif ($value === null) {
                $whereClauses[] = "`{$column}` IS NULL";
            } else {
                $whereClauses[] = "`{$column}` = ?";
                $params[] = $value;
            }
        }

        if ($this->softDelete) {
            $whereClauses[] = "`{$this->deletedAtColumn}` IS NULL";
        }

        $sql = "SELECT COUNT(*) FROM `{$this->table}`";
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function insert(array $data): int|string
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!isset($data[$this->createdAtColumn])) {
                $data[$this->createdAtColumn] = $now;
            }
            if (!isset($data[$this->updatedAtColumn])) {
                $data[$this->updatedAtColumn] = $now;
            }
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $colList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $valList = implode(', ', $placeholders);

        $sql = "INSERT INTO `{$this->table}` ({$colList}) VALUES ({$valList})";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute(array_values($data));

        return $this->getPdo()->lastInsertId();
    }

    public function update(int|string $id, array $data): bool
    {
        if ($this->timestamps && !isset($data[$this->updatedAtColumn])) {
            $data[$this->updatedAtColumn] = date('Y-m-d H:i:s');
        }

        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setParts[] = "`{$column}` = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setParts) . " WHERE `{$this->primaryKey}` = ?";
        if ($this->softDelete) {
            $sql .= " AND `{$this->deletedAtColumn}` IS NULL";
        }

        $stmt = $this->getPdo()->prepare($sql);
        return $stmt->execute($params);
    }

    public function softDelete(int|string $id): bool
    {
        if ($this->softDelete) {
            $sql = "UPDATE `{$this->table}` SET `{$this->deletedAtColumn}` = ? WHERE `{$this->primaryKey}` = ?";
            $stmt = $this->getPdo()->prepare($sql);
            return $stmt->execute([date('Y-m-d H:i:s'), $id]);
        }

        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $stmt = $this->getPdo()->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function paginate(
        int $page = 1,
        int $perPage = 15,
        array $conditions = [],
        array $orderBy = ['id' => 'DESC']
    ): array {
        $perPage = max(1, $perPage);
        $total = $this->count($conditions);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        $items = $total > 0
            ? $this->where($conditions, 'AND', $orderBy, $perPage, $offset)
            : [];

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }
}
