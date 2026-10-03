<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Notification;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\User;
use InvalidArgumentException;
use PDO;

class ReminderService
{
    private ReminderRule $ruleModel;
    private Reminder $reminderModel;
    private Notification $notificationModel;
    private User $userModel;
    private PDO $pdo;

    public function __construct(
        ?ReminderRule $ruleModel = null,
        ?Reminder $reminderModel = null,
        ?Notification $notificationModel = null,
        ?User $userModel = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->ruleModel = $ruleModel ?? new ReminderRule($this->pdo);
        $this->reminderModel = $reminderModel ?? new Reminder($this->pdo);
        $this->notificationModel = $notificationModel ?? new Notification($this->pdo);
        $this->userModel = $userModel ?? new User($this->pdo);
    }

    /**
     * Get all active and managed reminder rules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRules(): array
    {
        return $this->ruleModel->getAllRules();
    }

    /**
     * Create a new reminder rule (Admin managed, zero hardcoded statutory dates).
     *
     * @param array<string, mixed> $data
     * @return int
     */
    public function createRule(array $data, int $userId): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Reminder rule name is required.');
        }

        $appliesTo = (string)($data['applies_to'] ?? 'client_service');
        if (!in_array($appliesTo, ['client_service', 'student_fee', 'all_clients'], true)) {
            throw new InvalidArgumentException('Invalid applies_to selection.');
        }

        $category = (string)($data['category'] ?? 'statutory_compliance');
        $dueDay = !empty($data['due_day']) ? (int)$data['due_day'] : null;
        if ($dueDay !== null && ($dueDay < 1 || $dueDay > 31)) {
            throw new InvalidArgumentException('Due day must be between 1 and 31.');
        }

        $dueDate = !empty($data['due_date']) ? (string)$data['due_date'] : null;
        if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new InvalidArgumentException('Due date must be in YYYY-MM-DD format.');
        }

        $remindDays = isset($data['remind_days_before']) ? max(0, (int)$data['remind_days_before']) : 3;

        $channels = $data['channels'] ?? ['in_app', 'email'];
        if (is_string($channels)) {
            $channels = json_decode($channels, true) ?: ['in_app', 'email'];
        }
        if (!is_array($channels) || empty($channels)) {
            $channels = ['in_app'];
        }

        $ruleCode = !empty($data['rule_code']) ? strtoupper(trim((string)$data['rule_code'])) : 'RULE_' . strtoupper(substr(md5($name . microtime()), 0, 8));

        return (int)$this->ruleModel->create([
            'name' => $name,
            'rule_code' => $ruleCode,
            'category' => $category,
            'applies_to' => $appliesTo,
            'service_id' => !empty($data['service_id']) ? (int)$data['service_id'] : null,
            'due_day' => $dueDay,
            'due_date' => $dueDate,
            'remind_days_before' => $remindDays,
            'channels' => json_encode(array_values(array_unique($channels))),
            'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            'description' => !empty($data['description']) ? trim((string)$data['description']) : null,
            'created_by' => $userId,
        ]);
    }

    /**
     * Update an existing reminder rule.
     *
     * @param array<string, mixed> $data
     */
    public function updateRule(int $id, array $data): bool
    {
        $rule = $this->ruleModel->find($id);
        if (!$rule) {
            throw new InvalidArgumentException('Reminder rule not found.');
        }

        $update = [];
        if (isset($data['name'])) {
            $name = trim((string)$data['name']);
            if ($name === '') {
                throw new InvalidArgumentException('Reminder rule name cannot be empty.');
            }
            $update['name'] = $name;
        }

        if (isset($data['applies_to'])) {
            $appliesTo = (string)$data['applies_to'];
            if (!in_array($appliesTo, ['client_service', 'student_fee', 'all_clients'], true)) {
                throw new InvalidArgumentException('Invalid applies_to selection.');
            }
            $update['applies_to'] = $appliesTo;
        }

        if (array_key_exists('service_id', $data)) {
            $update['service_id'] = !empty($data['service_id']) ? (int)$data['service_id'] : null;
        }

        if (array_key_exists('due_day', $data)) {
            $dueDay = !empty($data['due_day']) ? (int)$data['due_day'] : null;
            if ($dueDay !== null && ($dueDay < 1 || $dueDay > 31)) {
                throw new InvalidArgumentException('Due day must be between 1 and 31.');
            }
            $update['due_day'] = $dueDay;
        }

        if (array_key_exists('due_date', $data)) {
            $dueDate = !empty($data['due_date']) ? (string)$data['due_date'] : null;
            if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                throw new InvalidArgumentException('Due date must be in YYYY-MM-DD format.');
            }
            $update['due_date'] = $dueDate;
        }

        if (isset($data['remind_days_before'])) {
            $update['remind_days_before'] = max(0, (int)$data['remind_days_before']);
        }

        if (isset($data['channels'])) {
            $channels = is_array($data['channels']) ? $data['channels'] : (json_decode((string)$data['channels'], true) ?: ['in_app']);
            $update['channels'] = json_encode(array_values(array_unique($channels)));
        }

        if (isset($data['is_active'])) {
            $update['is_active'] = (int)(bool)$data['is_active'];
        }

        if (array_key_exists('description', $data)) {
            $update['description'] = !empty($data['description']) ? trim((string)$data['description']) : null;
        }

        if (empty($update)) {
            return true;
        }

        return $this->ruleModel->update($id, $update);
    }

    /**
     * Toggle rule active status.
     */
    public function toggleRule(int $id, bool $isActive): bool
    {
        return $this->ruleModel->update($id, ['is_active' => $isActive ? 1 : 0]);
    }

    /**
     * Delete a reminder rule (soft delete).
     */
    public function deleteRule(int $id): bool
    {
        return $this->ruleModel->delete($id);
    }

    /**
     * Generate reminder instances per client/student per period via cron.
     * Prevents duplicates via unique constraint / deduplication check.
     *
     * @return array{generated: int, rules_processed: int}
     */
    public function generateReminders(?string $targetDate = null): array
    {
        $today = $targetDate ?? date('Y-m-d');
        $activeRules = $this->ruleModel->getActiveRules();
        $generatedCount = 0;

        foreach ($activeRules as $rule) {
            $ruleId = (int)$rule['id'];
            $appliesTo = (string)$rule['applies_to'];
            $remindDays = (int)($rule['remind_days_before'] ?? 3);
            $serviceId = !empty($rule['service_id']) ? (int)$rule['service_id'] : null;

            if ($appliesTo === 'client_service') {
                $generatedCount += $this->generateClientServiceReminders($rule, $today, $remindDays, $serviceId);
            } elseif ($appliesTo === 'student_fee') {
                $generatedCount += $this->generateStudentFeeReminders($rule, $today, $remindDays);
            } elseif ($appliesTo === 'all_clients') {
                $generatedCount += $this->generateAllClientsReminders($rule, $today, $remindDays);
            }
        }

        return [
            'generated' => $generatedCount,
            'rules_processed' => count($activeRules),
        ];
    }

    /**
     * Generate reminders for client services (e.g. GSTR-1, GSTR-3B, ITR, TDS, PF/ESI).
     */
    private function generateClientServiceReminders(array $rule, string $today, int $remindDays, ?int $serviceId): int
    {
        $ruleId = (int)$rule['id'];
        $dueDay = !empty($rule['due_day']) ? (int)$rule['due_day'] : null;
        $fixedDueDate = !empty($rule['due_date']) ? (string)$rule['due_date'] : null;

        // Fetch active client services
        $sql = "
            SELECT cs.*, c.name AS client_name, c.email AS client_email, c.assigned_to AS client_assigned_to,
                   s.name AS service_name, s.code AS service_code
            FROM `client_services` cs
            JOIN `clients` c ON cs.client_id = c.id
            JOIN `services` s ON cs.service_id = s.id
            WHERE cs.status = 'active'
              AND cs.deleted_at IS NULL
              AND c.deleted_at IS NULL
        ";
        $params = [];
        if ($serviceId !== null) {
            $sql .= " AND cs.service_id = ?";
            $params[] = $serviceId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $clientServices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $created = 0;
        $currentYearMonth = date('Y-m', strtotime($today));
        $currentYear = (int)date('Y', strtotime($today));
        $currentMonth = (int)date('m', strtotime($today));

        foreach ($clientServices as $cs) {
            $clientId = (int)$cs['client_id'];
            $csId = (int)$cs['id'];
            $assignedStaff = !empty($cs['assigned_accountant_id'])
                ? (int)$cs['assigned_accountant_id']
                : (!empty($cs['client_assigned_to']) ? (int)$cs['client_assigned_to'] : null);

            $dueDate = null;
            $period = null;

            if ($fixedDueDate !== null) {
                // Fixed annual or specific date (e.g. ITR 2026-07-31, ROC 2026-09-30)
                $dueDate = $fixedDueDate;
                $period = date('Y', strtotime($fixedDueDate)) . '-' . substr($fixedDueDate, 5, 2);
            } elseif ($dueDay !== null) {
                // Recurring monthly/quarterly due day (e.g. 11th for GSTR-1, 20th for GSTR-3B)
                // Determine appropriate target period (current month)
                $daysInMonth = (int)date('t', strtotime($today));
                $effectiveDay = min($dueDay, $daysInMonth);
                $dueDate = sprintf('%s-%02d', $currentYearMonth, $effectiveDay);
                $period = $currentYearMonth;
            } else {
                continue;
            }

            $remindDate = date('Y-m-d', strtotime("{$dueDate} -{$remindDays} days"));

            // Check if today is on or past remind_date and reminder not created yet
            if ($today >= $remindDate) {
                $title = "{$rule['name']} Due for {$cs['client_name']}";
                $desc = "{$rule['name']} for period {$period} is due on {$dueDate} for {$cs['client_name']} ({$cs['service_name']}).";

                if ($this->insertReminderInstance($ruleId, 'client', $clientId, $csId, null, $period, $title, $desc, $dueDate, $remindDate, $assignedStaff)) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Generate reminders for student tuition fees.
     */
    private function generateStudentFeeReminders(array $rule, string $today, int $remindDays): int
    {
        $ruleId = (int)$rule['id'];

        $sql = "
            SELECT inv.*, s.name AS student_name, s.email AS student_email, s.created_by AS counselor_id
            FROM `invoices` inv
            JOIN `students` s ON inv.student_id = s.id
            WHERE inv.status IN ('unpaid', 'partially_paid', 'overdue')
              AND inv.deleted_at IS NULL
              AND s.deleted_at IS NULL
        ";
        $stmt = $this->pdo->query($sql);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $created = 0;
        foreach ($invoices as $inv) {
            $studentId = (int)$inv['student_id'];
            $invoiceId = (int)$inv['id'];
            $dueDate = $inv['due_date'];
            $period = (string)$inv['invoice_no'];
            $remindDate = date('Y-m-d', strtotime("{$dueDate} -{$remindDays} days"));
            $assignedStaff = !empty($inv['counselor_id']) ? (int)$inv['counselor_id'] : null;

            if ($today >= $remindDate) {
                $amountDue = number_format((float)$inv['balance_amount'], 2);
                $title = "Course Fee Due: {$inv['student_name']} (INR {$amountDue})";
                $desc = "Fee installment for {$inv['student_name']} of INR {$amountDue} against invoice {$inv['invoice_no']} is due on {$dueDate}.";

                if ($this->insertReminderInstance($ruleId, 'student', $studentId, null, $invoiceId, $period, $title, $desc, $dueDate, $remindDate, $assignedStaff)) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Generate reminders for all clients (e.g. Birthday, Annual Service Renewal).
     */
    private function generateAllClientsReminders(array $rule, string $today, int $remindDays): int
    {
        $ruleId = (int)$rule['id'];
        $category = (string)($rule['category'] ?? '');
        $created = 0;
        $currentYear = date('Y', strtotime($today));

        if ($category === 'birthday' || ($rule['rule_code'] ?? '') === 'RULE_BIRTHDAY') {
            // Check client birthdays
            $sql = "
                SELECT id, name, email, mobile, date_of_birth, assigned_to
                FROM `clients`
                WHERE `date_of_birth` IS NOT NULL AND `deleted_at` IS NULL
            ";
            $stmt = $this->pdo->query($sql);
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($clients as $client) {
                $dob = $client['date_of_birth'];
                $monthDay = substr($dob, 5); // MM-DD
                $birthdayThisYear = "{$currentYear}-{$monthDay}";
                $remindDate = date('Y-m-d', strtotime("{$birthdayThisYear} -{$remindDays} days"));
                $period = "{$currentYear}-BIRTHDAY";

                if ($today >= $remindDate && $today <= $birthdayThisYear) {
                    $title = "Client Birthday: {$client['name']}";
                    $desc = "Today/Upcoming birthday for client {$client['name']} on {$birthdayThisYear}. Send best wishes!";
                    $assignedStaff = !empty($client['assigned_to']) ? (int)$client['assigned_to'] : null;

                    if ($this->insertReminderInstance($ruleId, 'client', (int)$client['id'], null, null, $period, $title, $desc, $birthdayThisYear, $remindDate, $assignedStaff)) {
                        $created++;
                    }
                }
            }
        } else {
            // Service Annual Renewal
            $sql = "
                SELECT cs.*, c.name AS client_name, c.email AS client_email, c.assigned_to AS client_assigned_to, s.name AS service_name
                FROM `client_services` cs
                JOIN `clients` c ON cs.client_id = c.id
                JOIN `services` s ON cs.service_id = s.id
                WHERE cs.status = 'active'
                  AND cs.frequency IN ('yearly', 'recurring')
                  AND cs.deleted_at IS NULL
                  AND c.deleted_at IS NULL
            ";
            $stmt = $this->pdo->query($sql);
            $clientServices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($clientServices as $cs) {
                $startDate = $cs['start_date'];
                $monthDay = substr($startDate, 5);
                $anniversaryThisYear = "{$currentYear}-{$monthDay}";
                $remindDate = date('Y-m-d', strtotime("{$anniversaryThisYear} -{$remindDays} days"));
                $period = "{$currentYear}-RENEWAL";
                $assignedStaff = !empty($cs['assigned_accountant_id'])
                    ? (int)$cs['assigned_accountant_id']
                    : (!empty($cs['client_assigned_to']) ? (int)$cs['client_assigned_to'] : null);

                if ($today >= $remindDate && $today <= $anniversaryThisYear) {
                    $title = "Annual Service Renewal: {$cs['client_name']} ({$cs['service_name']})";
                    $desc = "Annual service subscription for {$cs['client_name']} ({$cs['service_name']}) is due for renewal on {$anniversaryThisYear}.";

                    if ($this->insertReminderInstance($ruleId, 'client', (int)$cs['client_id'], (int)$cs['id'], null, $period, $title, $desc, $anniversaryThisYear, $remindDate, $assignedStaff)) {
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    /**
     * Safely insert reminder instance with deduplication.
     */
    private function insertReminderInstance(
        int $ruleId,
        string $entityType,
        int $entityId,
        ?int $clientServiceId,
        ?int $invoiceId,
        string $period,
        string $title,
        string $description,
        string $dueDate,
        string $remindDate,
        ?int $assignedStaff
    ): bool {
        // Fallback assigned staff: default to first active Admin if none assigned
        if ($assignedStaff === null) {
            $assignedStaff = $this->getDefaultAdminUserId();
        }

        $sql = "
            INSERT IGNORE INTO `reminders`
            (`rule_id`, `entity_type`, `entity_id`, `client_service_id`, `invoice_id`, `period`, `title`, `description`, `due_date`, `remind_date`, `status`, `assigned_user_id`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $ruleId,
            $entityType,
            $entityId,
            $clientServiceId,
            $invoiceId,
            $period,
            $title,
            $description,
            $dueDate,
            $remindDate,
            $assignedStaff,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Dispatch in-app bell notifications and emails for pending reminders.
     *
     * @return array{dispatched: int, in_app: int, emails: int}
     */
    public function dispatchPendingReminders(?string $targetDate = null): array
    {
        $today = $targetDate ?? date('Y-m-d');
        $pending = $this->reminderModel->getPendingForDispatch($today);

        $dispatched = 0;
        $inAppCount = 0;
        $emailCount = 0;

        foreach ($pending as $rem) {
            $remId = (int)$rem['id'];
            $channels = !empty($rem['rule_channels'])
                ? (is_array($rem['rule_channels']) ? $rem['rule_channels'] : (json_decode((string)$rem['rule_channels'], true) ?: ['in_app']))
                : ['in_app', 'email'];

            $sentLog = [];
            $assignedUserId = !empty($rem['assigned_user_id']) ? (int)$rem['assigned_user_id'] : $this->getDefaultAdminUserId();

            // 1. In-App Bell Notification for assigned staff
            if (in_array('in_app', $channels, true) && $assignedUserId) {
                $this->notificationModel->createNotification(
                    $assignedUserId,
                    $rem['title'],
                    $rem['description'] ?? 'Reminder alert due on ' . $rem['due_date'],
                    '/reminders',
                    'reminder'
                );
                $sentLog['in_app'] = date('Y-m-d H:i:s');
                $inAppCount++;
            }

            // Phase 9 Omnichannel Message Layer dispatch (email, whatsapp, sms)
            $externalChannels = array_values(array_intersect($channels, ['email', 'whatsapp', 'sms']));
            if (!empty($externalChannels)) {
                try {
                    $msgService = new \App\Services\Messaging\MessageService();
                    $msgResults = $msgService->onReminderTriggered($remId, $externalChannels);
                    foreach ($msgResults as $ch => $res) {
                        if (!empty($res['success'])) {
                            $sentLog[$ch] = date('Y-m-d H:i:s');
                            if ($ch === 'email') $emailCount++;
                        }
                    }
                } catch (\Throwable $e) {
                    \App\Core\Logger::error("Failed to dispatch omnichannel reminder for #{$remId}: " . $e->getMessage());
                }
            }

            // Update reminder status
            $stmt = $this->pdo->prepare("
                UPDATE `reminders`
                SET `status` = 'notified',
                    `notified_at` = NOW(),
                    `channels_sent` = ?
                WHERE `id` = ?
            ");
            $stmt->execute([
                json_encode($sentLog),
                $remId,
            ]);

            $dispatched++;
        }

        return [
            'dispatched' => $dispatched,
            'in_app' => $inAppCount,
            'emails' => $emailCount,
        ];
    }

    /**
     * Get reminders by tab (Today, Upcoming, Overdue, Done).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReminders(string $tab = 'today', ?int $userId = null, ?string $userRole = null): array
    {
        return $this->reminderModel->getByTab($tab, $userId, $userRole);
    }

    /**
     * Get reminder tab counts.
     *
     * @return array<string, int>
     */
    public function getCounts(?int $userId = null, ?string $userRole = null): array
    {
        return $this->reminderModel->getCounts($userId, $userRole);
    }

    /**
     * Mark a reminder as done with a note.
     */
    public function markDone(int $id, int $userId, ?string $note = null): bool
    {
        $reminder = $this->reminderModel->find($id);
        if (!$reminder) {
            throw new InvalidArgumentException('Reminder not found.');
        }

        return $this->reminderModel->markDone($id, $userId, $note);
    }

    /**
     * Helper to get default admin user ID for fallback notifications.
     */
    private function getDefaultAdminUserId(): ?int
    {
        $stmt = $this->pdo->query("
            SELECT u.id
            FROM `users` u
            JOIN `roles` r ON u.role_id = r.id
            WHERE r.name = 'admin' AND u.is_active = 1
            LIMIT 1
        ");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? (int)$res['id'] : null;
    }
}
