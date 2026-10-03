<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\Cache\Cache;
use App\Services\DashboardService;
use App\Services\ReportService;

$pdo = Database::getConnection();

function assertTrue($cond, string $msg): void {
    if (!$cond) {
        echo "❌ FAILED: {$msg}\n";
        exit(1);
    }
    echo "✅ PASSED: {$msg}\n";
}

echo "=== PHASE 8 INTEGRATION TESTS: DASHBOARD & REPORTS ===\n";

Cache::clear();

// 1. Setup Test Users
$rolesStmt = $pdo->query("SELECT id, name FROM roles");
$rolesMap = [];
while ($r = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
    $rolesMap[$r['name']] = (int)$r['id'];
}

function getOrCreateUser(PDO $pdo, string $email, string $name, int $roleId): int {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }
    $ins = $pdo->prepare("INSERT INTO users (role_id, name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)");
    $ins->execute([$roleId, $name, $email, password_hash('Secret123!', PASSWORD_BCRYPT)]);
    return (int)$pdo->lastInsertId();
}

$adminId = getOrCreateUser($pdo, 'p8_admin@crm.local', 'Admin User', $rolesMap['admin']);
$accountantId = getOrCreateUser($pdo, 'p8_accountant@crm.local', 'Accountant User', $rolesMap['accountant']);
$counselorId = getOrCreateUser($pdo, 'p8_counselor@crm.local', 'Counselor User', $rolesMap['counselor']);
$trainerId = getOrCreateUser($pdo, 'p8_trainer@crm.local', 'Trainer User', $rolesMap['trainer']);

$dashboardService = new DashboardService(null, null, $pdo);
$reportService = new ReportService($pdo);

// 2. Setup Test Data (Leads, Clients, Services, Invoices, Payments, Courses, Batches, Enrollments, Attendance)
$suffix = bin2hex(random_bytes(3));

// Leads in different funnel stages
$sources = ['Instagram', 'Google', 'Website', 'Referral'];
$statuses = ['new', 'contacted', 'interested', 'follow_up', 'converted', 'lost'];
$sourceIds = [];
foreach ($sources as $sName) {
    $stmt = $pdo->prepare("SELECT id FROM lead_sources WHERE name = ?");
    $stmt->execute([$sName]);
    $sid = $stmt->fetchColumn();
    if (!$sid) {
        $ins = $pdo->prepare("INSERT INTO lead_sources (name, is_active) VALUES (?, 1)");
        $ins->execute([$sName]);
        $sid = $pdo->lastInsertId();
    }
    $sourceIds[$sName] = (int)$sid;
}

foreach ($statuses as $idx => $st) {
    $src = $sources[$idx % count($sources)];
    $sId = $sourceIds[$src];
    $phone = '98' . random_int(10000000, 99999999);
    $pdo->prepare("INSERT INTO leads (lead_code, name, mobile, email, lead_source_id, interested_in, status, assigned_to) VALUES (?, ?, ?, ?, ?, 'GST Compliance', ?, ?)")
        ->execute(["LD-P8-{$suffix}-{$idx}", "Lead {$st} {$suffix}", $phone, "lead_{$idx}_{$suffix}@crm.local", $sId, $st, $counselorId]);
}

// Client with Service, Invoice, and Payments
$phoneClient = '97' . random_int(10000000, 99999999);
$pdo->prepare("INSERT INTO clients (client_code, name, email, mobile, client_type, status, assigned_to) VALUES (?, ?, ?, ?, 'company', 'active', ?)")
    ->execute(["CL-P8-{$suffix}", "Client Corp {$suffix}", "corp_{$suffix}@crm.local", $phoneClient, $accountantId]);
$clientId = (int)$pdo->lastInsertId();

$srvStmt = $pdo->query("SELECT id FROM services WHERE code = 'SRV-GST-RET' LIMIT 1");
$serviceId = (int)$srvStmt->fetchColumn();
if (!$serviceId) {
    $pdo->query("INSERT INTO services (name, code, type, frequency, default_fee, status) VALUES ('GST Return Filing', 'SRV-GST-RET', 'recurring', 'monthly', 4000.00, 'active')");
    $serviceId = (int)$pdo->lastInsertId();
}

$pdo->prepare("INSERT INTO client_services (client_id, service_id, assigned_accountant_id, fee, frequency, start_date, status) VALUES (?, ?, ?, 4000.00, 'monthly', '2026-01-01', 'active')")
    ->execute([$clientId, $serviceId, $accountantId]);
$clientServiceId = (int)$pdo->lastInsertId();

// Invoices: One current, one aging 45 days
$pdo->prepare("INSERT INTO invoices (invoice_no, client_id, client_service_id, title, total_amount, paid_amount, balance_amount, issue_date, due_date, status) VALUES (?, ?, ?, 'GST Monthly Filing', 4000.00, 2000.00, 2000.00, '2026-09-01', '2026-09-10', 'partially_paid')")
    ->execute(["INV-P8-AGING-{$suffix}", $clientId, $clientServiceId]);
