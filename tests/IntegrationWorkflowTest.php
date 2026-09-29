<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ClientService;
use App\Services\PermissionService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * End-to-end integration test covering:
 * - Auth: login, lockout, logout
 * - Client CRUD & Scoping (Sales vs Admin/Manager)
 * - Document Uploads & Authorization
 * - Export permissions & PAN masking
 */
final class IntegrationWorkflowTest extends TestCase
{
    private PDO $pdo;
    private AuthService $authService;
    private ClientService $clientService;
    private string $tempUploadDir;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        Database::setConnection($this->pdo);

        $this->pdo->exec("
            CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, label TEXT);
            CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, label TEXT);
            CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER, PRIMARY KEY (role_id, permission_id));
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INTEGER,
                name TEXT,
                email TEXT UNIQUE,
                password_hash TEXT,
                is_active INTEGER DEFAULT 1,
                failed_attempts INTEGER DEFAULT 0,
                locked_until TEXT NULL,
                last_login_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_code TEXT UNIQUE,
                client_type TEXT DEFAULT 'individual',
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
                address_line1 TEXT NULL,
                address_line2 TEXT NULL,
                city TEXT NULL,
                state TEXT NULL,
                pincode TEXT NULL,
                country TEXT DEFAULT 'India',
                lead_source TEXT NULL,
                assigned_to INTEGER NULL,
                status TEXT DEFAULT 'new',
                tags TEXT NULL,
                notes TEXT NULL,
                consent_given INTEGER DEFAULT 0,
                consent_at TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE client_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER,
                stored_name TEXT,
                original_name TEXT,
                mime_type TEXT,
                size_bytes INTEGER,
                uploaded_by INTEGER,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                entity_type TEXT,
                entity_id INTEGER,
                action TEXT,
                old_values TEXT NULL,
                new_values TEXT NULL,
                ip TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT,
                ip_address TEXT,
                successful INTEGER,
                attempted_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT,
                token_hash TEXT,
                expires_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->seedTestDataset();

        $this->tempUploadDir = sys_get_temp_dir() . '/crm_int_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempUploadDir, 0755, true);

        $userModel = new User();
        $clientModel = new Client();
        $docModel = new ClientDocument();
        $activityLog = new ActivityLog();
        $loginAttemptModel = new LoginAttempt();
        $passwordResetModel = new PasswordReset();

        $this->authService = new AuthService($userModel, $loginAttemptModel, $passwordResetModel);
        $this->clientService = new ClientService($clientModel, $docModel, $activityLog, $userModel, $this->tempUploadDir);
    }

    protected function tearDown(): void
    {
        Session::destroy();
        self::rmdirRecursive($this->tempUploadDir);
    }

    private function seedTestDataset(): void
    {
        $this->pdo->exec("
            INSERT INTO roles (id, name, label) VALUES
                (1, 'admin', 'Administrator'),
                (2, 'manager', 'Sales Manager'),
                (3, 'sales', 'Sales Representative');

            INSERT INTO permissions (id, name, label) VALUES
                (1, 'client.view_all', 'View All Clients'),
                (2, 'client.view_own', 'View Own Clients'),
                (3, 'client.create', 'Create Client'),
                (4, 'client.edit', 'Edit Client'),
                (5, 'client.delete', 'Delete Client'),
                (6, 'client.export', 'Export Clients'),
                (7, 'user.manage', 'Manage Users');

            -- Admin: All permissions (1-7)
            INSERT INTO role_permissions (role_id, permission_id) VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7);
            -- Manager: view_all, create, edit, export (1,3,4,6)
            INSERT INTO role_permissions (role_id, permission_id) VALUES (2,1),(2,3),(2,4),(2,6);
            -- Sales: view_own, create, edit (2,3,4)
            INSERT INTO role_permissions (role_id, permission_id) VALUES (3,2),(3,3),(3,4);
        ");

        $hash = password_hash('Secret123!', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("INSERT INTO users (id, role_id, name, email, password_hash, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([1, 1, 'Admin User', 'admin@crm.local', $hash]);
        $stmt->execute([2, 2, 'Manager User', 'manager@crm.local', $hash]);
        $stmt->execute([3, 3, 'Sales Alice', 'alice@crm.local', $hash]);
        $stmt->execute([4, 3, 'Sales Bob', 'bob@crm.local', $hash]);
    }

    /**
     * 1. Test Auth: Login, Lockout (5 attempts), Logout.
     */
    public function testAuthLifecycleAndLockout(): void
    {
        // Bad password fails
        $res1 = $this->authService->login('alice@crm.local', 'WrongPassword!', '127.0.0.1');
        $this->assertFalse($res1['success']);
        $this->assertSame(401, $res1['status_code']);

        // 4 more failures (total 5) triggers lockout
        $this->authService->login('alice@crm.local', 'WrongPassword!', '127.0.0.1');
        $this->authService->login('alice@crm.local', 'WrongPassword!', '127.0.0.1');
        $this->authService->login('alice@crm.local', 'WrongPassword!', '127.0.0.1');
        $res5 = $this->authService->login('alice@crm.local', 'WrongPassword!', '127.0.0.1');
        $this->assertFalse($res5['success']);
        $this->assertSame(423, $res5['status_code']);
        $this->assertStringContainsString('temporarily locked', $res5['message']);

        // Even correct password is now locked out
        $resLocked = $this->authService->login('alice@crm.local', 'Secret123!', '127.0.0.1');
        $this->assertFalse($resLocked['success']);
        $this->assertSame(423, $resLocked['status_code']);

        // Successful login with another account
        $resAdmin = $this->authService->login('admin@crm.local', 'Secret123!', '127.0.0.1');
        $this->assertTrue($resAdmin['success']);
        $this->assertSame(200, $resAdmin['status_code']);
        $this->assertSame(1, Session::get('user_id'));

        // Logout
        $this->authService->logout();
        $this->assertNull(Session::get('user_id'));
    }

    /**
     * 2. Test Client CRUD and Data Scoping (Sales vs Manager/Admin).
     */
    public function testClientCrudAndScoping(): void
    {
        // Authenticate as Sales Alice (U#3)
        $this->authService->login('alice@crm.local', 'Secret123!', '127.0.0.1');
        PermissionService::clearCache();

        // Alice creates Client A (auto-assigned to Alice)
        $clientA = $this->clientService->createClient([
            'client_type' => 'individual',
            'name' => 'Alice Client',
            'email' => 'client.a@example.com',
            'mobile' => '9876543210',
            'consent_given' => '1',
            'state' => 'Maharashtra',
        ]);
        $this->assertStringStartsWith('CL-', $clientA['client_code']);

        // Authenticate as Sales Bob (U#4)
        $this->authService->login('bob@crm.local', 'Secret123!', '127.0.0.1');
        PermissionService::clearCache();

        // Bob creates Client B
        $clientB = $this->clientService->createClient([
            'client_type' => 'company',
            'name' => 'Bob Company',
            'contact_person' => 'Robert',
            'email' => 'client.b@example.com',
            'mobile' => '9876543211',
            'consent_given' => '1',
            'state' => 'Delhi',
        ]);

        // Scoping Check: Bob lists clients — should only see Bob's client
        $bobList = $this->clientService->listClients([]);
        $this->assertSame(1, $bobList['records_total']);
        $this->assertSame('Bob Company', $bobList['items'][0]['name']);

        // Bob attempts to view Alice's client -> access denied (404/403)
        $this->assertNull($this->clientService->getClient($clientA['id']));

        // Bob attempts to update Alice's client -> fails
        $updated = $this->clientService->updateClient($clientA['id'], ['name' => 'Hacked Name']);
        $this->assertNotSame('Hacked Name', $updated['name']);

        // Authenticate as Manager (U#2, has client.view_all)
        $this->authService->login('manager@crm.local', 'Secret123!', '127.0.0.1');
        PermissionService::clearCache();

        // Manager sees BOTH clients
        $managerList = $this->clientService->listClients([]);
        $this->assertSame(2, $managerList['records_total']);

        // Manager can update Alice's client
        $updatedByMgr = $this->clientService->updateClient($clientA['id'], ['city' => 'Mumbai']);
        $this->assertSame('Mumbai', $updatedByMgr['city']);

        // Manager soft-deletes Bob's client
        $this->assertTrue($this->clientService->deleteClient($clientB['id']));

        // Excluded from subsequent searches
        $managerListAfterDelete = $this->clientService->listClients([]);
        $this->assertSame(1, $managerListAfterDelete['records_total']);
    }

    /**
     * 3. Test File Uploads: MIME validation, secure path, download scoping.
     */
    public function testFileUploadsAndSecureDownload(): void
    {
        // Login as Alice
        $this->authService->login('alice@crm.local', 'Secret123!', '127.0.0.1');
        PermissionService::clearCache();

        // Create client
        $client = $this->clientService->createClient([
            'client_type' => 'individual',
            'name' => 'Doc Test Client',
            'email' => 'doctest@example.com',
            'mobile' => '9811223344',
            'consent_given' => '1',
        ]);

        // Attach valid PDF probe
        $pdfPath = tempnam(sys_get_temp_dir(), 'test_pdf');
        file_put_contents($pdfPath, "%PDF-1.4 sample content");
        $file = [
            'name' => 'agreement.pdf',
            'tmp_name' => $pdfPath,
            'size' => filesize($pdfPath),
            'type' => 'application/pdf',
            'error' => UPLOAD_ERR_OK,
        ];

        $docRecord = $this->clientService->addDocument($client['id'], $file);
        $this->assertSame('agreement.pdf', $docRecord['original_name']);

        // Verify stored in non-web directory
        $expectedDir = $this->tempUploadDir . '/' . $client['id'];
        $this->assertDirectoryExists($expectedDir);

        // Download document as Alice (permitted)
        $downloadAlice = $this->clientService->getDocumentForDownload($client['id'], $docRecord['id']);
        $this->assertFileExists($downloadAlice['file_path']);

        // Login as Bob (different sales rep)
        $this->authService->login('bob@crm.local', 'Secret123!', '127.0.0.1');
        PermissionService::clearCache();

        // Bob tries to download Alice's client document -> access denied
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);
        $this->clientService->getDocumentForDownload($client['id'], $docRecord['id']);
    }

    /**
     * 4. Test Export Permissions and PAN Masking.
     */
    public function testExportPermissionsAndPanMasking(): void
    {
        // Admin creates client with PAN
        $this->authService->login('admin@crm.local', 'Secret123!', '127.0.0.1');
        PermissionService::clearCache();

        $client = $this->clientService->createClient([
            'client_type' => 'individual',
            'name' => 'Tax Payer',
            'email' => 'tax@example.com',
            'mobile' => '9899887766',
            'pan_no' => 'ABCDE1234F',
            'consent_given' => '1',
        ]);

        // Admin export: Admin gets full PAN unmasked
        $adminExport = $this->clientService->getClientsForExport([], 1, true);
        $this->assertCount(1, $adminExport);
        $this->assertSame('ABCDE1234F', $adminExport[0]['pan_no']);

        // Manager export: Manager has client.export but is NOT admin -> PAN is masked
        $this->authService->login('manager@crm.local', 'Secret123!', '127.0.0.1');
        PermissionService::clearCache();

        $managerExport = $this->clientService->getClientsForExport([], 2, true);
        $this->assertCount(1, $managerExport);
        $this->assertSame('******1234', $managerExport[0]['pan_no']);

        // Sales user does NOT have client.export permission
        $this->authService->login('alice@crm.local', 'Secret123!', '127.0.0.1');
        PermissionService::clearCache();

        $this->assertFalse(PermissionService::can('client.export'));
    }

    private static function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? self::rmdirRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
