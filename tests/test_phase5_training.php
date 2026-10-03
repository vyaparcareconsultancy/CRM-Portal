<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Session;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PermissionService;
use App\Services\TrainingInstituteService;

Session::start();

// Ensure database connection
$pdo = Database::getConnection();

function setUser(PDO $pdo, string $roleName): int
{
    $stmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = ? AND u.is_active = 1 LIMIT 1");
    $stmt->execute([$roleName]);
    $user = $stmt->fetch();
    $uid = $user ? (int)$user['id'] : 1;
    Session::set('user_id', $uid);
    PermissionService::refresh();
    return $uid;
}

$adminId = setUser($pdo, 'admin');

echo "========================================\n";
echo "Testing Phase 5: Training Institute\n";
echo "========================================\n\n";

function assertTest(bool $condition, string $message): void {
    if ($condition) {
        echo "PASS: {$message}\n";
    } else {
        echo "FAIL: {$message}\n";
        exit(1);
    }
}

$service = new TrainingInstituteService();

// --- 1. Testing Courses Catalog & Modules ---
echo "--- 1. Testing Courses Catalog & Modules ---\n";

$testCourseCode = 'CRS-TDS-' . time();
$courseData = [
    'name' => 'Corporate Taxation & TDS Certification',
    'course_code' => $testCourseCode,
    'duration_weeks' => 5,
    'duration' => '5 Weeks (Weekend Intensive)',
    'fee' => 7500.00,
    'modules' => [
        'Module 1: TDS Concepts & Thresholds under Sec 192-194',
        'Module 2: Deduction, Deposit & Challan 281 Generation',
        'Module 3: Quarterly Returns 24Q, 26Q & 27Q Filing',
        'Module 4: Form 16/16A Generation via TRACES Portal',
        'Module 5: Practical Assessment & Case Studies'
    ]
];

$course = $service->createCourse($courseData);
assertTest(!empty($course['id']), "Course created successfully with ID {$course['id']}");
assertTest($course['name'] === 'Corporate Taxation & TDS Certification', "Course name matches");
assertTest((float)$course['fee'] === 7500.00, "Course fee matches ₹7,500.00");
assertTest($course['duration'] === '5 Weeks (Weekend Intensive)', "Course duration label matches");
assertTest(is_array($course['modules']) && count($course['modules']) === 5, "Syllabus modules list parsed into 5 modules");
assertTest($course['modules'][0] === 'Module 1: TDS Concepts & Thresholds under Sec 192-194', "Module 1 title matches");

// --- 2. Setting Up Trainers & Batches ---
echo "\n--- 2. Setting Up Trainers & Batches ---\n";

// Find or create test trainer 1 and trainer 2
$userModel = new User();
$trainer1 = $userModel->findByEmail('trainer1@crm.local');
if (!$trainer1) {
    $rStmt = $pdo->prepare("SELECT `id` FROM `roles` WHERE `name` = 'trainer' LIMIT 1");
    $rStmt->execute();
    $trainerRoleId = (int)$rStmt->fetchColumn();

    $t1Id = $userModel->create([
        'name' => 'Faculty Vikram',
        'email' => 'trainer1@crm.local',
        'password_hash' => password_hash('Trainer@123456', PASSWORD_BCRYPT),
        'role_id' => $trainerRoleId,
        'is_active' => 1,
    ]);
    $trainer1 = $userModel->find($t1Id);
}

$trainer2 = $userModel->findByEmail('trainer2@crm.local');
if (!$trainer2) {
    $rStmt = $pdo->prepare("SELECT `id` FROM `roles` WHERE `name` = 'trainer' LIMIT 1");
    $rStmt->execute();
    $trainerRoleId = (int)$rStmt->fetchColumn();

    $t2Id = $userModel->create([
        'name' => 'Faculty Priya',
        'email' => 'trainer2@crm.local',
        'password_hash' => password_hash('Trainer@123456', PASSWORD_BCRYPT),
        'role_id' => $trainerRoleId,
        'is_active' => 1,
    ]);
    $trainer2 = $userModel->find($t2Id);
}