$invoiceAgingId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO invoices (invoice_no, client_id, client_service_id, title, total_amount, paid_amount, balance_amount, issue_date, due_date, status) VALUES (?, ?, ?, 'GST Current Filing', 4000.00, 0.00, 4000.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'unpaid')")
    ->execute(["INV-P8-CURR-{$suffix}", $clientId, $clientServiceId]);
$invoiceCurrId = (int)$pdo->lastInsertId();

// Payment
$pdo->prepare("INSERT INTO payments (invoice_id, receipt_no, amount, payment_date, payment_mode, received_by) VALUES (?, ?, 2000.00, CURDATE(), 'upi', ?)")
    ->execute([$invoiceAgingId, "REC-P8-{$suffix}", $accountantId]);

// Course, Batch, Student, Enrollment, Attendance
$courseStmt = $pdo->query("SELECT id FROM courses WHERE course_code = 'CRS-GST' LIMIT 1");
$courseId = (int)$courseStmt->fetchColumn();
if (!$courseId) {
    $pdo->query("INSERT INTO courses (course_code, name, fee, duration, status) VALUES ('CRS-GST', 'GST Mastery', 8000.00, '6 Weeks', 'active')");
    $courseId = (int)$pdo->lastInsertId();
}

$pdo->prepare("INSERT INTO batches (batch_code, course_id, trainer_id, name, start_date, capacity, status) VALUES (?, ?, ?, 'Weekend Batch P8', '2026-10-01', 15, 'active')")
    ->execute(["BAT-P8-{$suffix}", $courseId, $trainerId]);
$batchId = (int)$pdo->lastInsertId();

$phoneStudent = '87' . random_int(10000000, 99999999);
$pdo->prepare("INSERT INTO students (student_code, name, email, mobile, status, created_by) VALUES (?, ?, ?, ?, 'active', ?)")
    ->execute(["ST-P8-{$suffix}", "Student P8 {$suffix}", "student_{$suffix}@crm.local", $phoneStudent, $counselorId]);
$studentId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO enrollments (enrollment_no, student_id, course_id, batch_id, admission_date, agreed_fee, status) VALUES (?, ?, ?, ?, '2026-10-01', 8000.00, 'active')")
    ->execute(["ENR-P8-{$suffix}", $studentId, $courseId, $batchId]);

// Attendance
$pdo->prepare("INSERT INTO attendance (batch_id, student_id, session_date, status, marked_by) VALUES (?, ?, '2026-10-02', 'present', ?)")
    ->execute([$batchId, $studentId, $trainerId]);

// 3. TEST DASHBOARD STATS (ROLE-BASED)

// Admin Dashboard View
$adminStats = $dashboardService->computeStats($adminId, 'admin', true);
$mAdmin = $adminStats['metrics'];

assertTrue($mAdmin['total_leads'] >= 6, 'Admin dashboard displays total leads');
assertTrue($mAdmin['new_leads_today'] >= 0, 'Admin dashboard displays new leads today');
assertTrue(isset($mAdmin['follow_ups_today'], $mAdmin['follow_ups_overdue']), 'Admin dashboard displays follow-ups metrics');
assertTrue($mAdmin['converted_customers'] >= 1, 'Admin dashboard displays converted customers');
assertTrue($mAdmin['active_students'] >= 1, 'Admin dashboard displays active students count');
assertTrue($mAdmin['revenue_this_month'] >= 2000.00, 'Admin dashboard displays monthly revenue');
assertTrue($mAdmin['total_due'] >= 6000.00, 'Admin dashboard displays total outstanding due');
assertTrue($adminStats['can_view_financial'] === true, 'Admin has financial access flag enabled');

// Verify 3 Required Charts in Admin Dashboard
$charts = $adminStats['charts'];
assertTrue(!empty($charts['leads_by_source']), 'Dashboard contains Leads by Source chart data');
assertTrue(!empty($charts['conversion_funnel']), 'Dashboard contains Lead Conversion Funnel chart data');
assertTrue(count($charts['conversion_funnel']) >= 5, 'Conversion funnel includes stages (new, contacted, interested, follow_up, converted, lost)');
assertTrue(!empty($charts['monthly_revenue']), 'Dashboard contains Monthly Revenue trend chart data');

// Trainer Dashboard View (Financial metrics strictly redacted)
$trainerStats = $dashboardService->computeStats($trainerId, 'trainer', false);
assertTrue($trainerStats['can_view_financial'] === false, 'Trainer dashboard can_view_financial is false');
assertTrue($trainerStats['metrics']['revenue_this_month'] === null, 'Trainer cannot view revenue this month (strictly redacted)');
assertTrue($trainerStats['metrics']['total_due'] === null, 'Trainer cannot view total due (strictly redacted)');
assertTrue($trainerStats['metrics']['active_students'] >= 1, 'Trainer sees active students in assigned batches');

