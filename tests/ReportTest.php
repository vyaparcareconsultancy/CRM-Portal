<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Services\Cache\Cache;
use App\Services\DashboardService;
use App\Services\ReportService;
use PDO;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    private PDO $pdo;
    private DashboardService $dashboardService;
    private ReportService $reportService;

    private int $adminId;
    private int $accountantId;
    private int $counselorId;
    private int $trainerId;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();

        $this->pdo = DatabaseResetter::reset(true) ?? Database::getConnection();
        $this->dashboardService = new DashboardService(null, null, $this->pdo);
        $this->reportService = new ReportService($this->pdo);

        // 1. Roles & Users setup
        $rolesStmt = $this->pdo->query("SELECT id, name FROM roles");
        $rolesMap = [];
        while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
            $rolesMap[$r['name']] = (int)$r['id'];
        }

        $this->adminId = $this->getOrCreateUser('admin_rep@crm.local', 'Admin User', $rolesMap['admin']);
        $this->accountantId = $this->getOrCreateUser('acc_rep@crm.local', 'Accountant User', $rolesMap['accountant']);
        $this->counselorId = $this->getOrCreateUser('coun_rep@crm.local', 'Counselor User', $rolesMap['counselor']);
        $this->trainerId = $this->getOrCreateUser('train_rep@crm.local', 'Trainer User', $rolesMap['trainer']);

        // 2. Sample Data
        $suffix = bin2hex(random_bytes(3));

        // Lead
        $this->pdo->prepare("INSERT INTO leads (lead_code, name, mobile, email, interested_in, status, assigned_to) VALUES (?, 'Lead Test', '9811122233', 'lead@test.com', 'GST', 'converted', ?)")
            ->execute(["LD-REP-{$suffix}", $this->counselorId]);

        // Client & Invoice & Payment
        $this->pdo->prepare("INSERT INTO clients (client_code, name, email, mobile, client_type, status, assigned_to) VALUES (?, 'Client Rep', 'cl@test.com', '9844455566', 'company', 'active', ?)")
            ->execute(["CL-REP-{$suffix}", $this->accountantId]);
        $clientId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO invoices (invoice_no, client_id, title, total_amount, paid_amount, balance_amount, issue_date, due_date, status) VALUES (?, ?, 'Audit Invoice', 10000.00, 4000.00, 6000.00, '2026-09-01', '2026-09-15', 'partially_paid')")
            ->execute(["INV-REP-{$suffix}", $clientId]);
        $invId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO payments (invoice_id, receipt_no, amount, payment_date, payment_mode, received_by) VALUES (?, ?, 4000.00, CURDATE(), 'bank_transfer', ?)")
            ->execute([$invId, "REC-REP-{$suffix}", $this->accountantId]);
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

    public function testDashboardStatsAndRoleScoping(): void
    {
        $adminStats = $this->dashboardService->computeStats($this->adminId, 'admin', true);
        $this->assertTrue($adminStats['can_view_financial']);
        $this->assertGreaterThanOrEqual(1, $adminStats['metrics']['total_leads']);
        $this->assertGreaterThanOrEqual(4000.00, (float)$adminStats['metrics']['revenue_this_month']);
        $this->assertGreaterThanOrEqual(6000.00, (float)$adminStats['metrics']['total_due']);

        // Check required charts
        $this->assertArrayHasKey('leads_by_source', $adminStats['charts']);
        $this->assertArrayHasKey('conversion_funnel', $adminStats['charts']);
        $this->assertArrayHasKey('monthly_revenue', $adminStats['charts']);

        // Trainer scoping
        $trainerStats = $this->dashboardService->computeStats($this->trainerId, 'trainer', false);
        $this->assertFalse($trainerStats['can_view_financial']);
        $this->assertNull($trainerStats['metrics']['revenue_this_month']);
        $this->assertNull($trainerStats['metrics']['total_due']);
    }

    public function testReportsGenerationAndCaching(): void
    {
        // 1. Sales & Collections
        $r1 = $this->reportService->getReport('sales_collections');
        $this->assertNotEmpty($r1['rows']);
        $this->assertGreaterThanOrEqual(4000.00, (float)$r1['summary']['total_collected']);

        // 2. Lead Source & Conversion %
        $r2 = $this->reportService->getReport('lead_source_conversion');
        $this->assertIsArray($r2['rows']);
        $this->assertArrayHasKey('overall_conversion_pct', $r2['summary']);

        // 3. Course Admissions
        $r3 = $this->reportService->getReport('course_admissions');
        $this->assertIsArray($r3['rows']);

        // 4. Client Service Revenue
        $r4 = $this->reportService->getReport('client_service_revenue');
        $this->assertIsArray($r4['rows']);

        // 5. Payment Aging
        $r5 = $this->reportService->getReport('payment_aging');
        $this->assertNotEmpty($r5['rows']);
        $this->assertArrayHasKey('total_pending', $r5['summary']);

        // 6. Staff Performance
        $r6 = $this->reportService->getReport('staff_performance');
        $this->assertNotEmpty($r6['rows']);

        // 7. Attendance Summary
        $r7 = $this->reportService->getReport('attendance_summary');
        $this->assertIsArray($r7['rows']);

        // Cache verification
        $cacheKey = "report:sales_collections:" . md5(json_encode([]));
        $this->assertTrue(Cache::has($cacheKey));
    }
}
