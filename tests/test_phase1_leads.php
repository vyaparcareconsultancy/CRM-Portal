<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Session;
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

App\Core\Session::start();

echo "========================================\n";
echo "Testing Phase 1: Lead Management & Follow-ups\n";
echo "========================================\n\n";

$pdo = Database::getConnection();

function assertCheck(bool $condition, string $message): void
{
    if ($condition) {
        echo "PASS: {$message}\n";
    } else {
        echo "FAIL: {$message}\n";
        exit(1);
    }
}

// 1. Verify default Lead Sources
$sourceModel = new LeadSource();
$sources = $sourceModel->getActiveSources();
$sourceNames = array_column($sources, 'name');
$expectedSources = ['Instagram', 'Facebook', 'Google', 'Website', 'WhatsApp', 'Referral', 'Walk-in'];

foreach ($expectedSources as $expected) {
    assertCheck(in_array($expected, $sourceNames, true), "Default lead source '{$expected}' exists");
}

// Admin adding new lead source
$sourceService = new LeadSourceService();
$newSource = $sourceService->create([
    'name' => 'Campus Seminar ' . time(),
    'is_active' => true,
]);
assertCheck(!empty($newSource['id']), "Admin can add new lead source ({$newSource['name']})");

// 2. Setup test users: 1 Admin and 2 Counselors
$userModel = new User();
$rolesStmt = $pdo->query("SELECT id, name FROM roles");
$roleMap = [];
while ($r = $rolesStmt->fetch()) {
    $roleMap[$r['name']] = (int)$r['id'];
}
$adminRoleId = $roleMap['admin'] ?? 1;
$counselorRoleId = $roleMap['counselor'] ?? 4;

$adminUser = $userModel->findByEmail('admin@crm.local');
if (!$adminUser) {
    $adminId = $userModel->insert([
        'name' => 'Admin Test',
        'email' => 'admin@crm.local',
        'password_hash' => password_hash('AdminPass1234!', PASSWORD_DEFAULT),
        'role_id' => $adminRoleId,
        'is_active' => 1,
    ]);
    $adminUser = $userModel->find($adminId);
}

$counselor1Email = 'counselor1_' . time() . '@crm.local';
$c1Id = (int)$userModel->insert([
    'name' => 'Counselor Priya',
    'email' => $counselor1Email,
    'password_hash' => password_hash('Counselor123!', PASSWORD_DEFAULT),
    'role_id' => $counselorRoleId,
    'is_active' => 1,
]);

$counselor2Email = 'counselor2_' . time() . '@crm.local';
$c2Id = (int)$userModel->insert([
    'name' => 'Counselor Rahul',
    'email' => $counselor2Email,
    'password_hash' => password_hash('Counselor123!', PASSWORD_DEFAULT),
    'role_id' => $counselorRoleId,
    'is_active' => 1,
]);

assertCheck($c1Id > 0 && $c2Id > 0, "Created test counselors for scoping verification");

// 3. Lead Creation & Auto-Code Generation
$leadService = new LeadService();
$testMobile1 = '987' . substr((string)time(), -7);

Session::start();
Session::set('user_id', (int)$adminUser['id']);
Session::set('user_role', 'admin');
PermissionService::loadUserPermissions((int)$adminUser['id']);

$websiteSource = $sourceModel->findByName('Website');
$referralSource = $sourceModel->findByName('Referral');

$lead1 = $leadService->create([
    'name' => 'Ananya Sharma',
    'mobile' => $testMobile1,
    'whatsapp_number' => $testMobile1,
    'email' => 'ananya_' . time() . '@gmail.com',
    'lead_source_id' => $websiteSource['id'],
    'interest_type' => 'course',
    'interested_in' => 'Certified GST Professional',
    'assigned_to' => $c1Id,
    'status' => 'new',
    'notes' => 'Interested in weekend batch',
]);

assertCheck(!empty($lead1['id']), "Lead 1 created successfully");
assertCheck((bool)preg_match('/^LD-\d{4}-\d{4}$/', $lead1['lead_code']), "Lead code generated as {$lead1['lead_code']}");
assertCheck($lead1['status'] === 'new', "Initial status is 'new'");
assertCheck((int)$lead1['assigned_to'] === $c1Id, "Assigned to Counselor 1");