$trainer1Id = (int)$trainer1['id'];
$trainer2Id = (int)$trainer2['id'];

// Create Batch 1 assigned to Trainer 1
$batch1 = $service->createBatch([
    'course_id' => $course['id'],
    'name' => 'TDS Morning Cohort Alpha',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+35 days')),
    'timing' => '07:30 AM - 09:30 AM',
    'days' => 'Mon, Wed, Fri',
    'trainer_id' => $trainer1Id,
    'capacity' => 2, // low capacity to test limit
    'status' => 'active'
]);

assertTest(!empty($batch1['id']), "Batch 1 created successfully");
assertTest(str_starts_with($batch1['batch_code'], 'BAT-'), "Batch code generated with BAT- prefix");
assertTest((int)$batch1['trainer_id'] === $trainer1Id, "Trainer 1 assigned to Batch 1");
assertTest((int)$batch1['capacity'] === 2, "Batch capacity set to 2");

// Create Batch 2 assigned to Trainer 2
$batch2 = $service->createBatch([
    'course_id' => $course['id'],
    'name' => 'TDS Evening Cohort Beta',
    'start_date' => date('Y-m-d'),
    'timing' => '06:00 PM - 08:00 PM',
    'days' => 'Tue, Thu, Sat',
    'trainer_id' => $trainer2Id,
    'capacity' => 10,
    'status' => 'active'
]);

assertTest(!empty($batch2['id']), "Batch 2 created successfully for Trainer 2");

// --- 3. Testing Admissions & Fee Plan Linking (Phase 4 Invoice) ---
echo "\n--- 3. Testing Admissions & Fee Plan Linking ---\n";

$mobileSuffix = substr((string)time(), -5);
$amitMobile = '98' . $mobileSuffix . '1';
$nehaMobile = '98' . $mobileSuffix . '2';
$rohanMobile = '98' . $mobileSuffix . '3';

// Admission 1: Direct New Student with 2 Installments
$adm1 = $service->admitStudent([
    'name' => 'Amit Verma',
    'mobile' => $amitMobile,
    'email' => 'amit.' . $mobileSuffix . '@test.local',
    'qualification' => 'B.Com Graduate',
    'course_id' => $course['id'],
    'batch_id' => $batch1['id'],
    'admission_date' => date('Y-m-d'),
    'agreed_fee' => 7500.00,
    'discount_amount' => 500.00, // net = 7000.00
    'gst_rate_pct' => 0.00,
    'create_invoice' => true,
    'installments' => [
        ['installment_no' => 1, 'amount' => 3500.00, 'due_date' => date('Y-m-d')],
        ['installment_no' => 2, 'amount' => 3500.00, 'due_date' => date('Y-m-d', strtotime('+30 days'))],
    ]
]);

assertTest(!empty($adm1['id']), "Student 1 admitted and enrolled");
assertTest(str_starts_with($adm1['enrollment_no'], 'ENR-'), "Enrollment number generated with ENR- prefix");
assertTest(!empty($adm1['invoice_id']), "Phase 4 invoice successfully generated and linked to enrollment");
assertTest((float)$adm1['invoice_total'] === 7000.00, "Invoice net payable correctly calculated as ₹7,000.00 (7500 - 500)");
assertTest((float)$adm1['invoice_balance'] === 7000.00, "Initial invoice balance due equals ₹7,000.00");
assertTest($adm1['status'] === 'active', "Enrollment status is 'active'");

$student1Id = (int)$adm1['student_id'];
$enrollment1Id = (int)$adm1['id'];

// Check that student_module_progress was automatically initialized
$progress1 = $service->getEnrollmentProgress($enrollment1Id);
assertTest(count($progress1['modules']) === 5, "Student 1 module progress automatically initialized with 5 course modules");
assertTest($progress1['modules'][0]['status'] === 'not_started', "Module 1 initial status is 'not_started'");
assertTest($progress1['certificate_ready'] === false, "Student 1 certificate_ready is false at start");

