<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Course;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Service;
use App\Models\Student;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\LeadService;
use App\Services\LeadSourceService;
use App\Services\PermissionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class LeadManagementTest extends TestCase
{
    private PDO $pdo;
    private Lead $leadModel;
    private LeadSource $sourceModel;
    private Contact $contactModel;
    private Client $clientModel;
    private Student $studentModel;
    private FollowUp $followUpModel;
    private ActivityLog $activityLog;
    private LeadService $leadService;
    private FollowUpService $followUpService;
    private LeadSourceService $sourceService;

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
            CREATE TABLE contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contact_type TEXT DEFAULT 'individual',
                name TEXT,
                email TEXT NULL,
                mobile TEXT,
                whatsapp_number TEXT NULL,
                alt_mobile TEXT NULL,
                pan_no TEXT NULL,
                address_line1 TEXT NULL,
                city TEXT NULL,
                state TEXT NULL,
                pincode TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE lead_sources (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                is_active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
            CREATE TABLE services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE,
                name TEXT,
                category TEXT,
                default_fee REAL DEFAULT 0.0,
                billing_frequency TEXT DEFAULT 'one_time',
                is_active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE courses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_code TEXT UNIQUE,
                name TEXT,
                duration_weeks INTEGER DEFAULT 4,
                total_hours INTEGER DEFAULT 40,
                fee REAL DEFAULT 0.0,
                syllabus_summary TEXT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE students (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_code TEXT UNIQUE,
                contact_id INTEGER NULL,
                name TEXT,
                email TEXT NULL,
                mobile TEXT,
                whatsapp_number TEXT NULL,
                course_name TEXT NULL,
                qualification TEXT NULL,
                guardian_name TEXT NULL,
                guardian_mobile TEXT NULL,
                date_of_birth TEXT NULL,
                status TEXT DEFAULT 'enrolled',
                notes TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_code TEXT UNIQUE,
                contact_id INTEGER NULL,
                client_type TEXT DEFAULT 'individual',
                name TEXT,
                contact_person TEXT NULL,
                email TEXT UNIQUE,
                mobile TEXT UNIQUE,
                whatsapp_number TEXT NULL,
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
            CREATE TABLE leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_code TEXT UNIQUE,
                contact_id INTEGER NULL,
                name TEXT,
                mobile TEXT,
                whatsapp_number TEXT NULL,
                email TEXT NULL,
                lead_source_id INTEGER NULL,
                referred_by TEXT NULL,
                interest_type TEXT DEFAULT 'service',
                interested_in TEXT,
                service_id INTEGER NULL,
                course_id INTEGER NULL,
                status TEXT DEFAULT 'new',
                lost_reason TEXT NULL,
                assigned_to INTEGER NULL,
                notes TEXT NULL,
                converted_at TEXT NULL,
                converted_client_id INTEGER NULL,
                converted_student_id INTEGER NULL,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE follow_ups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NULL,
                lead_id INTEGER NULL,
                user_id INTEGER NOT NULL,
                due_at TEXT NOT NULL,
                type TEXT NOT NULL,
                notes TEXT NULL,
                remarks TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                outcome TEXT NULL,
                next_follow_up_at TEXT NULL,
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

        // Seed roles
        $this->pdo->exec("
            INSERT INTO roles (id, name, label) VALUES
            (1, 'admin', 'Administrator'),
            (2, 'manager', 'Manager'),
            (3, 'counselor', 'Counselor'),
            (4, 'sales', 'Sales Representative');
        ");

        // Seed permissions
        $this->pdo->exec("
            INSERT INTO permissions (id, name, label) VALUES
            (1, 'lead.view', 'View Leads'),
            (2, 'lead.view_all', 'View All Leads'),
            (3, 'lead.manage', 'Manage Leads'),
            (4, 'lead.convert', 'Convert Leads'),
            (5, 'lead_source.manage', 'Manage Lead Sources'),
            (6, 'followup.manage', 'Manage Follow-ups'),
            (7, 'client.view_all', 'View All Clients'),
            (8, 'client.create', 'Create Client');
        ");

        // Role permissions
        $this->pdo->exec("
            -- Admin has all
            INSERT INTO role_permissions VALUES (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8);
            -- Counselor has lead.view, lead.manage, lead.convert, followup.manage, client.create (NOT lead.view_all)
            INSERT INTO role_permissions VALUES (3, 1), (3, 3), (3, 4), (3, 6), (3, 8);
        ");

        // Seed Users: 1=Admin, 2=Counselor 1, 3=Counselor 2
        $this->pdo->exec("
            INSERT INTO users (id, role_id, name, email, password_hash, is_active) VALUES
            (1, 1, 'Super Admin', 'admin@crm.local', 'hash', 1),
            (2, 3, 'Counselor Sunita', 'sunita@crm.local', 'hash', 1),
            (3, 3, 'Counselor Rohit', 'rohit@crm.local', 'hash', 1);
        ");

        // Seed Lead Sources
        $this->pdo->exec("
            INSERT INTO lead_sources (id, name, is_active) VALUES
            (1, 'Instagram', 1),
            (2, 'Facebook', 1),
            (3, 'Google', 1),
            (4, 'Website', 1),
            (5, 'WhatsApp', 1),
            (6, 'Referral', 1),
            (7, 'Walk-in', 1);
        ");

        $this->leadModel = new Lead($this->pdo);
        $this->sourceModel = new LeadSource($this->pdo);
        $this->contactModel = new Contact($this->pdo);
        $this->clientModel = new Client($this->pdo);
        $this->studentModel = new Student($this->pdo);
        $this->followUpModel = new FollowUp($this->pdo);
        $this->activityLog = new ActivityLog($this->pdo);

        $this->leadService = new LeadService(
            $this->leadModel,
            $this->sourceModel,
            $this->contactModel,
            $this->clientModel,
            $this->studentModel,
            $this->activityLog
        );

        $this->followUpService = new FollowUpService(
            $this->followUpModel,
            $this->clientModel,
            $this->activityLog,
            $this->leadModel
        );

        $this->sourceService = new LeadSourceService($this->sourceModel);
    }

    private function authenticate(int $userId, int $roleId): void
    {
        Session::start();
        Session::set('user_id', $userId);
        Session::set('role_id', $roleId);
        PermissionService::refresh();
    }

    public function testCreateLeadWithAutoCodeAndContactSync(): void
    {
        $this->authenticate(1, 1); // Admin

        $lead = $this->leadService->create([
            'name' => 'Amit Sharma',
            'mobile' => '9876543210',
            'whatsapp_number' => '', // should default to mobile
            'email' => 'amit@example.com',
            'lead_source_id' => 1,
            'interest_type' => 'service',
            'interested_in' => 'GST Registration & Return Filing',
            'notes' => 'Looking for monthly return filing.',
        ]);

        $this->assertNotEmpty($lead['id']);
        $this->assertStringStartsWith('LD-', $lead['lead_code']);
        $this->assertSame('9876543210', $lead['mobile']);
        $this->assertSame('9876543210', $lead['whatsapp_number']);
        $this->assertSame('new', $lead['status']);
        $this->assertSame('Instagram', $lead['source_name']);

        // Verify shared contact created
        $contact = $this->contactModel->findByMobile('9876543210');
        $this->assertNotNull($contact);
        $this->assertSame('Amit Sharma', $contact['name']);
        $this->assertSame((int)$contact['id'], (int)$lead['contact_id']);
    }

    public function testDuplicateMobileCheckRejectsLead(): void
    {
        $this->authenticate(1, 1);

        $this->leadService->create([
            'name' => 'Original Contact',
            'mobile' => '9988776655',
            'interested_in' => 'ITR Filing',
        ]);

        $this->expectException(ValidationException::class);
        $this->leadService->create([
            'name' => 'Duplicate Contact',
            'mobile' => '9988776655',
            'interested_in' => 'Tally Course',
        ]);
    }

    public function testReferralSourceRequiresReferredBy(): void
    {
        $this->authenticate(1, 1);

        // Referral source ID is 6
        $this->expectException(ValidationException::class);
        $this->leadService->create([
            'name' => 'Referred Person',
            'mobile' => '9123456780',
            'lead_source_id' => 6,
            'interested_in' => 'Company Registration',
            'referred_by' => '', // Missing!
        ]);
    }

    public function testMarkingStatusLostRequiresLostReason(): void
    {
        $this->authenticate(1, 1);

        $lead = $this->leadService->create([
            'name' => 'Potential Client',
            'mobile' => '9812345678',
            'interested_in' => 'Accounting',
        ]);

        $this->expectException(ValidationException::class);
        $this->leadService->updateStatus($lead['id'], 'lost', '');
    }

    public function testKanbanGroupingAndStatusTransitions(): void
    {
        $this->authenticate(1, 1);

        $l1 = $this->leadService->create(['name' => 'Lead One', 'mobile' => '9111111111', 'interested_in' => 'GST']);
        $l2 = $this->leadService->create(['name' => 'Lead Two', 'mobile' => '9222222222', 'interested_in' => 'ITR']);

        // Move l2 to 'contacted'
        $this->leadService->updateStatus($l2['id'], 'contacted');

        $kanban = $this->leadService->getKanban([]);
        $this->assertArrayHasKey('new', $kanban);
        $this->assertArrayHasKey('contacted', $kanban);
        $this->assertArrayHasKey('interested', $kanban);
        $this->assertArrayHasKey('follow_up', $kanban);
        $this->assertArrayHasKey('converted', $kanban);
        $this->assertArrayHasKey('lost', $kanban);

        $newIds = array_column($kanban['new'], 'id');
        $contactedIds = array_column($kanban['contacted'], 'id');

        $this->assertContains((int)$l1['id'], array_map('intval', $newIds));
        $this->assertContains((int)$l2['id'], array_map('intval', $contactedIds));
    }

    public function testConvertLeadToClientAndStudent(): void
    {
        $this->authenticate(1, 1);

        $lead = $this->leadService->create([
            'name' => 'Pooja Verma',
            'mobile' => '9888877777',
            'email' => 'pooja@verma.in',
            'interested_in' => 'GST Practitioner Certification',
        ]);

        $conversion = $this->leadService->convert($lead['id'], [
            'convert_to_client' => true,
            'convert_to_student' => true,
            'client_type' => 'individual',
            'course_name' => 'GST Practitioner Certification',
        ]);

        $this->assertNotEmpty($conversion['client_code']);
        $this->assertStringStartsWith('CL-', $conversion['client_code']);
        $this->assertNotEmpty($conversion['student_code']);
        $this->assertStringStartsWith('ST-', $conversion['student_code']);

        // Check lead status
        $updatedLead = $this->leadService->get($lead['id']);
        $this->assertSame('converted', $updatedLead['status']);
        $this->assertSame((int)$conversion['client_id'], (int)$updatedLead['converted_client_id']);
        $this->assertSame((int)$conversion['student_id'], (int)$updatedLead['converted_student_id']);
        $this->assertNotNull($updatedLead['converted_at']);
    }

    public function testCsvImportWithDuplicateCheck(): void
    {
        $this->authenticate(1, 1);

        // Pre-insert an existing lead
        $this->leadService->create([
            'name' => 'Existing Lead',
            'mobile' => '9777777777',
            'interested_in' => 'Registration',
        ]);

        $csv = "Name,Mobile,WhatsApp,Email,Source,Interested In,Notes\n"
             . "New Prospect,9666666666,9666666666,new@prospect.in,Instagram,Tally Course,Great interest\n"
             . "Existing Lead Duplicate,9777777777,9777777777,exist@lead.in,Website,GST,Duplicate entry\n"
             . "Internal Duplicate,9555555555,9555555555,dup1@test.in,Google,ITR,First instance\n"
             . "Internal Duplicate Repeat,9555555555,9555555555,dup2@test.in,Google,ITR,Second instance\n";

        $summary = $this->leadService->importCsv($csv);

        $this->assertSame(2, $summary['imported']); // New Prospect, Internal Duplicate (1st)
        $this->assertSame(2, $summary['duplicates']); // Existing Lead Duplicate, Internal Duplicate Repeat
    }

    public function testCounselorScoping(): void
    {
        // 1. As Admin, create lead for Counselor Sunita (User 2) and Counselor Rohit (User 3)
        $this->authenticate(1, 1); // Admin

        $leadSunita = $this->leadService->create([
            'name' => 'Sunita Client',
            'mobile' => '9444411111',
            'interested_in' => 'GST',
            'assigned_to' => 2,
        ]);

        $leadRohit = $this->leadService->create([
            'name' => 'Rohit Client',
            'mobile' => '9444422222',
            'interested_in' => 'Accounting',
            'assigned_to' => 3,
        ]);

        // 2. Switch to Counselor Sunita (User 2, Role 3)
        $this->authenticate(2, 3);
        $sunitaList = $this->leadService->list([]);
        $sunitaIds = array_column($sunitaList['items'], 'id');

        $this->assertContains((int)$leadSunita['id'], array_map('intval', $sunitaIds));
        $this->assertNotContains((int)$leadRohit['id'], array_map('intval', $sunitaIds));

        // 3. Switch back to Admin -> sees both
        $this->authenticate(1, 1);
        $adminList = $this->leadService->list([]);
        $adminIds = array_column($adminList['items'], 'id');
        $this->assertContains((int)$leadSunita['id'], array_map('intval', $adminIds));
        $this->assertContains((int)$leadRohit['id'], array_map('intval', $adminIds));
    }

    public function testFollowUpForLeadWithOutcomeAndNextAutoSchedule(): void
    {
        $this->authenticate(1, 1);

        $lead = $this->leadService->create([
            'name' => 'Kunal Jain',
            'mobile' => '9333333333',
            'interested_in' => 'Tax Audit',
        ]);

        // Schedule first follow-up for lead
        $fu = $this->followUpService->create([
            'lead_id' => $lead['id'],
            'due_at' => date('Y-m-d 10:00:00', strtotime('+1 day')),
            'type' => 'call',
            'notes' => 'Introduction call and quote presentation',
        ]);

        $this->assertNotEmpty($fu['id']);
        $this->assertSame((int)$lead['id'], (int)$fu['lead_id']);
        $this->assertSame('lead', $fu['entity_type']);

        // Complete follow-up with outcome 'busy' and auto-schedule next follow-up 2 days later
        $nextDate = date('Y-m-d 14:00:00', strtotime('+3 days'));
        $completed = $this->followUpService->update($fu['id'], [
            'status' => 'done',
            'outcome' => 'busy',
            'remarks' => 'Client is in a meeting; asked to call on Friday 2 PM',
            'next_follow_up_at' => $nextDate,
            'next_follow_up_type' => 'call',
        ]);

        $this->assertSame('done', $completed['status']);
        $this->assertSame('busy', $completed['outcome']);

        // Verify that the next follow-up was auto-scheduled for the same lead!
        $allLeadFu = $this->followUpService->getByLead($lead['id']);
        $this->assertCount(2, $allLeadFu);
        $pending = array_filter($allLeadFu, fn($item) => $item['status'] === 'pending');
        $this->assertCount(1, $pending);
        $nextFu = reset($pending);
        $this->assertSame($nextDate, $nextFu['due_at']);
    }

    public function testAdminLeadSourcesManagement(): void
    {
        $this->authenticate(1, 1); // Admin

        // Create new lead source
        $newSource = $this->sourceService->create([
            'name' => 'Seminar / Exhibition',
            'is_active' => 1,
        ]);

        $this->assertSame('Seminar / Exhibition', $newSource['name']);
        $this->assertSame(1, (int)$newSource['is_active']);

        // Toggle to inactive
        $updated = $this->sourceService->update($newSource['id'], ['is_active' => 0]);
        $this->assertSame(0, (int)$updated['is_active']);
    }
}