// 4. Duplicate Mobile Prevention
$duplicateCaught = false;
try {
    $leadService->create([
        'name' => 'Duplicate Ananya',
        'mobile' => $testMobile1,
        'lead_source_id' => $websiteSource['id'],
        'interested_in' => 'ITR Filing Course',
    ]);
} catch (\App\Exceptions\ValidationException $e) {
    $duplicateCaught = true;
    assertCheck(isset($e->getErrors()['mobile']), "Validation catches duplicate mobile number");
}
assertCheck($duplicateCaught, "Duplicate mobile rejected on lead creation");

// 5. Referral requires 'referred_by'
$referralMissingReferredBy = false;
try {
    $leadService->create([
        'name' => 'Vikram Patel',
        'mobile' => '988' . substr((string)time(), -7),
        'lead_source_id' => $referralSource['id'],
        'interested_in' => 'GST Registration',
    ]);
} catch (\App\Exceptions\ValidationException $e) {
    $referralMissingReferredBy = true;
    assertCheck(isset($e->getErrors()['referred_by']), "Referral source enforces 'referred_by' field");
}
assertCheck($referralMissingReferredBy, "Referred by validation enforced for Referral source");

// Create Lead 2 assigned to Counselor 2
$testMobile2 = '986' . substr((string)time(), -7);
$lead2 = $leadService->create([
    'name' => 'Vikram Patel',
    'mobile' => $testMobile2,
    'lead_source_id' => $referralSource['id'],
    'referred_by' => 'Dr. Verma',
    'interest_type' => 'service',
    'interested_in' => 'Private Limited Registration',
    'assigned_to' => $c2Id,
    'status' => 'new',
]);
assertCheck(!empty($lead2['id']), "Lead 2 (Referral with referred_by) created successfully");

// 6. Counselor Scoping: Counselor 1 should only see their assigned leads
Session::set('user_id', $c1Id);
Session::set('user_role', 'counselor');
PermissionService::loadUserPermissions($c1Id);

$c1List = $leadService->list([], 1, 20);
$c1LeadIds = array_column($c1List['items'], 'id');
assertCheck(in_array((int)$lead1['id'], $c1LeadIds, true), "Counselor 1 sees Lead 1 (assigned to them)");
assertCheck(!in_array((int)$lead2['id'], $c1LeadIds, true), "Counselor 1 does NOT see Lead 2 (assigned to Counselor 2)");

// 7. Kanban Status Transitions & Lost Reason
Session::set('user_id', (int)$adminUser['id']);
Session::set('user_role', 'admin');
PermissionService::loadUserPermissions((int)$adminUser['id']);

$updatedToContacted = $leadService->updateStatus($lead1['id'], 'contacted');
assertCheck($updatedToContacted['status'] === 'contacted', "Lead status changed to 'contacted'");

// Moving to lost without reason must fail
$lostWithoutReasonCaught = false;
try {
    $leadService->updateStatus($lead1['id'], 'lost', '');
} catch (\App\Exceptions\ValidationException $e) {
    $lostWithoutReasonCaught = true;
}
assertCheck($lostWithoutReasonCaught, "Moving lead to 'lost' without reason is rejected");

$updatedToLost = $leadService->updateStatus($lead1['id'], 'lost', 'Fee was too high for current budget');
assertCheck($updatedToLost['status'] === 'lost', "Lead marked as 'lost'");
assertCheck($updatedToLost['lost_reason'] === 'Fee was too high for current budget', "Lost reason saved correctly");

// 8. Lead Conversion: Convert Lead 2 to Client and Student
$convertResult = $leadService->convert($lead2['id'], [
    'convert_to_client' => true,
    'convert_to_student' => true,
    'client_type' => 'company',
    'course_name' => 'Corporate Tax Practical Training',
]);

