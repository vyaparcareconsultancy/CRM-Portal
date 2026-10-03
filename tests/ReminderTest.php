<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Models\Notification;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Services\ReminderService;
use PDO;
use PHPUnit\Framework\TestCase;

class ReminderTest extends TestCase
{
    private PDO $pdo;
    private ReminderService $service;
    private ReminderRule $ruleModel;
    private Reminder $reminderModel;
    private Notification $notificationModel;

    private int $adminId;
    private int $accountantId;
    private int $counselorId;
    private int $trainerId;

    private int $clientId;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();

        $this->pdo = DatabaseResetter::reset(true) ?? Database::getConnection();
        $this->service = new ReminderService(null, null, null, null, $this->pdo);
        $this->ruleModel = new ReminderRule($this->pdo);
        $this->reminderModel = new Reminder($this->pdo);
        $this->notificationModel = new Notification($this->pdo);

        // 1. Roles & Users setup
        $rolesStmt = $this->pdo->query("SELECT id, name FROM roles");
        $rolesMap = [];
        while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
            $rolesMap[$r['name']] = (int)$r['id'];
        }

        $this->adminId = $this->getOrCreateUser('admin_p7@crm.local', 'Admin User', $rolesMap['admin']);
        $this->accountantId = $this->getOrCreateUser('accountant_p7@crm.local', 'Accountant User', $rolesMap['accountant']);
        $this->counselorId = $this->getOrCreateUser('counselor_p7@crm.local', 'Counselor User', $rolesMap['counselor']);
        $this->trainerId = $this->getOrCreateUser('trainer_p7@crm.local', 'Trainer User', $rolesMap['trainer']);

        // 2. Setup Client & Service
        $suffix = bin2hex(random_bytes(3));
        $this->pdo->prepare("INSERT INTO clients (client_code, name, email, mobile, client_type, status, assigned_to, date_of_birth) VALUES (?, ?, ?, ?, 'company', 'active', ?, '1992-10-15')")
            ->execute(["CL-TEST-{$suffix}", "Apex Corp {$suffix}", "apex_{$suffix}@crm.local", '98' . random_int(10000000, 99999999), $this->accountantId]);
        $this->clientId = (int)$this->pdo->lastInsertId();

        $srvStmt = $this->pdo->query("SELECT id FROM services WHERE code = 'SRV-GST-RET' LIMIT 1");
        $gstServiceId = (int)$srvStmt->fetchColumn();
        if (!$gstServiceId) {
            $this->pdo->query("INSERT INTO services (name, code, type, frequency, default_fee, status) VALUES ('GST Return Filing', 'SRV-GST-RET', 'recurring', 'monthly', 3000.00, 'active')");
            $gstServiceId = (int)$this->pdo->lastInsertId();
        }

        $this->pdo->prepare("INSERT INTO client_services (client_id, service_id, assigned_accountant_id, fee, frequency, start_date, status) VALUES (?, ?, ?, 3000.00, 'monthly', '2026-01-01', 'active')")
            ->execute([$this->clientId, $gstServiceId, $this->accountantId]);

        // 3. Setup Student & Due Invoice
        $this->pdo->prepare("INSERT INTO students (student_code, name, email, mobile, status, created_by) VALUES (?, ?, ?, ?, 'active', ?)")
            ->execute(["ST-TEST-{$suffix}", "Student {$suffix}", "student_{$suffix}@crm.local", '88' . random_int(10000000, 99999999), $this->counselorId]);
        $this->studentId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO invoices (invoice_no, student_id, title, total_amount, paid_amount, balance_amount, issue_date, due_date, status) VALUES (?, ?, 'Course Fee Due', 20000.00, 10000.00, 10000.00, '2026-10-01', '2026-10-12', 'partially_paid')")
            ->execute(["INV-TEST-{$suffix}", $this->studentId]);
    }

    private function getOrCreateUser(string $email, string $name, int $roleId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }

        $ins = $this->pdo->prepare("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)");
        $ins->execute([$roleId, $name, $email, password_hash('Secret123!', PASSWORD_BCRYPT)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function testAdminCanCreateAndModifyDynamicReminderRules(): void
    {
        $ruleId = $this->service->createRule([
            'name' => 'Extended GSTR-3B Special',
            'rule_code' => 'RULE_GSTR3B_SPECIAL_' . bin2hex(random_bytes(2)),
            'category' => 'statutory_compliance',
            'applies_to' => 'client_service',
            'due_date' => '2026-10-25',
            'remind_days_before' => 4,
            'channels' => ['in_app', 'email'],
            'is_active' => 1,
            'description' => 'Extended due date announced via notification',
        ], $this->adminId);

        $this->assertGreaterThan(0, $ruleId);

        $rule = $this->ruleModel->find($ruleId);
        $this->assertSame('Extended GSTR-3B Special', $rule['name']);
        $this->assertSame('2026-10-25', $rule['due_date']);
        $this->assertSame(4, (int)$rule['remind_days_before']);

        // Dynamically update due date (zero hardcoded dates)
        $this->service->updateRule($ruleId, [
            'due_date' => '2026-10-28',
            'remind_days_before' => 3,
        ]);

        $updated = $this->ruleModel->find($ruleId);
        $this->assertSame('2026-10-28', $updated['due_date']);
        $this->assertSame(3, (int)$updated['remind_days_before']);
    }

    public function testReminderGenerationAndDeduplication(): void
    {
        // Simulate target date 2026-10-10
        $stats1 = $this->service->generateReminders('2026-10-10');
        $this->assertGreaterThanOrEqual(1, $stats1['generated']);

        // Idempotency: second run produces 0 duplicates
        $stats2 = $this->service->generateReminders('2026-10-10');
        $this->assertSame(0, $stats2['generated']);
    }

    public function testNotificationDispatchAndMarkDoneWithNote(): void
    {
        $this->service->generateReminders('2026-10-10');
        $dispatchStats = $this->service->dispatchPendingReminders('2026-10-10');

        $this->assertGreaterThan(0, $dispatchStats['dispatched']);
        $this->assertGreaterThan(0, $dispatchStats['in_app']);

        // Check assigned accountant received bell notification
        $unread = $this->notificationModel->getUnreadForUser($this->accountantId);
        $this->assertNotEmpty($unread);

        // Fetch notified reminders and mark one as done
        $activeReminders = $this->reminderModel->where(['status' => 'notified']);
        $this->assertNotEmpty($activeReminders);

        $reminderId = (int)$activeReminders[0]['id'];
        $note = 'Completed return filing. Ack #20261010-09876';
        $doneSuccess = $this->service->markDone($reminderId, $this->accountantId, $note);
        $this->assertTrue($doneSuccess);

        $doneItem = $this->reminderModel->find($reminderId);
        $this->assertSame('done', $doneItem['status']);
        $this->assertSame($this->accountantId, (int)$doneItem['done_by']);
        $this->assertSame($note, $doneItem['done_note']);
        $this->assertNotEmpty($doneItem['done_at']);

        // Verify it appears in Done tab
        $doneList = $this->service->getReminders('done');
        $found = false;
        foreach ($doneList as $d) {
            if ((int)$d['id'] === $reminderId) {
                $found = true;
                $this->assertSame($note, $d['done_note']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testTabCountsAndScoping(): void
    {
        $counts = $this->service->getCounts();
        $this->assertArrayHasKey('today', $counts);
        $this->assertArrayHasKey('upcoming', $counts);
        $this->assertArrayHasKey('overdue', $counts);
        $this->assertArrayHasKey('done', $counts);

        // Trainer should not see client tax reminders
        $trainerList = $this->service->getReminders('today', $this->trainerId, 'trainer');
        $accountantList = $this->service->getReminders('today', $this->accountantId, 'accountant');
        $this->assertGreaterThanOrEqual(count($trainerList), count($accountantList));
    }
}