// Admission 2: From Inbound Lead into Batch 1 (filling capacity)
$leadModel = new Lead();
$leadId = $leadModel->create([
    'lead_code' => 'LD-' . $mobileSuffix . '-99',
    'name' => 'Neha Gupta',
    'mobile' => $nehaMobile,
    'email' => 'neha.' . $mobileSuffix . '@test.local',
    'interest_type' => 'course',
    'interested_in' => 'Corporate Taxation & TDS Certification',
    'status' => 'interested'
]);

$adm2 = $service->admitStudent([
    'lead_id' => $leadId,
    'course_id' => $course['id'],
    'batch_id' => $batch1['id'],
    'admission_date' => date('Y-m-d'),
    'agreed_fee' => 7500.00,
    'create_invoice' => true,
]);

assertTest(!empty($adm2['id']), "Student 2 admitted from Lead");
$refreshedLead = $leadModel->find($leadId);
assertTest($refreshedLead['status'] === 'converted', "Inbound Lead status transitioned to 'converted'");
assertTest((int)$refreshedLead['converted_student_id'] === (int)$adm2['student_id'], "Lead linked to converted student ID");

$student2Id = (int)$adm2['student_id'];
$enrollment2Id = (int)$adm2['id'];

// Admission 3: Try to admit 3rd student into Batch 1 (capacity was 2)
$capacityBlocked = false;
try {
    $service->admitStudent([
        'name' => 'Rohan Mehta',
        'mobile' => $rohanMobile,
        'course_id' => $course['id'],
        'batch_id' => $batch1['id'],
    ]);
} catch (RuntimeException $e) {
    $capacityBlocked = true;
}
assertTest($capacityBlocked, "Admitting past batch capacity (2 seats) is rejected with error");

// Admit Rohan into Batch 2 instead
$adm3 = $service->admitStudent([
    'name' => 'Rohan Mehta',
    'mobile' => $rohanMobile,
    'course_id' => $course['id'],
    'batch_id' => $batch2['id'],
    'agreed_fee' => 7500.00,
]);
assertTest(!empty($adm3['id']), "Rohan admitted to Batch 2");
$student3Id = (int)$adm3['student_id'];

// --- 4. Testing Attendance Marking & Attendance % ---
echo "\n--- 4. Testing Attendance Marking & Attendance % ---\n";

// Day 1: Trainer 1 marks attendance for Batch 1 (Amit present, Neha present)
$day1 = '2026-10-01';
$res1 = $service->markAttendance((int)$batch1['id'], $day1, [
    $student1Id => 'present',
    $student2Id => 'present',
], $trainer1Id);
assertTest($res1['marked_count'] === 2, "Trainer 1 marked 2 students present for Day 1");

// Day 2: Amit Late (with remarks), Neha Absent
$day2 = '2026-10-03';
$service->markAttendance((int)$batch1['id'], $day2, [
    $student1Id => ['status' => 'late', 'remarks' => 'Traffic delay 20 mins'],
    $student2Id => ['status' => 'absent', 'remarks' => 'Sick leave'],
], $trainer1Id);

// Day 3: Amit Present, Neha Present
$day3 = '2026-10-05';
$service->markAttendance((int)$batch1['id'], $day3, [
    $student1Id => 'present',
    $student2Id => 'present',
], $trainer1Id);

// Day 4: Amit Present, Neha Absent
$day4 = '2026-10-07';
$service->markAttendance((int)$batch1['id'], $day4, [
    $student1Id => 'present',
    $student2Id => 'absent',
], $trainer1Id);

// Verify Amit stats: 3 present, 1 late, 0 absent out of 4 sessions = (3+1)/4 * 100 = 100.0%
$amitStats = $service->getStudentAttendanceStats($student1Id, (int)$batch1['id']);
assertTest($amitStats['total'] === 4, "Amit total sessions = 4");
assertTest($amitStats['present'] === 3, "Amit present = 3");
assertTest($amitStats['late'] === 1, "Amit late = 1");
assertTest($amitStats['percentage'] === 100.0, "Amit attendance rate is 100.0% (Present + Late)");

