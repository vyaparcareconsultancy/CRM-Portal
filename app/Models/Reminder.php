<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Reminder extends BaseModel
{
    protected string $table = 'reminders';
    protected bool $softDelete = true;

    /**
     * Get reminders by tab with entity details and role scoping.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByTab(string $tab, ?int $userId = null, ?string $userRole = null): array
    {
        $sql = "
            SELECT rem.*,
                   rule.name AS rule_name, rule.category AS rule_category,
                   u_assigned.name AS assigned_user_name,
                   u_done.name AS done_by_name,
                   CASE 
                       WHEN rem.entity_type = 'client' THEN c.name
                       WHEN rem.entity_type = 'student' THEN s.name
                       ELSE 'Unknown'
                   END AS entity_name,
                   CASE 
                       WHEN rem.entity_type = 'client' THEN c.email
                       WHEN rem.entity_type = 'student' THEN s.email
                       ELSE NULL
                   END AS entity_email,
                   CASE 
                       WHEN rem.entity_type = 'client' THEN c.mobile
                       WHEN rem.entity_type = 'student' THEN s.mobile
                       ELSE NULL
                   END AS entity_mobile,
                   CASE 
                       WHEN rem.entity_type = 'client' THEN c.client_code
                       WHEN rem.entity_type = 'student' THEN s.student_code
                       ELSE NULL
                   END AS entity_code,
                   inv.invoice_no, inv.balance_amount AS invoice_due_amount
            FROM `{$this->table}` rem
            LEFT JOIN `reminder_rules` rule ON rem.rule_id = rule.id
            LEFT JOIN `users` u_assigned ON rem.assigned_user_id = u_assigned.id
            LEFT JOIN `users` u_done ON rem.done_by = u_done.id
            LEFT JOIN `clients` c ON rem.entity_type = 'client' AND rem.entity_id = c.id
            LEFT JOIN `students` s ON rem.entity_type = 'student' AND rem.entity_id = s.id
            LEFT JOIN `invoices` inv ON rem.invoice_id = inv.id
            WHERE rem.deleted_at IS NULL
        ";

        $params = [];

        // Tab conditions
        $today = date('Y-m-d');
        switch ($tab) {
            case 'today':
                $sql .= " AND rem.status != 'done' AND (rem.due_date = ? OR (rem.remind_date <= ? AND rem.due_date >= ?))";
                $params[] = $today;
                $params[] = $today;
                $params[] = $today;
                break;
            case 'upcoming':
                $sql .= " AND rem.status != 'done' AND rem.due_date > ?";
                $params[] = $today;
                break;
            case 'overdue':
                $sql .= " AND rem.status != 'done' AND rem.due_date < ?";
                $params[] = $today;
                break;
            case 'done':
                $sql .= " AND rem.status = 'done'";
                break;
            default: // all active
                $sql .= " AND rem.status != 'done'";
                break;
        }

        // Role scoping: Counselors & Trainers see only assigned or their clients/students
        if ($userId !== null && !in_array($userRole, ['admin', 'manager', 'accountant'], true)) {
            $sql .= " AND (rem.assigned_user_id = ? OR c.assigned_to = ? OR s.created_by = ?)";
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($tab === 'done') {
            $sql .= " ORDER BY rem.done_at DESC, rem.id DESC";
        } else {
            $sql .= " ORDER BY rem.due_date ASC, rem.id ASC";
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get counts for all tabs.
     *
     * @return array<string, int>
     */
    public function getCounts(?int $userId = null, ?string $userRole = null): array
    {
        $today = date('Y-m-d');
        $counts = [
            'today' => 0,
            'upcoming' => 0,
            'overdue' => 0,
            'done' => 0,
        ];

        foreach (array_keys($counts) as $tab) {
            $items = $this->getByTab($tab, $userId, $userRole);
            $counts[$tab] = count($items);
        }

        return $counts;
    }

    /**
     * Mark a reminder as completed with notes.
     */
    public function markDone(int $id, int $userId, ?string $note = null): bool
    {
        $stmt = $this->getPdo()->prepare("
            UPDATE `{$this->table}`
            SET `status` = 'done',
                `done_at` = NOW(),
                `done_by` = ?,
                `done_note` = ?
            WHERE `id` = ? AND `deleted_at` IS NULL
        ");

        return $stmt->execute([$userId, $note, $id]);
    }

    /**
     * Find pending reminders due for dispatch on or before a given target date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPendingForDispatch(string $targetDate): array
    {
        $sql = "
            SELECT rem.*,
                   rule.name AS rule_name, rule.channels AS rule_channels,
                   c.name AS client_name, c.email AS client_email, c.mobile AS client_mobile,
                   s.name AS student_name, s.email AS student_email, s.mobile AS student_mobile
            FROM `{$this->table}` rem
            LEFT JOIN `reminder_rules` rule ON rem.rule_id = rule.id
            LEFT JOIN `clients` c ON rem.entity_type = 'client' AND rem.entity_id = c.id
            LEFT JOIN `students` s ON rem.entity_type = 'student' AND rem.entity_id = s.id
            WHERE rem.status = 'pending'
              AND rem.remind_date <= ?
              AND rem.deleted_at IS NULL
            ORDER BY rem.due_date ASC
        ";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$targetDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
