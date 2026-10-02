<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Middleware\PermissionMiddleware;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PaymentService;
use App\Services\PermissionService;
use App\Services\RolePermissionService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RolePermissionTest extends TestCase
{
    private PDO $pdo;
    private RolePermissionService $rolePermService;
    private ClientService $clientService;
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = DatabaseResetter::reset(true) ?? Database::getConnection();

        $this->rolePermService = new RolePermissionService($this->pdo);
        $this->clientService = new ClientService();
        $this->paymentService = new PaymentService();
    }

    private function setUserSession(int $userId, string $roleName): void
    {
        Session::start();
        Session::set('user_id', $userId);
        Session::set('user_role', $roleName);

        // Fetch user's role_id
        $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $stmt->execute([$roleName]);
        $roleId = (int)$stmt->fetchColumn();
        Session::set('role_id', $roleId);

        PermissionService::refresh();
    }

    public function testStandardRolesExist(): void
    {
        $matrix = $this->rolePermService->getMatrix();
        $roleNames = array_column($matrix['roles'], 'name');

        $this->assertContains('admin', $roleNames);
        $this->assertContains('manager', $roleNames);
        $this->assertContains('counselor', $roleNames);
        $this->assertContains('accountant', $roleNames);
        $this->assertContains('trainer', $roleNames);
    }

    public function testSalesUsersMigratedToCounselor(): void
    {
        // Query to check if any active user still has the legacy sales role
        $stmt = $this->pdo->query("
            SELECT COUNT(*) 
            FROM `users` u 
            JOIN `roles` r ON u.`role_id` = r.`id` 
            WHERE r.`name` = 'sales'
        ");
        $salesCount = (int)$stmt->fetchColumn();
        $this->assertSame(0, $salesCount, 'All legacy sales users must be migrated to Counselor');
    }

    public function testAdminHasAllPermissions(): void
    {
        $this->setUserSession(1, 'admin');

        $this->assertTrue(PermissionService::can('user.manage'));
        $this->assertTrue(PermissionService::can('client.view_all'));
        $this->assertTrue(PermissionService::can('client.delete'));
        $this->assertTrue(PermissionService::can('payment.view'));
        $this->assertTrue(PermissionService::can('payment.record'));
        $this->assertTrue(PermissionService::can('lead.manage'));
        $this->assertTrue(PermissionService::can('attendance.manage'));
    }

    public function testManagerHasOperationalPermissionsExceptUserManagement(): void
    {
        $mgrStmt = $this->pdo->query("SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'manager' LIMIT 1) LIMIT 1");
        $mgrId = (int)$mgrStmt->fetchColumn();
        if (!$mgrId) {
            $rId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'manager' LIMIT 1")->fetchColumn();
            $this->pdo->exec("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES ({$rId}, 'Mgr Test', 'mgr@crm.local', 'hash', 1)");
            $mgrId = (int)$this->pdo->lastInsertId();
        }

        $this->setUserSession($mgrId, 'manager');

        $this->assertTrue(PermissionService::can('client.view_all'));
        $this->assertTrue(PermissionService::can('lead.manage'));
        $this->assertTrue(PermissionService::can('lead.convert'));
        $this->assertTrue(PermissionService::can('followup.manage'));

        // Prohibited: User management and system settings
        $this->assertFalse(PermissionService::can('user.manage'));
        $this->assertFalse(PermissionService::can('system.settings'));
    }

    public function testCounselorHasLeadsAndOwnClientsButNoPaymentsEdit(): void
    {
        $cStmt = $this->pdo->query("SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'counselor' LIMIT 1) LIMIT 1");
        $cId = (int)$cStmt->fetchColumn();
        if (!$cId) {
            $rId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'counselor' LIMIT 1")->fetchColumn();
            $this->pdo->exec("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES ({$rId}, 'Counselor Test', 'counselor@crm.local', 'hash', 1)");
            $cId = (int)$this->pdo->lastInsertId();
        }

        $this->setUserSession($cId, 'counselor');

        $this->assertTrue(PermissionService::can('lead.view'));
        $this->assertTrue(PermissionService::can('lead.manage'));
        $this->assertTrue(PermissionService::can('lead.convert'));
        $this->assertTrue(PermissionService::can('followup.manage'));
        $this->assertTrue(PermissionService::can('client.view_own'));

        // Prohibited: No payments edit
        $this->assertFalse(PermissionService::can('payment.record'));
        $this->assertFalse(PermissionService::can('payment.manage'));
        $this->assertFalse(PermissionService::can('invoice.manage'));
        $this->assertFalse(PermissionService::can('user.manage'));
    }

    public function testAccountantHasBillingClientsAndReportsButNoUserManagement(): void
    {
        $aStmt = $this->pdo->query("SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'accountant' LIMIT 1) LIMIT 1");
        $aId = (int)$aStmt->fetchColumn();
        if (!$aId) {
            $rId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'accountant' LIMIT 1")->fetchColumn();
            $this->pdo->exec("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES ({$rId}, 'Accountant Test', 'acc@crm.local', 'hash', 1)");
            $aId = (int)$this->pdo->lastInsertId();
        }

        $this->setUserSession($aId, 'accountant');

        $this->assertTrue(PermissionService::can('client.view_all'));
        $this->assertTrue(PermissionService::can('client.view_tax'));
        $this->assertTrue(PermissionService::can('service.manage'));
        $this->assertTrue(PermissionService::can('invoice.manage'));
        $this->assertTrue(PermissionService::can('payment.view'));
        $this->assertTrue(PermissionService::can('payment.record'));
        $this->assertTrue(PermissionService::can('report.view_financial'));
        $this->assertTrue(PermissionService::can('reminder.manage'));

        // Prohibited: No user management
        $this->assertFalse(PermissionService::can('user.manage'));
        $this->assertFalse(PermissionService::can('system.settings'));
    }

    /**
     * Requirement: Prove a Trainer cannot see client tax data.
     */
    public function testTrainerCannotSeeClientTaxData(): void
    {
        $tStmt = $this->pdo->query("SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'trainer' LIMIT 1) LIMIT 1");
        $tId = (int)$tStmt->fetchColumn();
        if (!$tId) {
            $rId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'trainer' LIMIT 1")->fetchColumn();
            $this->pdo->exec("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES ({$rId}, 'Trainer Vikram', 'vikram@crm.local', 'hash', 1)");
            $tId = (int)$this->pdo->lastInsertId();
        }

        $this->setUserSession($tId, 'trainer');

        // 1. Verify Trainer has NO client viewing permissions
        $this->assertFalse(PermissionService::can('client.view_all'));
        $this->assertFalse(PermissionService::can('client.view_own'));
        $this->assertFalse(PermissionService::can('client.view_tax'));
        $this->assertFalse(PermissionService::can('client.create'));
        $this->assertFalse(PermissionService::can('client.edit'));
        $this->assertFalse(PermissionService::can('client.delete'));

        // 2. ClientService::listClients() must throw 403 RuntimeException
        $exceptionCaught = false;
        try {
            $this->clientService->listClients();
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertSame(403, $e->getCode());
        }
        $this->assertTrue($exceptionCaught, 'Trainer must be rejected with 403 when trying to list clients');

        // 3. ClientService::getClient() must throw 403 RuntimeException
        $exceptionCaught = false;
        try {
            $this->clientService->getClient(1);
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertSame(403, $e->getCode());
        }
        $this->assertTrue($exceptionCaught, 'Trainer must be rejected with 403 when trying to view a client profile or tax data');
    }

    /**
     * Requirement: Prove a Trainer cannot see payments or fees.
     */
    public function testTrainerCannotSeePayments(): void
    {
        $tStmt = $this->pdo->query("SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'trainer' LIMIT 1) LIMIT 1");
        $tId = (int)$tStmt->fetchColumn();
        if (!$tId) {
            $rId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'trainer' LIMIT 1")->fetchColumn();
            $this->pdo->exec("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES ({$rId}, 'Trainer Vikram', 'vikram@crm.local', 'hash', 1)");
            $tId = (int)$this->pdo->lastInsertId();
        }

        $this->setUserSession($tId, 'trainer');

        // 1. Verify Trainer has NO payment permissions
        $this->assertFalse(PermissionService::can('payment.view'));
        $this->assertFalse(PermissionService::can('payment.record'));
        $this->assertFalse(PermissionService::can('payment.manage'));
        $this->assertFalse(PermissionService::can('invoice.manage'));

        // 2. PaymentService::listPayments() must throw 403 RuntimeException
        $exceptionCaught = false;
        try {
            $this->paymentService->listPayments();
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertSame(403, $e->getCode());
        }
        $this->assertTrue($exceptionCaught, 'Trainer must be rejected with 403 when trying to list payments');

        // 3. PaymentService::getPayment() must throw 403 RuntimeException
        $exceptionCaught = false;
        try {
            $this->paymentService->getPayment(1);
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertSame(403, $e->getCode());
        }
        $this->assertTrue($exceptionCaught, 'Trainer must be rejected with 403 when trying to view a payment');
    }

    public function testTrainerCanOnlyManageAssignedAcademicTasks(): void
    {
        $tStmt = $this->pdo->query("SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'trainer' LIMIT 1) LIMIT 1");
        $tId = (int)$tStmt->fetchColumn();
        if (!$tId) {
            $rId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'trainer' LIMIT 1")->fetchColumn();
            $this->pdo->exec("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES ({$rId}, 'Trainer User', 'trainer@crm.local', 'hash', 1)");
            $tId = (int)$this->pdo->lastInsertId();
        }

        $this->setUserSession($tId, 'trainer');

        $this->assertTrue(PermissionService::can('batch.view_assigned'));
        $this->assertTrue(PermissionService::can('student.view_assigned'));
        $this->assertTrue(PermissionService::can('attendance.manage'));
        $this->assertTrue(PermissionService::can('attendance.view'));
        $this->assertTrue(PermissionService::can('progress.manage'));
        $this->assertTrue(PermissionService::can('report.view_academic'));
    }

    public function testPermissionMatrixToggleAPI(): void
    {
        $this->setUserSession(1, 'admin');

        $counselorRoleId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'counselor' LIMIT 1")->fetchColumn();
        $counselorUserId = (int)$this->pdo->query("SELECT id FROM users WHERE role_id = {$counselorRoleId} LIMIT 1")->fetchColumn();
        if (!$counselorUserId) {
            $this->pdo->exec("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES ({$counselorRoleId}, 'Counselor Test', 'counselor_toggle@crm.local', 'hash', 1)");
            $counselorUserId = (int)$this->pdo->lastInsertId();
        }

        // 1. Toggle OFF 'lead.convert' for Counselor
        $res = $this->rolePermService->toggle($counselorRoleId, 'lead.convert', false);
        $this->assertFalse($res['enabled']);

        // Check that Counselor no longer has 'lead.convert'
        $this->setUserSession($counselorUserId, 'counselor');
        $this->assertFalse(PermissionService::can('lead.convert'));

        // 2. Toggle ON 'lead.convert' for Counselor
        $this->setUserSession(1, 'admin');
        $res = $this->rolePermService->toggle($counselorRoleId, 'lead.convert', true);
        $this->assertTrue($res['enabled']);

        // Check that Counselor now has 'lead.convert' again
        $this->setUserSession($counselorUserId, 'counselor');
        $this->assertTrue(PermissionService::can('lead.convert'));

        // 3. Admin permissions cannot be revoked
        $this->setUserSession(1, 'admin');
        $adminRoleId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn();
        $this->expectException(RuntimeException::class);
        $this->rolePermService->toggle($adminRoleId, 'client.view_all', false);
    }
}