// Verify Neha stats: 2 present, 0 late, 2 absent out of 4 sessions = 2/4 * 100 = 50.0%
$nehaStats = $service->getStudentAttendanceStats($student2Id, (int)$batch1['id']);
assertTest($nehaStats['total'] === 4, "Neha total sessions = 4");
assertTest($nehaStats['present'] === 2, "Neha present = 2");
assertTest($nehaStats['absent'] === 2, "Neha absent = 2");
assertTest($nehaStats['percentage'] === 50.0, "Neha attendance rate accurately computed as 50.0%");

// Monthly Sheet Matrix
$sheet = $service->getMonthlyAttendanceSheet((int)$batch1['id'], '2026-10', $trainer1Id);
assertTest(count($sheet['dates']) === 4, "Monthly sheet lists 4 session dates in Oct 2026");
assertTest(count($sheet['students']) === 2, "Monthly sheet contains both enrolled students");
assertTest($sheet['students'][0]['summary']['total'] === 4, "Student monthly summary contains total sessions");

// --- 5. Testing Module Progress & Certificate Readiness ---
echo "\n--- 5. Testing Module Progress & Certificate Readiness ---\n";

// Update Amit's modules sequentially
// Module 1: Completed with test score 92.5 and remarks
$progRes1 = $service->updateProgress(
    $enrollment1Id,
    'Module 1: TDS Concepts & Thresholds under Sec 192-194',
    [
        'status' => 'completed',
        'test_score' => 92.5,
        'trainer_remarks' => 'Excellent grasp of sections 194C, 194J, 194Q'
    ],
    $trainer1Id
);
assertTest($progRes1['certificate_ready'] === false, "Certificate ready is false with only 1 module completed");

// Complete Modules 2, 3, 4
$service->updateProgress($enrollment1Id, 'Module 2: Deduction, Deposit & Challan 281 Generation', ['status' => 'completed', 'test_score' => 88.0], $trainer1Id);
$service->updateProgress($enrollment1Id, 'Module 3: Quarterly Returns 24Q, 26Q & 27Q Filing', ['status' => 'completed', 'test_score' => 95.0], $trainer1Id);
$service->updateProgress($enrollment1Id, 'Module 4: Form 16/16A Generation via TRACES Portal', ['status' => 'completed', 'test_score' => 90.0], $trainer1Id);

$midProg = $service->getEnrollmentProgress($enrollment1Id, $trainer1Id);
assertTest($midProg['certificate_ready'] === false, "Certificate ready is still false with 4/5 modules completed");

// Complete Final Module 5
$finalProg = $service->updateProgress(
    $enrollment1Id,
    'Module 5: Practical Assessment & Case Studies',
    ['status' => 'completed', 'test_score' => 96.0, 'trainer_remarks' => 'Outstanding practical performance'],
    $trainer1Id
);

assertTest($finalProg['certificate_ready'] === true, "Certificate ready flag automatically set to true when all 5 modules completed");

// Issue certificate
$certified = $service->issueCertificate($enrollment1Id, 'CERT-2026-TDS-001');
assertTest($certified['certificate_no'] === 'CERT-2026-TDS-001', "Certificate number recorded");
assertTest($certified['status'] === 'completed', "Enrollment status transitioned to 'completed'");

// Verify that incomplete student cannot be certified
$unreadyCertBlocked = false;
try {
    $service->issueCertificate($enrollment2Id, 'CERT-INVALID');
} catch (RuntimeException $e) {
    $unreadyCertBlocked = true;
}
assertTest($unreadyCertBlocked, "Cannot issue certificate to student whose modules are not all completed");

// --- 6. Testing Trainer Role Scoping & Financial Redaction ---
echo "\n--- 6. Testing Trainer Role Scoping & Financial Redaction ---\n";

