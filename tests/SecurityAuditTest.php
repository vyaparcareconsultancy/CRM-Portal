<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Helpers\Crypto;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PermissionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class SecurityAuditTest extends TestCase
{
    private PDO $pdo;
    private Client $clientModel;
    private ClientDocument $docModel;
    private ActivityLog $activityLog;
    private User $userModel;
    private ClientService $clientService;
    private string $tempUploadDir;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        Database::setConnection($this->pdo);

        $this->pdo->exec("
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                label TEXT
            );
            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                label TEXT
            );
            CREATE TABLE role_permissions (
                role_id INTEGER,
                permission_id INTEGER,
                PRIMARY KEY (role_id, permission_id)
            );
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INTEGER,
                name TEXT,
                email TEXT UNIQUE,
                password TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT,
                updated_at TEXT,
                deleted_at TEXT NULL
            );
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_code TEXT UNIQUE,
                client_type TEXT,
                name TEXT,
                contact_person TEXT NULL,
                email TEXT UNIQUE,
                mobile TEXT UNIQUE,
                alt_mobile TEXT NULL,
                gst_no TEXT NULL,
                pan_no TEXT NULL,
                industry TEXT NULL,
                company_size TEXT NULL,
                website TEXT NULL,
                address_line1 TEXT,
                address_line2 TEXT NULL,
                city TEXT,
                state TEXT,
                pincode TEXT,
                country TEXT DEFAULT 'India',
                lead_source TEXT NULL,
                assigned_to INTEGER,
                status TEXT DEFAULT 'new',
                tags TEXT NULL,
                notes TEXT NULL,
                consent_given INTEGER DEFAULT 1,
                consent_at TEXT,
                created_by INTEGER,
                created_at TEXT,
                updated_at TEXT,
                deleted_at TEXT NULL
            );
            CREATE TABLE client_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER,
                original_name TEXT,
                stored_name TEXT,
                mime_type TEXT,
                size_bytes INTEGER,
                uploaded_by INTEGER,
                created_at TEXT,
                deleted_at TEXT NULL
            );
            CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                entity_type TEXT,
                entity_id INTEGER,
                action TEXT,
                new_values TEXT NULL,
                old_values TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT
            );
        ");

        $this->pdo->exec("
            INSERT INTO roles (id, name, label) VALUES (1, 'admin', 'Administrator'), (2, 'sales', 'Sales');
            INSERT INTO permissions (id, name, label) VALUES 
                (1, 'client.create', 'Create Client'),
                (2, 'client.view_all', 'View All Clients'),
                (3, 'client.view_own', 'View Own Clients'),
                (4, 'client.edit', 'Edit Client'),
                (5, 'client.delete', 'Delete Client'),
                (6, 'user.manage', 'Manage Users');
            INSERT INTO role_permissions (role_id, permission_id) VALUES 
                (1, 1), (1, 2), (1, 4), (1, 5), (1, 6),
                (2, 1), (2, 3), (2, 4);
            INSERT INTO users (id, role_id, name, email, password, is_active) VALUES 
                (1, 1, 'Admin', 'admin@techtians.in', 'hash', 1),
                (2, 2, 'Sales', 'sales@techtians.in', 'hash', 1);
        ");

        $this->clientModel = new Client($this->pdo);
        $this->docModel = new ClientDocument($this->pdo);
        $this->activityLog = new ActivityLog($this->pdo);
        $this->userModel = new User($this->pdo);

        $this->tempUploadDir = sys_get_temp_dir() . '/phpunit_sec_' . bin2hex(random_bytes(6));
        mkdir($this->tempUploadDir, 0755, true);

        $this->clientService = new ClientService(
            $this->clientModel,
            $this->docModel,
            $this->activityLog,
            $this->userModel,
            $this->tempUploadDir
        );

        $_SESSION = [];
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Admin';
        PermissionService::loadUserPermissions(1, $this->pdo);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempUploadDir)) {
            $files = glob($this->tempUploadDir . '/*');
            if ($files !== false) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->tempUploadDir);
        }
        $_SESSION = [];
    }

    public function testPanEncryptionAtRestAndMasking(): void
    {
        $rawPan = 'ABCDE1234F';
        $encrypted = Crypto::encryptPan($rawPan);
        $this->assertNotNull($encrypted);
        $this->assertNotSame($rawPan, $encrypted);

        $decrypted = Crypto::decryptPan($encrypted);
        $this->assertSame($rawPan, $decrypted);

        $masked = Crypto::maskPan($rawPan);
        $this->assertSame('******234F', $masked);
    }

    public function testPasswordPolicyValidation(): void
    {
        $tooShort = Validator::make(['password' => 'Ab1!'], ['password' => 'password_policy']);
        $this->assertTrue($tooShort->fails());

        $noNumbers = Validator::make(['password' => 'PasswordOnly'], ['password' => 'password_policy']);
        $this->assertTrue($noNumbers->fails());

        $noLetters = Validator::make(['password' => '123456789'], ['password' => 'password_policy']);
        $this->assertTrue($noLetters->fails());

        $blacklisted = Validator::make(['password' => 'password123'], ['password' => 'password_policy']);
        $this->assertTrue($blacklisted->fails());

        $valid = Validator::make(['password' => 'TechTians@2026'], ['password' => 'password_policy']);
        $this->assertTrue($valid->passes());
    }

    public function testClientCreationEncryptsPanAtRestAndMasksOnDisplay(): void
    {
        $clientData = [
            'client_type' => 'individual',
            'name' => 'Aditya Verma',
            'email' => 'aditya@example.com',
            'mobile' => '9811223344',
            'pan_no' => 'ABCDE1234F',
            'address_line1' => 'Park Street',
            'city' => 'Kolkata',
            'state' => 'West Bengal',
            'pincode' => '700016',
            'consent_given' => '1',
        ];

        $res = $this->clientService->createClient($clientData, [], 1);
        $clientId = (int)$res['id'];

        // Direct DB select: pan_no is encrypted
        $stmt = $this->pdo->query("SELECT pan_no FROM clients WHERE id = {$clientId}");
        $row = $stmt->fetch();
        $this->assertNotSame('ABCDE1234F', $row['pan_no']);
        $this->assertGreaterThan(40, strlen((string)$row['pan_no']));

        // Display client profile masks PAN
        $profile = $this->clientService->getClientProfile($clientId);
        $this->assertSame('******234F', $profile['client']['pan_no']);
        $this->assertSame('ABCDE1234F', $profile['client']['pan_no_raw']);
    }

    public function testDpdpActAnonymizationPurgesFilesAndRedactsPii(): void
    {
        $clientData = [
            'client_type' => 'company',
            'name' => 'Data Corp Pvt Ltd',
            'contact_person' => 'Sunil Gupta',
            'email' => 'sunil@datacorp.com',
            'mobile' => '9899887766',
            'pan_no' => 'ABCDE1234F',
            'address_line1' => 'Sector 62',
            'city' => 'Noida',
            'state' => 'Uttar Pradesh',
            'pincode' => '201301',
            'consent_given' => '1',
        ];

        $res = $this->clientService->createClient($clientData, [], 1);
        $clientId = (int)$res['id'];

        // Create dummy physical file
        $clientDir = $this->tempUploadDir . '/' . $clientId;
        mkdir($clientDir, 0755, true);
        $dummyFile = $clientDir . '/contract.pdf';
        file_put_contents($dummyFile, 'contract data');

        $this->docModel->createDocument([
            'client_id' => $clientId,
            'original_name' => 'contract.pdf',
            'stored_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 13,
            'uploaded_by' => 1,
        ]);

        $this->assertFileExists($dummyFile);

        // Sales user cannot anonymize
        $_SESSION['user_id'] = 2;
        PermissionService::loadUserPermissions(2, $this->pdo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);
        $this->clientService->anonymizeClient($clientId, 2);

        // Reset to admin and anonymize
        $_SESSION['user_id'] = 1;
        PermissionService::loadUserPermissions(1, $this->pdo);

        $anon = $this->clientService->anonymizeClient($clientId, 1);
        $this->assertTrue($anon);

        // Verify physical file purged
        $this->assertFileDoesNotExist($dummyFile);

        // Verify PII erased
        $stmt = $this->pdo->query("SELECT * FROM clients WHERE id = {$clientId}");
        $record = $stmt->fetch();
        $this->assertSame("Anonymized Client #{$clientId}", $record['name']);
        $this->assertNull($record['contact_person']);
        $this->assertNull($record['pan_no']);
        $this->assertSame('inactive', $record['status']);
        $this->assertNotNull($record['deleted_at']);
    }
}
