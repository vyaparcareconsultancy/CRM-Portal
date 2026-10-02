<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Helpers\Crypto;
use App\Services\ClientComplianceService;
use App\Services\PermissionService;
use App\Services\ServiceCatalogService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ClientComplianceTest extends TestCase
{
    private PDO $pdo;
    private ServiceCatalogService $catalogService;
    private ClientComplianceService $complianceService;

    private int $adminId;
    private int $accountantId;
    private int $counselorId;
    private int $trainerId;
    private int $testClientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = DatabaseResetter::reset(true) ?? Database::getConnection();

        $this->catalogService = new ServiceCatalogService();
        $this->complianceService = new ClientComplianceService();

        // Retrieve seeded user IDs
        $this->adminId = (int)$this->pdo->query("SELECT id FROM users WHERE email = 'admin@crm.local'")->fetchColumn();
        $this->accountantId = (int)$this->pdo->query("SELECT id FROM users WHERE email = 'accountant@crm.local'")->fetchColumn();
        $this->counselorId = (int)$this->pdo->query("SELECT id FROM users WHERE email = 'counselor@crm.local'")->fetchColumn();
        $this->trainerId = (int)$this->pdo->query("SELECT id FROM users WHERE email = 'trainer@crm.local'")->fetchColumn();

        // Create or get a test client
        $stmt = $this->pdo->query("SELECT id FROM clients LIMIT 1");
        $clientId = $stmt->fetchColumn();
        if (!$clientId) {
            $insert = $this->pdo->prepare("
                INSERT INTO clients (client_code, name, email, mobile, assigned_to, created_by, status)
                VALUES ('CL-TEST-01', 'Acme Enterprises', 'acme@test.local', '9876543210', ?, ?, 'active')
            ");
            $insert->execute([$this->accountantId ?: $this->adminId, $this->adminId]);
            $clientId = (int)$this->pdo->lastInsertId();
        }
        $this->testClientId = (int)$clientId;
    }

    private function setUserSession(int $userId, string $roleName): void
    {
        Session::start();
        Session::set('user_id', $userId);
        Session::set('user_role', $roleName);

        $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $stmt->execute([$roleName]);
        $roleId = (int)$stmt->fetchColumn();
        Session::set('role_id', $roleId);

        PermissionService::refresh();
    }

    /**
     * Requirement: Master services catalog (admin) has default services with
     * name, type (one-time / recurring), frequency (monthly / quarterly / yearly), default fee.
     */
    public function testMasterServicesCatalogDefaults(): void
    {
        $this->setUserSession($this->adminId, 'admin');

        $services = $this->catalogService->listServices();
        $this->assertNotEmpty($services, 'Services catalog should not be empty');

        $names = array_column($services, 'name');
        $expectedNames = [
            'GST Registration',
            'GST Return',
            'ITR',
            'Accounting/Bookkeeping',
            'Tax Audit',
            'Company/Firm Registration',
            'ROC Compliance',
            'TDS',
            'PF/ESI',
        ];

        foreach ($expectedNames as $expected) {
            $this->assertContains($expected, $names, "Master catalog must contain standard service: {$expected}");
        }

        // Verify structure of standard services
        $serviceMap = [];
        foreach ($services as $srv) {
            $serviceMap[$srv['name']] = $srv;
        }

        // GST Registration: one-time
        $this->assertSame('one_time', $serviceMap['GST Registration']['type']);
        $this->assertNull($serviceMap['GST Registration']['frequency']);
        $this->assertGreaterThan(0, (float)$serviceMap['GST Registration']['default_fee']);

        // GST Return: recurring, monthly
        $this->assertSame('recurring', $serviceMap['GST Return']['type']);
        $this->assertSame('monthly', $serviceMap['GST Return']['frequency']);
        $this->assertGreaterThan(0, (float)$serviceMap['GST Return']['default_fee']);

        // TDS: recurring, quarterly
        $this->assertSame('recurring', $serviceMap['TDS']['type']);
        $this->assertSame('quarterly', $serviceMap['TDS']['frequency']);

        // Tax Audit: recurring, yearly
        $this->assertSame('recurring', $serviceMap['Tax Audit']['type']);
        $this->assertSame('yearly', $serviceMap['Tax Audit']['frequency']);
    }

    /**
     * Requirement: Admin can create and manage catalog; Counselor / Trainer cannot.
     */
    public function testCatalogPermissionScoping(): void
    {
        // 1. Admin can create new service
        $this->setUserSession($this->adminId, 'admin');
        $newService = $this->catalogService->createService([
            'name' => 'FSSAI Food License',
            'code' => 'SRV-FSSAI-01',
            'type' => 'recurring',
            'frequency' => 'yearly',
            'default_fee' => 4500.00,
            'category' => 'registration',
        ]);
        $this->assertNotEmpty($newService['id']);
        $this->assertSame('FSSAI Food License', $newService['name']);
        $this->assertSame('yearly', $newService['frequency']);

        // 2. Counselor cannot create service in master catalog
        $this->setUserSession($this->counselorId, 'counselor');
        $counselorBlocked = false;
        try {
            $this->catalogService->createService([
                'name' => 'Unauthorized Service',
                'type' => 'one_time',
                'default_fee' => 1000.00,
            ]);
        } catch (RuntimeException $e) {
            $counselorBlocked = true;
            $this->assertSame(403, $e->getCode());
        }
        $this->assertTrue($counselorBlocked, 'Counselor must be forbidden (403) from managing service catalog');

        // 3. Trainer cannot create service in master catalog
        $this->setUserSession($this->trainerId, 'trainer');
        $trainerBlocked = false;
        try {
            $this->catalogService->createService([
                'name' => 'Unauthorized Service 2',
                'type' => 'one_time',
                'default_fee' => 1000.00,
            ]);
        } catch (RuntimeException $e) {
            $trainerBlocked = true;
            $this->assertSame(403, $e->getCode());
        }
        $this->assertTrue($trainerBlocked, 'Trainer must be forbidden (403) from managing service catalog');
    }

    /**
     * Requirement: On client profile: "Services" tab — add service with
     * start date, frequency, fee, assigned accountant, status (Active, Paused, Completed).
     */
    public function testClientServiceSubscriptionLifecycle(): void
    {
        // Use Accountant session (Accountant has client_service.manage)
        $this->setUserSession($this->accountantId, 'accountant');

        // Pick GST Return service
        $srvStmt = $this->pdo->query("SELECT id, default_fee FROM services WHERE name = 'GST Return' LIMIT 1");
        $srv = $srvStmt->fetch();
        $this->assertNotEmpty($srv);

        // Add service subscription
        $startDate = '2026-10-01';
        $created = $this->complianceService->addClientService($this->testClientId, [
            'service_id' => (int)$srv['id'],
            'start_date' => $startDate,
            'frequency' => 'monthly',
            'fee' => 2500.00,
            'assigned_accountant_id' => $this->accountantId,
            'status' => 'active',
            'notes' => 'Monthly GST return filing for Acme',
        ]);

        $this->assertNotEmpty($created['id']);
        $clientServiceId = (int)$created['id'];
        $this->assertSame('active', $created['status']);
        $this->assertSame('monthly', $created['frequency']);
        $this->assertEquals(2500.00, (float)$created['fee']);
        $this->assertSame($this->accountantId, (int)$created['assigned_accountant_id']);

        // Update status: Active -> Paused
        $paused = $this->complianceService->updateClientService($clientServiceId, [
            'status' => 'paused',
            'fee' => 2800.00,
        ]);
        $this->assertSame('paused', $paused['status']);
        $this->assertEquals(2800.00, (float)$paused['fee']);

        // Update status: Paused -> Completed
        $completed = $this->complianceService->updateClientService($clientServiceId, [
            'status' => 'completed',
        ]);
        $this->assertSame('completed', $completed['status']);

        // List services for client
        $list = $this->complianceService->listClientServices($this->testClientId);
        $this->assertNotEmpty($list);
        $found = false;
        foreach ($list as $item) {
            if ((int)$item['id'] === $clientServiceId) {
                $found = true;
                $this->assertSame('GST Return', $item['service_name']);
                $this->assertSame('completed', $item['status']);
            }
        }
        $this->assertTrue($found, 'Subscribed service must appear in client services list with details');
    }

    /**
     * Requirement: Compliance details per client:
     * GSTIN, GST filing type (monthly/QRMP), PAN, TAN, CIN/LLPIN, PF/ESI codes, financial year, login portal notes.
     * Never store portal passwords in plain text; if stored, encrypt and show only to Admin/Accountant with reveal + audit log.
     */
    public function testComplianceDetailsEncryptionAtRest(): void
    {
        $this->setUserSession($this->adminId, 'admin');

        $plainPasswordSecret = 'GSTPortal#StrongPassword2026!';
        $saveData = [
            'gstin' => '27AABCU9603R1ZM',
            'gst_filing_type' => 'qrmp',
            'pan' => 'AABCU9603R',
            'tan' => 'MUMA12345B',
            'cin_llpin' => 'U72900MH2026PTC123456',
            'pf_esi_codes' => 'PF: MH/BAN/12345 | ESI: 31000123450001001',
            'financial_year' => '2026-2027',
            'portal_notes' => 'Portal username: acmepvtltd, filing due on 13th for QRMP',
            'portal_credentials' => $plainPasswordSecret,
        ];

        $result = $this->complianceService->saveComplianceDetails($this->testClientId, $saveData);
        $this->assertNotNull($result);
        $this->assertTrue($result['has_portal_credentials']);
        // Plain text credential must NOT be returned in standard non-reveal view
        $this->assertNull($result['portal_credentials']);

        // Query the database directly to inspect raw storage
        $rawStmt = $this->pdo->prepare("SELECT portal_credentials_encrypted FROM client_compliance_details WHERE client_id = ?");
        $rawStmt->execute([$this->testClientId]);
        $rawStored = (string)$rawStmt->fetchColumn();

        $this->assertNotEmpty($rawStored, 'Encrypted portal credential field must not be empty in DB');
        $this->assertNotEquals($plainPasswordSecret, $rawStored, 'Portal password must NEVER be stored in plain text in database');

        // Confirm it is a valid base64 encoded AES-256-GCM payload (12-byte IV + 16-byte Tag + ciphertext)
        $rawBytes = base64_decode($rawStored, true);
        $this->assertNotFalse($rawBytes, 'Encrypted secret must be valid base64');
        $this->assertGreaterThanOrEqual(12 + 16 + 1, strlen($rawBytes), 'Payload must contain IV, Auth Tag, and Ciphertext');

        // Crypto decrypt should match original secret
        $decryptedDirectly = Crypto::decryptSecret($rawStored);
        $this->assertSame($plainPasswordSecret, $decryptedDirectly);
    }

    /**
     * Requirement: Show portal credentials only to Admin/Accountant with reveal + audit log.
     * Trainer and Counselor must be rejected with 403.
     */
    public function testRevealCredentialsAccessControlAndAuditLog(): void
    {
        $plainSecret = 'ITRPortalSecret$99!';

        // 1. Admin sets credentials
        $this->setUserSession($this->adminId, 'admin');
        $this->complianceService->saveComplianceDetails($this->testClientId, [
            'portal_credentials' => $plainSecret,
            'portal_notes' => 'ITR login credentials',
        ]);

        // 2. Admin can reveal credentials
        $adminView = $this->complianceService->getComplianceDetails($this->testClientId, true);
        $this->assertSame($plainSecret, $adminView['portal_credentials'], 'Admin must be able to reveal credentials');

        // Verify audit log has recorded the reveal
        $logStmt = $this->pdo->prepare("
            SELECT * FROM activity_log 
            WHERE entity_type = 'client_compliance' 
              AND entity_id = ? 
              AND action = 'reveal_portal_credential'
            ORDER BY id DESC LIMIT 1
        ");
        $logStmt->execute([$this->testClientId]);
        $adminLog = $logStmt->fetch();
        $this->assertNotEmpty($adminLog, 'Audit log must record credential reveal by admin');
        $this->assertSame($this->adminId, (int)$adminLog['user_id']);

        // 3. Accountant can reveal credentials
        $this->setUserSession($this->accountantId, 'accountant');
        $acctView = $this->complianceService->getComplianceDetails($this->testClientId, true);
        $this->assertSame($plainSecret, $acctView['portal_credentials'], 'Accountant must be able to reveal credentials');

        // Verify audit log has recorded the accountant reveal
        $logStmt->execute([$this->testClientId]);
        $acctLog = $logStmt->fetch();
        $this->assertNotEmpty($acctLog);
        $this->assertSame($this->accountantId, (int)$acctLog['user_id']);

        // 4. Counselor CANNOT reveal credentials (must throw 403)
        $this->setUserSession($this->counselorId, 'counselor');
        $counselorRejected = false;
        try {
            $this->complianceService->getComplianceDetails($this->testClientId, true);
        } catch (RuntimeException $e) {
            $counselorRejected = true;
            $this->assertSame(403, $e->getCode());
        }
        $this->assertTrue($counselorRejected, 'Counselor must be blocked from revealing credentials with 403');

        // 5. Trainer CANNOT reveal credentials (must throw 403)
        $this->setUserSession($this->trainerId, 'trainer');
        $trainerRejected = false;
        try {
            $this->complianceService->getComplianceDetails($this->testClientId, true);
        } catch (RuntimeException $e) {
            $trainerRejected = true;
            $this->assertSame(403, $e->getCode());
        }
        $this->assertTrue($trainerRejected, 'Trainer must be blocked from revealing credentials with 403');
    }

    /**
     * Requirement: Work tracker per service period
     * (e.g. GST Return Sep-2026: Pending / Data received / Filed / Acknowledged + ack number + date).
     */
    public function testWorkTrackerPerServicePeriod(): void
    {
        $this->setUserSession($this->accountantId, 'accountant');

        // Subscribe to GST Return first
        $srvId = (int)$this->pdo->query("SELECT id FROM services WHERE name = 'GST Return' LIMIT 1")->fetchColumn();
        $sub = $this->complianceService->addClientService($this->testClientId, [
            'service_id' => $srvId,
            'start_date' => '2026-09-01',
            'frequency' => 'monthly',
            'fee' => 2000.00,
            'status' => 'active',
        ]);
        $clientServiceId = (int)$sub['id'];

        // 1. Add Work Tracker item: Period "Sep-2026", Status "pending"
        $item = $this->complianceService->addWorkTrackerItem($this->testClientId, $clientServiceId, [
            'period' => 'Sep-2026',
            'status' => 'pending',
            'notes' => 'Awaiting purchase and sales registers from client',
        ]);

        $this->assertNotEmpty($item['id']);
        $trackerId = (int)$item['id'];
        $this->assertSame('Sep-2026', $item['period']);
        $this->assertSame('pending', $item['status']);
        $this->assertNull($item['acknowledgment_no']);
        $this->assertNull($item['filing_date']);

        // 2. Client provides data -> Update to "data_received"
        $updatedDataRecv = $this->complianceService->updateWorkTrackerItem($trackerId, [
            'status' => 'data_received',
            'notes' => 'Tally backup and sales CSV received on 5th Oct',
        ]);
        $this->assertSame('data_received', $updatedDataRecv['status']);

        // 3. Accountant files return -> Update to "filed" with acknowledgment number and filing date
        $ackNumber = 'ACK-GST-2026-SEP-889921';
        $filingDate = '2026-10-10';
        $updatedFiled = $this->complianceService->updateWorkTrackerItem($trackerId, [
            'status' => 'filed',
            'acknowledgment_no' => $ackNumber,
            'filing_date' => $filingDate,
            'notes' => 'GSTR-3B and GSTR-1 filed successfully',
        ]);
        $this->assertSame('filed', $updatedFiled['status']);
        $this->assertSame($ackNumber, $updatedFiled['acknowledgment_no']);
        $this->assertSame($filingDate, $updatedFiled['filing_date']);

        // 4. Update to "acknowledged"
        $updatedAck = $this->complianceService->updateWorkTrackerItem($trackerId, [
            'status' => 'acknowledged',
        ]);
        $this->assertSame('acknowledged', $updatedAck['status']);
        $this->assertSame($ackNumber, $updatedAck['acknowledgment_no']);

        // 5. Query work tracker list
        $trackerList = $this->complianceService->listWorkTracker($this->testClientId, $clientServiceId);
        $this->assertCount(1, $trackerList);
        $this->assertSame('Sep-2026', $trackerList[0]['period']);
        $this->assertSame('acknowledged', $trackerList[0]['status']);
        $this->assertSame($ackNumber, $trackerList[0]['acknowledgment_no']);
        $this->assertSame($filingDate, $trackerList[0]['filing_date']);
    }
}