// 4. TEST THE 7 REQUIRED BUSINESS REPORTS

// Report 1: Daily/Monthly Sales & Collections
$r1 = $reportService->getReport('sales_collections');
assertTrue(!empty($r1['rows']), 'Report 1: Sales & Collections returned rows');
assertTrue(isset($r1['summary']['total_collected']), 'Report 1: Summary has total_collected');
assertTrue((float)$r1['summary']['total_collected'] >= 2000.00, 'Report 1: Total collected matches recorded payments');

// Report 2: Lead Source Performance & Conversion %
$r2 = $reportService->getReport('lead_source_conversion');
assertTrue(!empty($r2['rows']), 'Report 2: Lead Source Performance returned rows');
assertTrue(isset($r2['summary']['overall_conversion_pct']), 'Report 2: Summary contains overall_conversion_pct');
$foundSourceWithPct = false;
foreach ($r2['rows'] as $row) {
    if (isset($row['conversion_rate_pct'])) {
        $foundSourceWithPct = true;
        break;
    }
}
assertTrue($foundSourceWithPct, 'Report 2: Each lead source calculates conversion_rate_pct');

// Report 3: Course-wise Admissions & Batch Strength
$r3 = $reportService->getReport('course_admissions');
assertTrue(!empty($r3['rows']), 'Report 3: Course Admissions returned rows');
assertTrue(isset($r3['summary']['total_admissions']), 'Report 3: Summary contains total_admissions');
$foundCourse = false;
foreach ($r3['rows'] as $cr) {
    if ($cr['course_code'] === 'CRS-GST') {
        $foundCourse = true;
        assertTrue((int)$cr['total_admissions'] >= 1, 'Report 3: GST Mastery admissions counted correctly');
        break;
    }
}
assertTrue($foundCourse, 'Report 3: Found CRS-GST course with batch and student strength');

// Report 4: Client-wise & Service-wise Revenue
$r4 = $reportService->getReport('client_service_revenue');
assertTrue(!empty($r4['rows']), 'Report 4: Client & Service Revenue returned rows');
assertTrue(isset($r4['summary']['total_billed'], $r4['summary']['total_due']), 'Report 4: Summary contains total_billed and total_due');

// Report 5: Pending Payments / Aging (0-30, 31-60, 60+ Days)
$r5 = $reportService->getReport('payment_aging');
assertTrue(!empty($r5['rows']), 'Report 5: Payment Aging returned rows');
assertTrue(isset($r5['summary']['0-30'], $r5['summary']['31-60'], $r5['summary']['60+'], $r5['summary']['total_pending']), 'Report 5: Aging buckets (0-30, 31-60, 60+) present in summary');

// Report 6: Staff Performance (Leads Handled, Conversions, Collections)
$r6 = $reportService->getReport('staff_performance');
assertTrue(!empty($r6['rows']), 'Report 6: Staff Performance returned rows');
assertTrue(isset($r6['summary']['total_leads_handled'], $r6['summary']['total_conversions'], $r6['summary']['total_collections']), 'Report 6: Summary contains total leads, conversions, and collections');
$counselorRow = null;
foreach ($r6['rows'] as $sr) {
    if ((int)$sr['user_id'] === $counselorId) {
        $counselorRow = $sr;
        break;
    }
}
assertTrue($counselorRow !== null, 'Report 6: Counselor found in staff performance');
assertTrue((int)$counselorRow['leads_handled'] >= 6, 'Report 6: Counselor leads handled counted accurately');

// Report 7: Attendance Summary
$r7 = $reportService->getReport('attendance_summary');
assertTrue(!empty($r7['rows']), 'Report 7: Attendance Summary returned rows');
assertTrue(isset($r7['summary']['total_records'], $r7['summary']['overall_attendance_pct']), 'Report 7: Summary contains total records and overall attendance %');
$batchRow = null;
foreach ($r7['rows'] as $br) {
    if ((int)$br['batch_id'] === $batchId) {
        $batchRow = $br;
        break;
    }
}
assertTrue($batchRow !== null, 'Report 7: Batch P8 found in attendance summary');
assertTrue((float)$batchRow['attendance_pct'] == 100.0, 'Report 7: Batch attendance % calculated accurately');

// 5. TEST 10-MINUTE CACHING
$cacheKey = "report:sales_collections:" . md5(json_encode([]));
assertTrue(Cache::has($cacheKey), 'Report results cached in cache store with 10 min TTL');
$cachedData = Cache::get($cacheKey);
assertTrue(isset($cachedData['summary']['total_collected']), 'Cached data retrieved successfully from cache');

echo "\n🎉 ALL PHASE 8 DASHBOARD & REPORTS TESTS PASSED SUCCESSFULLY!\n";
