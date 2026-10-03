<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Session;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PermissionService;
use App\Services\TrainingInstituteService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TrainingInstituteTest extends TestCase
{
    private PDO $pdo;
    private TrainingInstituteService $trainingService;

    private int $adminId;
    private int $trainer1Id;
    private int $trainer2Id;
    private int $counselorId;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();

        $this->pdo = DatabaseResetter::reset(true) ?? Database::getConnection();
        $this->trainingService = new TrainingInstituteService();

        // Retrieve or create users
        $admin = $this->pdo->query("SELECT id FROM users WHERE email = 'admin@crm.local'")->fetch();
        $this->adminId = $admin ? (int)$admin['id'] : 1;

        // Trainer 1
        $t1 = $this->pdo->query("SELECT id FROM users WHERE email = 'trainer@crm.local'")->fetch();
        if (!$t1) {
            $tRoleId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'trainer'")->fetchColumn();
            $stmt = $this->pdo->prepare("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES (?, 'Faculty 1', 'trainer@crm.local', 'hash', 1)");
            $stmt->execute([$tRoleId]);
            $this->trainer1Id = (int)$this->pdo->lastInsertId();
        } else {
            $this->trainer1Id = (int)$t1['id'];
        }

        // Trainer 2
        $t2 = $this->pdo->query("SELECT id FROM users WHERE email = 'trainer2@crm.local'")->fetch();
        if (!$t2) {
            $tRoleId = (int)$this->pdo->query("SELECT id FROM roles WHERE name = 'trainer'")->fetchColumn();
            $stmt = $this->pdo->prepare("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES (?, 'Faculty 2', 'trainer2@crm.local', 'hash', 1)");
            $stmt->execute([$tRoleId]);
            $this->trainer2Id = (int)$this->pdo->lastInsertId();
        } else {
            $this->trainer2Id = (int)$t2['id'];
        }

        // Counselor
        $c = $this->pdo->query("SELECT id FROM users WHERE email = 'counselor@crm.local'")->fetch();
        $this->counselorId = $c ? (int)$c['id'] : 1;

        $this->setUser($this->adminId);
    }

    private function setUser(int $userId): void
    {
        Session::set('user_id', $userId);
        PermissionService::refresh();
    }

    public function testCourseCreationAndModulesSyllabus(): void
    {
        $code = 'CRS-TEST-' . substr((string)microtime(true), -5);
        $course = $this->trainingService->createCourse([
            'name' => 'Full Stack Accounting & GST Mastery',
            'course_code' => $code,
            'duration_weeks' => 8,
            'duration' => '8 Weeks Intensive',
            'fee' => 12000.00,
            'modules' => [
                'Module 1: Principles of Accounting',
                'Module 2: Tally Prime with GST',
                'Module 3: Direct Tax & ITR',
                'Module 4: Practical Filing & Auditing'
            ]
        ]);

        $this->assertNotEmpty($course['id']);
        $this->assertSame('Full Stack Accounting & GST Mastery', $course['name']);
        $this->assertEquals(12000.00, (float)$course['fee']);
        $this->assertSame('8 Weeks Intensive', $course['duration']);
        $this->assertCount(4, $course['modules']);
        $this->assertSame('Module 1: Principles of Accounting', $course['modules'][0]);
    }

    public function testBatchCreationAndCapacityEnforcement(): void
    {
        $course = $this->trainingService->createCourse([
            'name' => 'Mini Course',
            'course_code' => 'CRS-MINI-' . substr((string)microtime(true), -4),
            'fee' => 5000.00,
            'modules' => ['Module 1', 'Module 2']
        ]);

        $batch = $this->trainingService->createBatch([
            'course_id' => $course['id'],
            'name' => 'Weekday Batch',
            'start_date' => date('Y-m-d'),
            'timing' => '10:00 AM - 12:00 PM',
            'days' => 'Mon, Wed, Fri',
            'trainer_id' => $this->trainer1Id,
            'capacity' => 2,
            'status' => 'active'
        ]);

        $this->assertNotEmpty($batch['id']);
        $this->assertSame($this->trainer1Id, (int)$batch['trainer_id']);
        $this->assertEquals(2, (int)$batch['capacity']);

        // Admit 2 students to fill capacity
        $this->trainingService->admitStudent([
            'name' => 'Student One',
            'mobile' => '9900011101',
            'course_id' => $course['id'],
            'batch_id' => $batch['id'],
        ]);

        $this->trainingService->admitStudent([
            'name' => 'Student Two',
            'mobile' => '9900011102',
            'course_id' => $course['id'],
            'batch_id' => $batch['id'],
        ]);

        // 3rd admission must fail due to capacity limit
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('capacity limit');

        $this->trainingService->admitStudent([
            'name' => 'Student Three',
            'mobile' => '9900011103',
            'course_id' => $course['id'],
            'batch_id' => $batch['id'],
        ]);
    }

    public function testStudentAdmissionAndPhase4InvoiceFeePlan(): void
    {
        $course = $this->trainingService->createCourse([
            'name' => 'ROC Filing Diploma',
            'course_code' => 'CRS-ROC-' . substr((string)microtime(true), -4),
            'fee' => 10000.00,
            'modules' => ['Module 1: SPICe+', 'Module 2: Annual Filing']
        ]);

        $batch = $this->trainingService->createBatch([
            'course_id' => $course['id'],
            'name' => 'Weekend Cohort',
            'start_date' => date('Y-m-d'),
            'trainer_id' => $this->trainer1Id,
            'capacity' => 30,
        ]);

        // Admit student with discount and installment plan
        $admission = $this->trainingService->admitStudent([
            'name' => 'Deepak Chopra',
            'mobile' => '9900022201',
            'email' => 'deepak@test.local',
            'course_id' => $course['id'],
            'batch_id' => $batch['id'],
            'agreed_fee' => 10000.00,
            'discount_amount' => 1000.00,
            'create_invoice' => true,
            'installments' => [
                ['installment_no' => 1, 'amount' => 4500.00, 'due_date' => date('Y-m-d')],
                ['installment_no' => 2, 'amount' => 4500.00, 'due_date' => date('Y-m-d', strtotime('+30 days'))],
            ]
        ], $this->adminId);

        $this->assertNotEmpty($admission['id']);
        $this->assertNotEmpty($admission['invoice_id']);
        $this->assertEquals(9000.00, (float)$admission['invoice_total']);
        $this->assertEquals(9000.00, (float)$admission['invoice_balance']);
        $this->assertSame('active', $admission['status']);

        // Check module progress auto initialization
        $progress = $this->trainingService->getEnrollmentProgress((int)$admission['id']);
        $this->assertCount(2, $progress['modules']);
        $this->assertFalse($progress['certificate_ready']);
    }

    public function testDailyAttendanceAndPercentageCalculation(): void
    {
        $course = $this->trainingService->createCourse([
            'name' => 'Attendance Course',
            'course_code' => 'CRS-ATT-' . substr((string)microtime(true), -4),
            'fee' => 4000.00,
            'modules' => ['Module A']
        ]);

        $batch = $this->trainingService->createBatch([
            'course_id' => $course['id'],
            'name' => 'Roll Call Batch',
            'start_date' => date('Y-m-d'),
            'trainer_id' => $this->trainer1Id,
            'capacity' => 10,
        ]);

        $st1 = $this->trainingService->admitStudent([
            'name' => 'Aarav Kumar',
            'mobile' => '9900033301',
            'course_id' => $course['id'],
            'batch_id' => $batch['id'],
        ]);

        $st2 = $this->trainingService->admitStudent([
            'name' => 'Bhavna Sen',
            'mobile' => '9900033302',
            'course_id' => $course['id'],
            'batch_id' => $batch['id'],
        ]);

        $s1Id = (int)$st1['student_id'];
        $s2Id = (int)$st2['student_id'];
        $bId = (int)$batch['id'];

        // Mark Session 1: Both present
        $this->trainingService->markAttendance($bId, '2026-10-01', [
            $s1Id => 'present',
            $s2Id => 'present'
        ], $this->trainer1Id);

        // Mark Session 2: S1 late, S2 absent
        $this->trainingService->markAttendance($bId, '2026-10-02', [
            $s1Id => ['status' => 'late', 'remarks' => 'Bus delay'],
            $s2Id => ['status' => 'absent', 'remarks' => 'Fever']
        ], $this->trainer1Id);

        // Mark Session 3: S1 present, S2 absent
        $this->trainingService->markAttendance($bId, '2026-10-03', [
            $s1Id => 'present',
            $s2Id => 'absent'
        ], $this->trainer1Id);

        // S1: 2 present, 1 late, 0 absent out of 3 = 100.0%
        $s1Stats = $this->trainingService->getStudentAttendanceStats($s1Id, $bId);
        $this->assertSame(3, $s1Stats['total']);
        $this->assertSame(2, $s1Stats['present']);
        $this->assertSame(1, $s1Stats['late']);
        $this->assertEquals(100.0, $s1Stats['percentage']);

        // S2: 1 present, 0 late, 2 absent out of 3 = 33.3%
        $s2Stats = $this->trainingService->getStudentAttendanceStats($s2Id, $bId);
        $this->assertSame(3, $s2Stats['total']);
        $this->assertSame(1, $s2Stats['present']);
        $this->assertSame(2, $s2Stats['absent']);
        $this->assertEquals(33.3, $s2Stats['percentage']);

        // Monthly Register Sheet
        $monthly = $this->trainingService->getMonthlyAttendanceSheet($bId, '2026-10', $this->trainer1Id);
        $this->assertCount(3, $monthly['dates']);
        $this->assertCount(2, $monthly['students']);
    }

    public function testStudentModuleProgressAndCertificateReadiness(): void
    {
        $course = $this->trainingService->createCourse([
            'name' => 'Certification Track',
            'course_code' => 'CRS-CERT-' . substr((string)microtime(true), -4),
            'fee' => 6000.00,
            'modules' => ['Module 1', 'Module 2']
        ]);

        $batch = $this->trainingService->createBatch([
            'course_id' => $course['id'],
            'name' => 'Graduation Batch',
            'start_date' => date('Y-m-d'),
            'trainer_id' => $this->trainer1Id,
        ]);

        $adm = $this->trainingService->admitStudent([
            'name' => 'Kavita Roy',
            'mobile' => '9900044401',
            'course_id' => $course['id'],
            'batch_id' => $batch['id'],
        ]);

        $enrId = (int)$adm['id'];

        // Complete Module 1
        $res1 = $this->trainingService->updateProgress($enrId, 'Module 1', [
            'status' => 'completed',
            'test_score' => 95.0,
            'trainer_remarks' => 'Good work'
        ], $this->trainer1Id);

        $this->assertFalse($res1['certificate_ready']);

        // Complete Module 2
        $res2 = $this->trainingService->updateProgress($enrId, 'Module 2', [
            'status' => 'completed',
            'test_score' => 98.0,
            'trainer_remarks' => 'Excellent'
        ], $this->trainer1Id);

        $this->assertTrue($res2['certificate_ready']);

        // Issue Certificate
        $certified = $this->trainingService->issueCertificate($enrId, 'CERT-2026-TEST-99');
        $this->assertSame('CERT-2026-TEST-99', $certified['certificate_no']);
        $this->assertSame('completed', $certified['status']);
    }

    public function testTrainerBatchScopingAndFinancialRedaction(): void
    {
        $course = $this->trainingService->createCourse([
            'name' => 'Scoping Course',
            'course_code' => 'CRS-SCP-' . substr((string)microtime(true), -4),
            'fee' => 8000.00,
            'modules' => ['Core Module']
        ]);

        // Batch 1 for Trainer 1
        $b1 = $this->trainingService->createBatch([
            'course_id' => $course['id'],
            'name' => 'Trainer 1 Batch',
            'start_date' => date('Y-m-d'),
            'trainer_id' => $this->trainer1Id,
        ]);

        // Batch 2 for Trainer 2
        $b2 = $this->trainingService->createBatch([
            'course_id' => $course['id'],
            'name' => 'Trainer 2 Batch',
            'start_date' => date('Y-m-d'),
            'trainer_id' => $this->trainer2Id,
        ]);

        $st1 = $this->trainingService->admitStudent([
            'name' => 'Trainer 1 Student',
            'mobile' => '9900055501',
            'course_id' => $course['id'],
            'batch_id' => $b1['id'],
            'agreed_fee' => 8000.00,
            'create_invoice' => true,
        ]);

        $st2 = $this->trainingService->admitStudent([
            'name' => 'Trainer 2 Student',
            'mobile' => '9900055502',
            'course_id' => $course['id'],
            'batch_id' => $b2['id'],
            'agreed_fee' => 8000.00,
            'create_invoice' => true,
        ]);

        // 1. Trainer 1 cannot access Batch 2
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $this->trainingService->getBatch((int)$b2['id'], $this->trainer1Id);
    }

    public function testTrainerProfileRedactsFinancialData(): void
    {
        $course = $this->trainingService->createCourse([
            'name' => 'Redaction Test Course',
            'course_code' => 'CRS-RED-' . substr((string)microtime(true), -4),
            'fee' => 9000.00,
            'modules' => ['Module A']
        ]);

        $b = $this->trainingService->createBatch([
            'course_id' => $course['id'],
            'name' => 'Redaction Batch',
            'start_date' => date('Y-m-d'),
            'trainer_id' => $this->trainer1Id,
        ]);

        $st = $this->trainingService->admitStudent([
            'name' => 'Finance Protected Student',
            'mobile' => '9900066601',
            'course_id' => $course['id'],
            'batch_id' => $b['id'],
            'agreed_fee' => 9000.00,
            'create_invoice' => true,
        ]);

        $sId = (int)$st['student_id'];

        // Viewed as Trainer (canViewFinancials = false)
        $trainerProfile = $this->trainingService->getStudentProfile($sId, $this->trainer1Id, false);
        $this->assertFalse($trainerProfile['financial_access']);
        $this->assertNull($trainerProfile['financials']);
        $this->assertArrayNotHasKey('invoice_balance', $trainerProfile['enrollments'][0]);

        // Viewed as Admin (canViewFinancials = true)
        $adminProfile = $this->trainingService->getStudentProfile($sId, null, true);
        $this->assertTrue($adminProfile['financial_access']);
        $this->assertNotNull($adminProfile['financials']);
        $this->assertEquals(9000.00, (float)$adminProfile['financials']['fees_due']);
    }
}
