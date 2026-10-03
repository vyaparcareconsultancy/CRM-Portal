<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Services\DocumentService;
use App\Services\TimelineService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocumentTimelineTest extends TestCase
{
    private PDO $pdo;
    private DocumentService $docService;
    private TimelineService $timelineService;

    private int $adminId;
    private int $accountantId;
    private int $counselorId;
    private int $trainerId;

    private int $clientId;
    private int $leadId;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();

        $this->pdo = DatabaseResetter::reset(true) ?? Database::getConnection();
        $this->docService = new DocumentService();
        $this->timelineService = new TimelineService();

        // 1. Roles & Users setup
        $rolesStmt = $this->pdo->query("SELECT id, name FROM roles");
        $rolesMap = [];
        while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
            $rolesMap[$r['name']] = (int)$r['id'];
        }

        $this->adminId = $this->getOrCreateUser('admin_p6@crm.local', 'Admin User', $rolesMap['admin']);
        $this->accountantId = $this->getOrCreateUser('accountant_p6@crm.local', 'Accountant User', $rolesMap['accountant']);
        $this->counselorId = $this->getOrCreateUser('counselor_p6@crm.local', 'Rahul Counselor', $rolesMap['counselor']);
        $this->trainerId = $this->getOrCreateUser('trainer_p6@crm.local', 'Trainer User', $rolesMap['trainer']);

        // 2. Entities setup
        $suffix = bin2hex(random_bytes(3));
        $this->pdo->prepare("INSERT INTO clients (client_code, name, email, mobile, client_type, status) VALUES (?, ?, ?, ?, 'individual', 'active')")
            ->execute(["CL-DOC-{$suffix}", 'Test Client', "client_{$suffix}@crm.local", '98111' . random_int(10000, 99999)]);
        $this->clientId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO leads (lead_code, name, mobile, status, interested_in) VALUES (?, ?, ?, 'new', 'GST')")
            ->execute(["LD-DOC-{$suffix}", 'Test Lead', '98222' . random_int(10000, 99999)]);
        $this->leadId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO students (student_code, name, mobile, email, status) VALUES (?, ?, ?, ?, 'active')")
            ->execute(["ST-DOC-{$suffix}", 'Test Student', '98333' . random_int(10000, 99999), "student_{$suffix}@crm.local"]);
        $this->studentId = (int)$this->pdo->lastInsertId();
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
        $ins->execute([$roleId, $name, $email, password_hash('Pass12345678!', PASSWORD_BCRYPT)]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Test uploading sensitive files (PAN, Aadhaar) ensures encryption at rest on storage disk.
     */
    public function testSensitiveDocumentsAreEncryptedAtRest(): void
    {
        $plainContent = "SENSITIVE TAX AUDIT DATA 999999999";
        $doc = $this->docService->uploadDocument([
            'entity_type' => 'client',
            'entity_id' => $this->clientId,
            'document_type' => 'pan',
            'title' => 'Client PAN',
            'document_number' => 'ABCDE1234F',
            'financial_year' => '2025-26',
            'file_name' => 'pan.pdf',
        ], $plainContent, $this->adminId);

        $this->assertEquals(1, $doc['is_encrypted']);
        $this->assertEquals('ABCDE1234F', $doc['document_number']);

        $diskPath = dirname(__DIR__) . "/storage/uploads/documents/client/{$this->clientId}/" . $doc['stored_name'];
        $this->assertFileExists($diskPath);
        $rawBytes = file_get_contents($diskPath);

        // Raw bytes on disk must NOT equal plain content
        $this->assertNotEquals($plainContent, $rawBytes);

        // Download must decrypt on the fly
        $downloaded = $this->docService->downloadDocument($doc['id'], $this->adminId, 'admin');
        $this->assertEquals($plainContent, $downloaded['content']);
    }

    /**
     * Test Aadhaar masking: full number is never stored or returned; only last 4 digits.
     */
    public function testAadhaarNumberIsStrictlyMasked(): void
    {
        $rawNumber = "5555 6666 7777";
        $doc = $this->docService->uploadDocument([
            'entity_type' => 'student',
            'entity_id' => $this->studentId,
            'document_type' => 'aadhaar',
            'title' => 'Student ID Aadhaar',
            'document_number' => $rawNumber,
            'file_name' => 'aadhaar.pdf',
        ], "AADHAAR FILE CONTENT", $this->adminId);

        $this->assertEquals('XXXX-XXXX-7777', $doc['document_number']);
        $this->assertStringNotContainsString('5555', $doc['document_number']);
        $this->assertStringNotContainsString('6666', $doc['document_number']);
    }

    /**
     * Test Aadhaar private downloads: only Admin and Accountant have access; others get 403.
     */
    public function testAadhaarViewRestrictedToAdminAndAccountant(): void
    {
        $doc = $this->docService->uploadDocument([
            'entity_type' => 'student',
            'entity_id' => $this->studentId,
            'document_type' => 'aadhaar',
            'document_number' => '1234 5678 4321',
            'file_name' => 'aadhaar.pdf',
        ], "AADHAAR PROTECTED CONTENT", $this->adminId);

        // Admin can download
        $adminRes = $this->docService->downloadDocument($doc['id'], $this->adminId, 'admin');
        $this->assertEquals('AADHAAR PROTECTED CONTENT', $adminRes['content']);

        // Accountant can download
        $accRes = $this->docService->downloadDocument($doc['id'], $this->accountantId, 'accountant');
        $this->assertEquals('AADHAAR PROTECTED CONTENT', $accRes['content']);

        // Counselor gets 403 Forbidden
        try {
            $this->docService->downloadDocument($doc['id'], $this->counselorId, 'counselor');
            $this->fail('Counselor should not be allowed to access Aadhaar documents');
        } catch (RuntimeException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // Trainer gets 403 Forbidden
        try {
            $this->docService->downloadDocument($doc['id'], $this->trainerId, 'trainer');
            $this->fail('Trainer should not be allowed to access Aadhaar documents');
        } catch (RuntimeException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    /**
     * Test activity log audit for document downloads.
     */
    public function testDocumentDownloadIsAudited(): void
    {
        $doc = $this->docService->uploadDocument([
            'entity_type' => 'client',
            'entity_id' => $this->clientId,
            'document_type' => 'gst_certificate',
            'file_name' => 'gst.pdf',
        ], "GST CERTIFICATE", $this->adminId);

        $this->docService->downloadDocument($doc['id'], $this->accountantId, 'accountant');

        $stmt = $this->pdo->prepare("SELECT action, user_id FROM activity_log WHERE entity_type = 'document' AND entity_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$doc['id']]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($log);
        $this->assertEquals('document_downloaded', $log['action']);
        $this->assertEquals($this->accountantId, (int)$log['user_id']);
    }

    /**
     * Test Quick Add Note with @mention generates in-app notifications.
     */
    public function testAddNoteWithMentionCreatesNotification(): void
    {
        $note = $this->timelineService->addNote(
            'client',
            $this->clientId,
            $this->adminId,
            "Urgent: @Rahul please follow up with client for ITR acknowledgement",
            true
        );

        $this->assertTrue($note['is_pinned']);
        $this->assertContains($this->counselorId, $note['mentioned_users']);

        $notifs = $this->timelineService->getUnreadNotifications($this->counselorId);
        $this->assertNotEmpty($notifs);
        $this->assertEquals('mention', $notifs[0]['type']);
        $this->assertStringContainsString('Admin User mentioned you', $notifs[0]['title']);

        // Mark read
        $this->timelineService->markNotificationAsRead($notifs[0]['id'], $this->counselorId);
        $unread = $this->timelineService->getUnreadNotifications($this->counselorId);
        $this->assertEmpty($unread);
    }

    /**
     * Test Unified Timeline aggregates events and supports type filtering.
     */
    public function testUnifiedTimelineAggregationAndFiltering(): void
    {
        // 1. Add Note
        $this->timelineService->addNote('client', $this->clientId, $this->adminId, "First client note", false);

        // 2. Add Document
        $this->docService->uploadDocument([
            'entity_type' => 'client',
            'entity_id' => $this->clientId,
            'document_type' => 'other',
            'file_name' => 'agreement.pdf',
        ], "AGREEMENT TEXT", $this->adminId);

        // 3. Add Follow-up
        $this->pdo->prepare("INSERT INTO follow_ups (client_id, type, status, outcome, remarks, due_at, user_id) VALUES (?, 'call', 'done', 'connected', 'Called regarding documents', NOW(), ?)")
            ->execute([$this->clientId, $this->adminId]);

        // 4. Add Invoice and Payment
        $suffix = bin2hex(random_bytes(2));
        $this->pdo->prepare("INSERT INTO invoices (invoice_no, client_id, title, total_amount, net_amount, issue_date, due_date, status) VALUES (?, ?, 'Consulting', 2000.00, 2000.00, CURDATE(), CURDATE(), 'paid')")
            ->execute(["INV-UT-{$suffix}", $this->clientId]);
        $invId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO payments (receipt_no, invoice_id, amount, payment_date, payment_mode, received_by) VALUES (?, ?, 2000.00, CURDATE(), 'cash', ?)")
            ->execute(["REC-UT-{$suffix}", $invId, $this->accountantId]);

        // Aggregate All
        $all = $this->timelineService->getTimeline('client', $this->clientId, 'all');
        $this->assertGreaterThanOrEqual(4, count($all));

        // Filter: notes
        $notesOnly = $this->timelineService->getTimeline('client', $this->clientId, 'notes');
        $this->assertNotEmpty($notesOnly);
        foreach ($notesOnly as $item) {
            $this->assertEquals('note', $item['type']);
        }

        // Filter: calls
        $callsOnly = $this->timelineService->getTimeline('client', $this->clientId, 'call');
        $this->assertNotEmpty($callsOnly);
        foreach ($callsOnly as $item) {
            $this->assertEquals('call', $item['type']);
        }

        // Filter: payments
        $paymentsOnly = $this->timelineService->getTimeline('client', $this->clientId, 'payment');
        $this->assertNotEmpty($paymentsOnly);
        foreach ($paymentsOnly as $item) {
            $this->assertEquals('payment', $item['type']);
        }
    }
}