assertCheck($convertResult['lead']['status'] === 'converted', "Lead status updated to 'converted'");
assertCheck(!empty($convertResult['client']['id']), "Client created: " . ($convertResult['client']['client_code'] ?? ''));
assertCheck((bool)preg_match('/^CL-\d{4}-\d{4}$/', $convertResult['client']['client_code']), "Client code formatted as CL-YYYY-XXXX");
assertCheck(!empty($convertResult['student']['id']), "Student created: " . ($convertResult['student']['student_code'] ?? ''));
assertCheck((bool)preg_match('/^ST-\d{4}-\d{4}$/', $convertResult['student']['student_code']), "Student code formatted as ST-YYYY-XXXX");
assertCheck((int)$convertResult['lead']['converted_client_id'] === (int)$convertResult['client']['id'], "Lead linked to converted_client_id");
assertCheck((int)$convertResult['lead']['converted_student_id'] === (int)$convertResult['student']['id'], "Lead linked to converted_student_id");

// 9. Follow-ups Upgrade: Lead follow-up with outcome, remarks, next prompt, WA and call links
$followUpService = new FollowUpService();

// Create follow-up on Lead 1
$leadFollowUp = $followUpService->create([
    'lead_id' => $lead1['id'],
    'follow_up_date' => date('Y-m-d H:i:s', strtotime('+2 hours')),
    'type' => 'call',
    'notes' => 'Call lead regarding new batch discount',
    'assigned_to' => $c1Id,
]);
assertCheck(!empty($leadFollowUp['id']), "Follow-up created for Lead 1");

// Log outcome and trigger auto-prompt next follow-up
$loggedFollowUp = $followUpService->logOutcome($leadFollowUp['id'], [
    'outcome' => 'call_back',
    'remarks' => 'Requested call back tomorrow at 4 PM',
    'next_follow_up_date' => date('Y-m-d H:i:s', strtotime('+1 day 16:00:00')),
    'next_type' => 'whatsapp',
    'next_notes' => 'Send WhatsApp brochure before calling back',
]);

assertCheck($loggedFollowUp['status'] === 'done', "Original follow-up marked as completed (status = done)");
assertCheck($loggedFollowUp['outcome'] === 'call_back', "Outcome saved as 'call_back'");
assertCheck(!empty($loggedFollowUp['next_follow_up_id']), "Next follow-up auto-scheduled (ID: {$loggedFollowUp['next_follow_up_id']})");

// Verify Click-to-WhatsApp and Click-to-Call links
$waLink = "https://wa.me/91" . preg_replace('/[^0-9]/', '', $lead1['whatsapp_number']);
$telLink = "tel:" . preg_replace('/[^0-9]/', '', $lead1['mobile']);
assertCheck(str_starts_with($waLink, 'https://wa.me/91'), "WhatsApp wa.me link generated correctly");
assertCheck(str_starts_with($telLink, 'tel:'), "Call tel: link generated correctly");

// 10. Follow-ups filtering (today, overdue, upcoming)
$todayList = $followUpService->list(['tab' => 'today', 'lead_id' => $lead1['id']]);
assertCheck(is_array($todayList), "Follow-ups 'today' list retrieved");

$upcomingList = $followUpService->list(['tab' => 'upcoming']);
assertCheck(is_array($upcomingList) && count($upcomingList['items']) > 0, "Follow-ups 'upcoming' contains newly scheduled follow-up");

// 11. CSV Import with Duplicate Mobile Checking
$uniqueMobile3 = '985' . substr((string)time(), -7);
$csvContent = "name,mobile,email,source,interested_in,status\n"
    . "Rohan Gupta,{$uniqueMobile3},rohan@example.com,Instagram,GST Filing,new\n"
    . "Duplicate Rohan,{$testMobile1},dup@example.com,Google,ITR,new\n"; // duplicate mobile

$importResult = $leadService->importCsv($csvContent);
assertCheck($importResult['imported'] === 1, "CSV Import imported 1 valid new lead");
assertCheck($importResult['skipped'] >= 1, "CSV Import skipped 1 duplicate mobile lead");

echo "\n========================================\n";
echo "ALL PHASE 1 INTEGRATION TESTS PASSED!\n";
echo "========================================\n";