// Trainer 1 tries to view Trainer 2's batch -> Must throw 403 Forbidden
$trainer1BlockedFromBatch2 = false;
try {
    $service->getBatch((int)$batch2['id'], $trainer1Id);
} catch (RuntimeException $e) {
    if ($e->getCode() === 403) {
        $trainer1BlockedFromBatch2 = true;
    }
}
assertTest($trainer1BlockedFromBatch2, "Trainer 1 cannot access Batch 2 assigned to Trainer 2 (403 Forbidden)");

// Trainer 1 tries to mark attendance for Batch 2 -> 403 Forbidden
$trainer1BlockedFromAtt = false;
try {
    $service->markAttendance((int)$batch2['id'], '2026-10-01', [$student3Id => 'present'], $trainer1Id);
} catch (RuntimeException $e) {
    if ($e->getCode() === 403) {
        $trainer1BlockedFromAtt = true;
    }
}
assertTest($trainer1BlockedFromAtt, "Trainer 1 cannot mark attendance for Trainer 2's batch (403 Forbidden)");

// Trainer 1 tries to view Student 3 profile (Student 3 is only in Batch 2) -> 403 Forbidden
$trainer1BlockedFromStudent3 = false;
try {
    $service->getStudentProfile($student3Id, $trainer1Id, false);
} catch (RuntimeException $e) {
    if ($e->getCode() === 403) {
        $trainer1BlockedFromStudent3 = true;
    }
}
assertTest($trainer1BlockedFromStudent3, "Trainer 1 cannot view student profile from another trainer's batch (403 Forbidden)");

// Student Profile viewed by Trainer 1 (for student in own batch):
// Trainer sees course, batch, attendance %, progress, BUT fees due is strictly redacted!
$trainerStudentProfile = $service->getStudentProfile($student1Id, $trainer1Id, false);
assertTest($trainerStudentProfile['student']['name'] === 'Amit Verma', "Trainer can view profile of student in own batch");
assertTest(isset($trainerStudentProfile['attendance']['percentage']), "Trainer sees attendance percentage");
assertTest(isset($trainerStudentProfile['enrollments'][0]['progress']), "Trainer sees module progress");
assertTest($trainerStudentProfile['financial_access'] === false, "Financial access flag is false for Trainer");
assertTest($trainerStudentProfile['financials'] === null, "Fees due / financials is null (redacted) for Trainer");
assertTest(!isset($trainerStudentProfile['enrollments'][0]['invoice_balance']), "Invoice balance redacted from enrollment for Trainer");

// Student Profile viewed by Admin/Accountant:
// Sees course, batch, attendance %, progress, AND full fees due & payment totals!
$adminStudentProfile = $service->getStudentProfile($student1Id, null, true);
assertTest($adminStudentProfile['financial_access'] === true, "Financial access flag is true for Admin");
assertTest(isset($adminStudentProfile['financials']['fees_due']), "Fees due is visible for Admin");
assertTest((float)$adminStudentProfile['financials']['fees_due'] === 7000.00, "Fees due accurately shows ₹7,000.00");
assertTest((float)$adminStudentProfile['financials']['total_invoiced'] === 7000.00, "Total invoiced shows ₹7,000.00");

// Record partial payment on the student invoice via Phase 4 InvoiceService
$paymentService = new \App\Services\PaymentService();
$payment = $paymentService->recordPayment([
    'invoice_id' => $adm1['invoice_id'],
    'amount' => 3500.00,
    'payment_mode' => 'upi',
    'payment_date' => date('Y-m-d'),
    'reference_no' => 'UPI-FEE-TEST-001'
]);

$adminProfileAfterPayment = $service->getStudentProfile($student1Id, null, true);
assertTest((float)$adminProfileAfterPayment['financials']['total_paid'] === 3500.00, "Paid fee updated to ₹3,500.00");
assertTest((float)$adminProfileAfterPayment['financials']['fees_due'] === 3500.00, "Fees due updated to ₹3,500.00 (7000 - 3500)");

echo "\n========================================\n";
echo "ALL PHASE 5 INTEGRATION CHECKS PASSED!\n";
echo "========================================\n";
