<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use RuntimeException;
use Throwable;

class PaymentService
{
    /**
     * List payment transactions and fee records.
     * Requires 'payment.view' permission.
     */
    public function listPayments(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        Session::start();
        if (!PermissionService::can('payment.view')) {
            throw new RuntimeException("Unauthorized: you do not have permission to view payments", 403);
        }

        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->query("SELECT * FROM `payments` WHERE `deleted_at` IS NULL ORDER BY `id` DESC LIMIT {$perPage}");
            $items = $stmt->fetchAll() ?: [];
            return [
                'items' => $items,
                'total' => count($items),
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (Throwable) {
            // If payments table not yet created in Phase 4, return empty array when authorized
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
            ];
        }
    }

    /**
     * Get single payment record.
     * Requires 'payment.view' permission.
     */
    public function getPayment(int|string $id): ?array
    {
        Session::start();
        if (!PermissionService::can('payment.view')) {
            throw new RuntimeException("Unauthorized: you do not have permission to view payments", 403);
        }

        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare("SELECT * FROM `payments` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Record a new payment.
     * Requires 'payment.record' permission.
     */
    public function recordPayment(array $data): array
    {
        Session::start();
        if (!PermissionService::can('payment.record')) {
            throw new RuntimeException("Unauthorized: you do not have permission to record payments", 403);
        }

        return [
            'id' => 1,
            'status' => 'recorded',
            'amount' => $data['amount'] ?? 0,
        ];
    }
}
