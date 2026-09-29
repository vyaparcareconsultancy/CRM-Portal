<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PermissionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ClientCreationTest extends TestCase
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
                password_hash TEXT,
                is_active INTEGER DEFAULT 1,
                failed_attempts INTEGER DEFAULT 0,
                locked_until TEXT NULL,
                last_login_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
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
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE client_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER,
                original_name TEXT,
                stored_name TEXT,
                mime_type TEXT,
                size_bytes INTEGER,
                uploaded_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                entity_type TEXT,
                entity_id INTEGER,
                action TEXT,
                old_values TEXT NULL,
                new_values TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT NULL
            );
        ");

        // Seed Roles
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (1, 'admin', 'Administrator')");
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (2, 'manager', 'Manager')");
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (3, 'sales', 'Sales Rep')");

        // Seed Permissions
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (1, 'client.create', 'Create Client')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (2, 'client.view_all', 'View All')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (3, 'client.view_own', 'View Own')");

        // Assign Permissions
        // Admin: client.create (1), client.view_all (2)
        $this->pdo->exec("INSERT INTO role_permissions VALUES (1, 1), (1, 2)");
        // Manager: client.create (1), client.view_all (2)
        $this->pdo->exec("INSERT INTO role_permissions VALUES (2, 1), (2, 2)");
        // Sales: client.create (1), client.view_own (3)
        $this->pdo->exec("INSERT INTO role_permissions VALUES (3, 1), (3, 3)");

        // Seed Users
        // User 1: Admin
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (1, 1, 'Admin User', 'admin@crm.local', 1)");
        // User 10: Sales Rep Alpha
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (10, 3, 'Sales Alpha', 'alpha@crm.local', 1)");
        // User 20: Sales Rep Beta
        $this->pdo->exec("INSERT INTO users (id, role_id, name, email, is_active) VALUES (20, 3, 'Sales Beta', 'beta@crm.local', 1)");

        // Temporary storage dir for uploads in tests
        $this->tempUploadDir = sys_get_temp_dir() . '/crm_test_uploads_' . bin2hex(random_bytes(6));
        if (!is_dir($this->tempUploadDir)) {
            mkdir($this->tempUploadDir, 0777, true);
        }

        $this->clientModel = new Client($this->pdo);
        $this->docModel = new ClientDocument($this->pdo);
        $this->activityLog = new ActivityLog($this->pdo);
        $this->userModel = new User($this->pdo);

        $this->clientService = new ClientService(
            $this->clientModel,
            $this->docModel,
            $this->activityLog,
            $this->userModel,
            $this->tempUploadDir
        );
    }

    protected function tearDown(): void
    {
        // Cleanup temp upload dir
        if (is_dir($this->tempUploadDir)) {
            $this->recursiveRmdir($this->tempUploadDir);
        }
    }

    private function recursiveRmdir(string $dir): void
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveRmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testCreateClientSuccessWithDocumentsAndActivityLog(): void
    {
        Session::start();
        Session::set('user_id', 1); // Admin
        PermissionService::refresh();

        // Create a temporary valid PDF file for upload test
        $tmpPdf = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tmpPdf, "%PDF-1.4\n1 0 obj\n<<\n>>\nendobj\ntrailer\n<<\n>>\n%%EOF");

        $data = [
            'client_type' => 'company',
            'name' => 'Acme Corporation',
            'contact_person' => 'Jane Smith',
            'email' => 'contact@acme.com',
            'mobile' => '9876543210',
            'alt_mobile' => '9123456780',
            'pan_no' => 'abcde1234f', // lowercase to test uppercase normalization
            'gst_no' => '27abcde1234f1z5', // lowercase to test uppercase normalization
            'industry' => 'IT & Software',
            'company_size' => '51-200',
            'website' => 'https://acme.com',
            'address_line1' => '100 Innovation Way',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '560001',
            'consent_given' => '1',
            'assigned_to' => '10',
        ];

        $files = [
            'documents' => [
                [
                    'name' => 'incorporation_cert.pdf',
                    'tmp_name' => $tmpPdf,
                    'size' => filesize($tmpPdf),
                    'error' => UPLOAD_ERR_OK,
                ]
            ]
        ];

        $result = $this->clientService->createClient($data, $files, 1, '127.0.0.1', 'PHPUnit Test Agent');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('client_code', $result);
        $this->assertMatchesRegularExpression('/^CL-\d{4}-0001$/', $result['client_code']);

        // Verify client database record
        $client = $this->clientModel->find($result['id']);
        $this->assertNotNull($client);
        $this->assertSame('Acme Corporation', $client['name']);
        $this->assertSame('contact@acme.com', $client['email']);
        $this->assertSame('9876543210', $client['mobile']);
        $this->assertSame('ABCDE1234F', $client['pan_no']); // normalized uppercase
        $this->assertSame('27ABCDE1234F1Z5', $client['gst_no']); // normalized uppercase
        $this->assertSame(10, (int)$client['assigned_to']);

        // Verify document record & storage
        $docs = $this->docModel->getByClient($result['id']);
        $this->assertCount(1, $docs);
        $this->assertSame('incorporation_cert.pdf', $docs[0]['original_name']);
        $this->assertFileExists($this->tempUploadDir . '/' . $result['id'] . '/' . $docs[0]['stored_name']);

        // Verify activity log record
        $logs = $this->activityLog->where(['entity_type' => 'client', 'entity_id' => $result['id']]);
        $this->assertCount(1, $logs);
        $this->assertSame('create', $logs[0]['action']);
        $this->assertSame(1, (int)$logs[0]['user_id']);

        if (file_exists($tmpPdf)) {
            @unlink($tmpPdf);
        }
    }

    public function testCreateClientDuplicateEmailFailsEvenIfSoftDeleted(): void
    {
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        // 1. Insert existing active client
        $data = [
            'client_type' => 'individual',
            'name' => 'Original Client',
            'email' => 'client@domain.com',
            'mobile' => '9888877771',
            'address_line1' => 'Street 1',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'consent_given' => '1',
        ];
        $this->clientService->createClient($data, [], 1);

        // 2. Try to create another client with duplicate email
        $duplicateData = [
            'client_type' => 'individual',
            'name' => 'Duplicate Client',
            'email' => 'CLIENT@domain.com', // Case insensitivity test
            'mobile' => '9888877772',
            'address_line1' => 'Street 2',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pincode' => '411001',
            'consent_given' => '1',
        ];

        try {
            $this->clientService->createClient($duplicateData, [], 1);
            $this->fail("Expected ValidationException was not thrown for duplicate email");
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('email', $errors);
            $this->assertSame('Client with this email already exists.', $errors['email']);
        }

        // 3. Soft-delete the original client and verify email is STILL rejected
        $this->pdo->exec("UPDATE clients SET deleted_at = '2026-09-27 12:00:00' WHERE email = 'client@domain.com'");

        try {
            $this->clientService->createClient($duplicateData, [], 1);
            $this->fail("Expected ValidationException was not thrown for soft-deleted duplicate email");
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('email', $errors);
            $this->assertSame('Client with this email already exists.', $errors['email']);
        }
    }

    public function testCreateClientInvalidGstFails(): void
    {
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        $data = [
            'client_type' => 'individual',
            'name' => 'Bad GST Client',
            'email' => 'badgst@domain.com',
            'mobile' => '9991112223',
            'gst_no' => 'INVALID_GST_123',
            'address_line1' => 'Street 1',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'pincode' => '110001',
            'consent_given' => '1',
        ];

        try {
            $this->clientService->createClient($data, [], 1);
            $this->fail("Expected ValidationException was not thrown for invalid GST");
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('gst_no', $errors);
            $this->assertSame('Invalid GSTIN format (15 characters, e.g. 27AAAAA0000A1Z5).', $errors['gst_no']);
        }
    }

    public function testSalesUserAutoAssignmentEnforced(): void
    {
        // Log in as Sales Alpha (User 10)
        Session::start();
        Session::set('user_id', 10);
        PermissionService::refresh();

        $this->assertTrue(PermissionService::can('client.create'));
        $this->assertFalse(PermissionService::can('client.view_all'));

        $data = [
            'client_type' => 'individual',
            'name' => 'Sales Assigned Client',
            'email' => 'salesclient@domain.com',
            'mobile' => '9112233445',
            'address_line1' => 'Street 10',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302001',
            'consent_given' => '1',
            // Sales tries to assign to User 20
            'assigned_to' => '20',
        ];

        $result = $this->clientService->createClient($data, [], 10);

        $client = $this->clientModel->find($result['id']);
        $this->assertNotNull($client);

        // Crucial requirement: Must be forced to self (User 10)
        $this->assertSame(10, (int)$client['assigned_to']);
    }

    public function testGstinStateCodeMismatchThrowsValidationError(): void
    {
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        $data = [
            'client_type' => 'company',
            'name' => 'Bihar Tech Ltd',
            'email' => 'bihar@tech.com',
            'mobile' => '9876500000',
            'state' => 'Bihar',
            'gst_no' => '27AAAAA0000A1Z5', // 27 is Maharashtra, state is Bihar (10)
            'pan_no' => 'AAAAA0000A',
            'consent_given' => '1',
        ];

        try {
            $this->clientService->createClient($data, [], 1);
            $this->fail("Expected ValidationException for GSTIN state mismatch");
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('gst_no', $errors);
            $this->assertStringContainsString('does not match selected state', $errors['gst_no']);
        }
    }

    public function testGstinStateCodeMatchSucceeds(): void
    {
        Session::start();
        Session::set('user_id', 1);
        PermissionService::refresh();

        $data = [
            'client_type' => 'company',
            'name' => 'Bihar Solutions Ltd',
            'contact_person' => 'Rajesh Kumar',
            'email' => 'bihar2@tech.com',
            'mobile' => '9876500001',
            'address_line1' => 'Main Road',
            'city' => 'Patna',
            'state' => 'Bihar',
            'pincode' => '800001',
            'gst_no' => '10AAAAA0000A1Z5', // 10 is Bihar
            'pan_no' => 'AAAAA0000A',
            'consent_given' => '1',
        ];

        $result = $this->clientService->createClient($data, [], 1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $client = $this->clientModel->find($result['id']);
        $this->assertSame('10AAAAA0000A1Z5', $client['gst_no']);
    }
}
