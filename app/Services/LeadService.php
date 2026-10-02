<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Service;
use App\Models\Student;
use App\Services\Cache\Cache;
use RuntimeException;
use Throwable;

class LeadService
{
    private Lead $leadModel;
    private LeadSource $leadSourceModel;
    private Contact $contactModel;
    private Client $clientModel;
    private Student $studentModel;
    private ActivityLog $activityLog;

    public function __construct(
        ?Lead $leadModel = null,
        ?LeadSource $leadSourceModel = null,
        ?Contact $contactModel = null,
        ?Client $clientModel = null,
        ?Student $studentModel = null,
        ?ActivityLog $activityLog = null
    ) {
        $this->leadModel = $leadModel ?? new Lead();
        $this->leadSourceModel = $leadSourceModel ?? new LeadSource();
        $this->contactModel = $contactModel ?? new Contact();
        $this->clientModel = $clientModel ?? new Client();
        $this->studentModel = $studentModel ?? new Student();
        $this->activityLog = $activityLog ?? new ActivityLog();
    }

    /**
     * List leads with filters, search, and pagination.
     */
    public function list(array $filters): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('lead.view_all');

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min((int)($filters['per_page'] ?? 20), 100));
        $sortBy = (string)($filters['sort_by'] ?? 'created_at');
        $sortDir = (string)($filters['sort_dir'] ?? 'DESC');

        return $this->leadModel->search($filters, $userId, $canViewAll, $page, $perPage, $sortBy, $sortDir);
    }

    /**
     * Get grouped leads for Kanban board.
     */
    public function getKanban(array $filters): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('lead.view_all');

        return $this->leadModel->getKanbanData($filters, $userId, $canViewAll);
    }

    /**
     * Get single lead with scoping check.
     */
    public function get(int|string $id): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('lead.view_all');

        $lead = $this->leadModel->findScoped($id, $userId, $canViewAll);
        if (!$lead) {
            $unscoped = $this->leadModel->find($id);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to access this lead", 403);
            }
            throw new RuntimeException("Lead not found", 404);
        }

        return $lead;
    }

    /**
     * Create a new lead.
     */
    public function create(array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('lead.view_all');

        $errors = [];

        // 1. Name validation
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Full name is required.';
        }

        // 2. Mobile validation
        $rawMobile = (string)($data['mobile'] ?? '');
        $mobile = preg_replace('/[^0-9]/', '', $rawMobile);
        if ($mobile === '' || strlen($mobile) !== 10) {
            $errors['mobile'] = 'A valid 10-digit mobile number is required.';
        } else {
            // Duplicate mobile check
            $existing = $this->leadModel->findByMobile($mobile);
            if ($existing) {
                $errors['mobile'] = sprintf('A lead with this mobile number already exists (%s - %s).', $existing['lead_code'], $existing['name']);
            }
        }

        // 3. WhatsApp number
        $sameAsMobile = !empty($data['same_as_mobile']) || !empty($data['whatsapp_same_as_mobile']);
        if ($sameAsMobile || empty($data['whatsapp_number'])) {
            $whatsappNumber = $mobile;
        } else {
            $whatsappNumber = preg_replace('/[^0-9]/', '', (string)$data['whatsapp_number']);
            if (strlen($whatsappNumber) < 10) {
                $whatsappNumber = $mobile;
            }
        }

        // 4. Email validation
        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // 5. Lead source & Referral check
        $leadSourceId = !empty($data['lead_source_id']) ? (int)$data['lead_source_id'] : null;
        $referredBy = trim((string)($data['referred_by'] ?? ''));
        if ($leadSourceId) {
            $sourceRow = $this->leadSourceModel->find($leadSourceId);
            if ($sourceRow && strtolower((string)$sourceRow['name']) === 'referral' && $referredBy === '') {
                $errors['referred_by'] = 'Please specify who referred this lead.';
            }
        }

        // 6. Interest validation
        $interestType = strtolower(trim((string)($data['interest_type'] ?? 'service')));
        if (!in_array($interestType, ['service', 'course', 'other'], true)) {
            $interestType = 'service';
        }

        $interestedIn = trim((string)($data['interested_in'] ?? ''));
        $serviceId = !empty($data['service_id']) ? (int)$data['service_id'] : null;
        $courseId = !empty($data['course_id']) ? (int)$data['course_id'] : null;

        if ($interestedIn === '') {
            $errors['interested_in'] = 'Please select or enter the interested service or course.';
        }

        // 7. Status & Lost reason
        $status = strtolower(trim((string)($data['status'] ?? 'new')));
        $validStatuses = ['new', 'contacted', 'interested', 'follow_up', 'converted', 'lost'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'new';
        }

        $lostReason = trim((string)($data['lost_reason'] ?? ''));
        if ($status === 'lost' && $lostReason === '') {
            $errors['lost_reason'] = 'Lost reason is required when marking a lead as Lost.';
        }

        // 8. Assigned counselor
        $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;
        if (!$canViewAll || $assignedTo === null) {
            // Counselor assigns to self by default
            $assignedTo = $assignedTo ?? $userId;
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Upsert contact in shared contacts table
        $contact = $this->contactModel->firstOrCreate([
            'name' => $name,
            'mobile' => $mobile,
            'whatsapp_number' => $whatsappNumber,
            'email' => $email !== '' ? $email : null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
        ]);

        $year = (int)date('Y');
        $leadCode = $this->leadModel->generateLeadCode($year);

        $notes = trim((string)($data['notes'] ?? ''));

        $insertData = [
            'lead_code' => $leadCode,
            'contact_id' => $contact['id'] ?? null,
            'name' => $name,
            'mobile' => $mobile,
            'whatsapp_number' => $whatsappNumber,
            'email' => $email !== '' ? $email : null,
            'lead_source_id' => $leadSourceId,
            'referred_by' => $referredBy !== '' ? $referredBy : null,
            'interest_type' => $interestType,
            'interested_in' => $interestedIn,
            'service_id' => $serviceId,
            'course_id' => $courseId,
            'status' => $status,
            'lost_reason' => $status === 'lost' ? $lostReason : null,
            'assigned_to' => $assignedTo,
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $userId,
        ];

        $newId = (int)$this->leadModel->insert($insertData);

        // Activity log
        $clientIp = $ip ?? '127.0.0.1';
        $clientUa = $userAgent ?? '';
        $this->activityLog->log(
            $userId,
            'lead',
            $newId,
            'lead_created',
            [
                'lead_code' => $leadCode,
                'name' => $name,
                'mobile' => $mobile,
                'status' => $status,
                'assigned_to' => $assignedTo,
            ],
            null,
            $clientIp,
            $clientUa
        );

        Cache::forgetByPrefix('dashboard:stats');

        return $this->get($newId);
    }

    /**
     * Update existing lead.
     */
    public function update(int|string $id, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('lead.view_all');

        $existing = $this->get($id);
        $errors = [];
        $updateData = [];

        if (isset($data['name'])) {
            $name = trim((string)$data['name']);
            if ($name === '') {
                $errors['name'] = 'Full name cannot be empty.';
            } else {
                $updateData['name'] = $name;
            }
        }

        if (isset($data['mobile'])) {
            $mobile = preg_replace('/[^0-9]/', '', (string)$data['mobile']);
            if (strlen($mobile) !== 10) {
                $errors['mobile'] = 'A valid 10-digit mobile number is required.';
            } else {
                $duplicate = $this->leadModel->findByMobile($mobile);
                if ($duplicate && (int)$duplicate['id'] !== (int)$id) {
                    $errors['mobile'] = sprintf('Another lead with this mobile already exists (%s).', $duplicate['lead_code']);
                } else {
                    $updateData['mobile'] = $mobile;
                }
            }
        }

        if (isset($data['whatsapp_number'])) {
            $whatsapp = preg_replace('/[^0-9]/', '', (string)$data['whatsapp_number']);
            $updateData['whatsapp_number'] = strlen($whatsapp) >= 10 ? $whatsapp : ($updateData['mobile'] ?? $existing['mobile']);
        }

        if (isset($data['email'])) {
            $email = trim((string)$data['email']);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email address.';
            } else {
                $updateData['email'] = $email !== '' ? $email : null;
            }
        }

        if (isset($data['lead_source_id'])) {
            $sourceId = !empty($data['lead_source_id']) ? (int)$data['lead_source_id'] : null;
            $updateData['lead_source_id'] = $sourceId;
            if ($sourceId) {
                $sourceRow = $this->leadSourceModel->find($sourceId);
                if ($sourceRow && strtolower((string)$sourceRow['name']) === 'referral') {
                    $ref = trim((string)($data['referred_by'] ?? $existing['referred_by'] ?? ''));
                    if ($ref === '') {
                        $errors['referred_by'] = 'Please specify who referred this lead.';
                    } else {
                        $updateData['referred_by'] = $ref;
                    }
                }
            }
        }

        if (isset($data['referred_by']) && !array_key_exists('referred_by', $updateData)) {
            $updateData['referred_by'] = trim((string)$data['referred_by']) ?: null;
        }

        if (isset($data['interest_type'])) {
            $type = strtolower(trim((string)$data['interest_type']));
            if (in_array($type, ['service', 'course', 'other'], true)) {
                $updateData['interest_type'] = $type;
            }
        }

        if (isset($data['interested_in'])) {
            $in = trim((string)$data['interested_in']);
            if ($in !== '') {
                $updateData['interested_in'] = $in;
            }
        }

        if (isset($data['service_id'])) {
            $updateData['service_id'] = !empty($data['service_id']) ? (int)$data['service_id'] : null;
        }
        if (isset($data['course_id'])) {
            $updateData['course_id'] = !empty($data['course_id']) ? (int)$data['course_id'] : null;
        }

        if (isset($data['status'])) {
            $st = strtolower(trim((string)$data['status']));
            $valid = ['new', 'contacted', 'interested', 'follow_up', 'converted', 'lost'];
            if (in_array($st, $valid, true)) {
                $updateData['status'] = $st;
                if ($st === 'lost') {
                    $reason = trim((string)($data['lost_reason'] ?? $existing['lost_reason'] ?? ''));
                    if ($reason === '') {
                        $errors['lost_reason'] = 'Lost reason is required when status is Lost.';
                    } else {
                        $updateData['lost_reason'] = $reason;
                    }
                } else {
                    $updateData['lost_reason'] = null;
                }
            }
        }

        if (isset($data['assigned_to']) && $canViewAll) {
            $updateData['assigned_to'] = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;
        }

        if (isset($data['notes'])) {
            $updateData['notes'] = trim((string)$data['notes']) ?: null;
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        if (empty($updateData)) {
            return $existing;
        }

        $this->leadModel->update($id, $updateData);

        // Activity log
        $clientIp = $ip ?? '127.0.0.1';
        $clientUa = $userAgent ?? '';
        $this->activityLog->log(
            $userId,
            'lead',
            (int)$id,
            'lead_updated',
            $updateData,
            $existing,
            $clientIp,
            $clientUa
        );

        Cache::forgetByPrefix('dashboard:stats');

        return $this->get($id);
    }

    /**
     * Update lead status (for Kanban drag & drop).
     */
    public function updateStatus(
        int|string $id,
        string $status,
        ?string $lostReason = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $status = strtolower(trim($status));
        $valid = ['new', 'contacted', 'interested', 'follow_up', 'converted', 'lost'];
        if (!in_array($status, $valid, true)) {
            throw new ValidationException(['status' => 'Invalid lead status.']);
        }

        if ($status === 'lost' && empty(trim((string)$lostReason))) {
            throw new ValidationException(['lost_reason' => 'Lost reason is required when moving lead to Lost.']);
        }

        return $this->update($id, [
            'status' => $status,
            'lost_reason' => $status === 'lost' ? trim((string)$lostReason) : null,
        ], $ip, $userAgent);
    }

    /**
     * Convert Lead to Client and/or Student.
     *
     * @param array{convert_to_client?: bool, convert_to_student?: bool, client_type?: string, gst_no?: string, pan_no?: string, course_name?: string} $options
     */
    public function convert(int|string $id, array $options, ?string $ip = null, ?string $userAgent = null): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');

        $lead = $this->get($id);

        $toClient = !empty($options['convert_to_client']);
        $toStudent = !empty($options['convert_to_student']);

        if (!$toClient && !$toStudent) {
            // Default to client if not specified
            $toClient = true;
        }

        $year = (int)date('Y');
        $createdClientId = null;
        $createdStudentId = null;
        $clientCode = null;
        $studentCode = null;

        // 1. Convert to Client
        if ($toClient) {
            // Check if client already exists with this mobile
            $existingClient = $this->clientModel->findByMobile($lead['mobile']);
            if ($existingClient) {
                $createdClientId = (int)$existingClient['id'];
                $clientCode = $existingClient['client_code'];
            } else {
                $clientCode = $this->clientModel->generateClientCode($year);
                $clientData = [
                    'client_code' => $clientCode,
                    'contact_id' => $lead['contact_id'] ?? null,
                    'client_type' => $options['client_type'] ?? 'individual',
                    'name' => $lead['name'],
                    'email' => $lead['email'] ?? sprintf('%s@client.local', strtolower($clientCode)),
                    'mobile' => $lead['mobile'],
                    'whatsapp_number' => $lead['whatsapp_number'] ?? $lead['mobile'],
                    'lead_source' => $lead['source_name'] ?? 'Lead Conversion',
                    'assigned_to' => $lead['assigned_to'] ?? $userId,
                    'status' => 'new',
                    'notes' => sprintf('Converted from Lead %s on %s. Original interest: %s', $lead['lead_code'], date('d-m-Y H:i'), $lead['interested_in']),
                    'created_by' => $userId,
                ];

                if (!empty($options['pan_no'])) {
                    $clientData['pan_no'] = $options['pan_no'];
                }
                if (!empty($options['gst_no'])) {
                    $clientData['gst_no'] = $options['gst_no'];
                }

                $createdClientId = (int)$this->clientModel->insert($clientData);
            }
        }

        // 2. Convert to Student
        if ($toStudent) {
            $existingStudent = $this->studentModel->findByMobile($lead['mobile']);
            if ($existingStudent) {
                $createdStudentId = (int)$existingStudent['id'];
                $studentCode = $existingStudent['student_code'];
            } else {
                $studentCode = $this->studentModel->generateStudentCode($year);
                $studentData = [
                    'student_code' => $studentCode,
                    'contact_id' => $lead['contact_id'] ?? null,
                    'name' => $lead['name'],
                    'email' => $lead['email'] ?? null,
                    'mobile' => $lead['mobile'],
                    'whatsapp_number' => $lead['whatsapp_number'] ?? $lead['mobile'],
                    'course_name' => $options['course_name'] ?? $lead['interested_in'],
                    'qualification' => $options['qualification'] ?? null,
                    'status' => 'enrolled',
                    'notes' => sprintf('Converted from Lead %s on %s.', $lead['lead_code'], date('d-m-Y H:i')),
                    'created_by' => $userId,
                ];

                $createdStudentId = (int)$this->studentModel->insert($studentData);
            }
        }

        // 3. Mark Lead as Converted and link IDs
        $leadUpdates = [
            'status' => 'converted',
            'converted_at' => date('Y-m-d H:i:s'),
        ];
        if ($createdClientId) {
            $leadUpdates['converted_client_id'] = $createdClientId;
        }
        if ($createdStudentId) {
            $leadUpdates['converted_student_id'] = $createdStudentId;
        }

        $this->leadModel->update($id, $leadUpdates);

        // Activity log
        $clientIp = $ip ?? '127.0.0.1';
        $clientUa = $userAgent ?? '';
        $this->activityLog->log(
            $userId,
            'lead',
            (int)$id,
            'lead_converted',
            [
                'client_id' => $createdClientId,
                'client_code' => $clientCode,
                'student_id' => $createdStudentId,
                'student_code' => $studentCode,
            ],
            ['status' => $lead['status']],
            $clientIp,
            $clientUa
        );

        Cache::forgetByPrefix('dashboard:stats');

        return [
            'lead' => $this->get($id),
            'client_id' => $createdClientId,
            'client_code' => $clientCode,
            'student_id' => $createdStudentId,
            'student_code' => $studentCode,
            'client' => $createdClientId ? $this->clientModel->find($createdClientId) : null,
            'student' => $createdStudentId ? $this->studentModel->find($createdStudentId) : null,
        ];
    }

    /**
     * Import leads from CSV with duplicate checking on mobile.
     */
    public function importCsv(string $csvContent, ?int $defaultSourceId = null, ?int $defaultAssignedTo = null): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');

        $lines = preg_split("/\r\n|\n|\r/", trim($csvContent));
        if (empty($lines)) {
            throw new ValidationException(['file' => 'CSV file is empty.']);
        }

        $headerRow = str_getcsv(array_shift($lines), ',', '"', '\\');
        $headers = array_map(static fn($h) => strtolower(trim((string)$h)), $headerRow);

        $nameIdx = array_search('name', $headers, true);
        $mobileIdx = array_search('mobile', $headers, true);
        if ($mobileIdx === false) {
            $mobileIdx = array_search('phone', $headers, true);
        }
        $whatsappIdx = array_search('whatsapp', $headers, true);
        $emailIdx = array_search('email', $headers, true);
        $sourceIdx = array_search('source', $headers, true);
        $interestedIdx = array_search('interested_in', $headers, true);
        if ($interestedIdx === false) {
            $interestedIdx = array_search('service', $headers, true);
            if ($interestedIdx === false) {
                $interestedIdx = array_search('course', $headers, true);
            }
        }
        $notesIdx = array_search('notes', $headers, true);

        if ($nameIdx === false || $mobileIdx === false) {
            throw new ValidationException(['file' => 'CSV must have at least "Name" and "Mobile" columns.']);
        }

        // Cache lead sources
        $sources = $this->leadSourceModel->all();
        $sourceMap = [];
        foreach ($sources as $s) {
            $sourceMap[strtolower((string)$s['name'])] = (int)$s['id'];
        }

        $imported = 0;
        $duplicates = 0;
        $errors = [];
        $seenMobiles = [];

        foreach ($lines as $lineNum => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $row = str_getcsv($trimmed, ',', '"', '\\');
            $rowNum = $lineNum + 2; // account for header and 1-index

            $name = trim((string)($row[$nameIdx] ?? ''));
            $rawMobile = trim((string)($row[$mobileIdx] ?? ''));
            $mobile = preg_replace('/[^0-9]/', '', $rawMobile);

            if ($name === '' || strlen($mobile) !== 10) {
                $errors[] = "Row {$rowNum}: Invalid name or 10-digit mobile number ({$rawMobile}).";
                continue;
            }

            // Check if seen in current CSV batch
            if (isset($seenMobiles[$mobile])) {
                $duplicates++;
                $errors[] = "Row {$rowNum}: Duplicate mobile {$mobile} within file (Skipped).";
                continue;
            }
            $seenMobiles[$mobile] = true;

            // Check if duplicate in database
            $existing = $this->leadModel->findByMobile($mobile);
            if ($existing) {
                $duplicates++;
                $errors[] = "Row {$rowNum}: Duplicate mobile {$mobile} already exists in CRM as {$existing['lead_code']} (Skipped).";
                continue;
            }

            $whatsapp = $whatsappIdx !== false ? preg_replace('/[^0-9]/', '', (string)($row[$whatsappIdx] ?? '')) : '';
            if (strlen($whatsapp) < 10) {
                $whatsapp = $mobile;
            }

            $email = $emailIdx !== false ? trim((string)($row[$emailIdx] ?? '')) : '';
            $sourceName = $sourceIdx !== false ? strtolower(trim((string)($row[$sourceIdx] ?? ''))) : '';
            $sourceId = $defaultSourceId;
            if ($sourceName !== '' && isset($sourceMap[$sourceName])) {
                $sourceId = $sourceMap[$sourceName];
            }

            $interestedIn = $interestedIdx !== false ? trim((string)($row[$interestedIdx] ?? '')) : 'General Inquiry';
            if ($interestedIn === '') {
                $interestedIn = 'General Inquiry';
            }

            $notes = $notesIdx !== false ? trim((string)($row[$notesIdx] ?? '')) : '';

            try {
                $this->create([
                    'name' => $name,
                    'mobile' => $mobile,
                    'whatsapp_number' => $whatsapp,
                    'email' => $email,
                    'lead_source_id' => $sourceId,
                    'interested_in' => $interestedIn,
                    'status' => 'new',
                    'assigned_to' => $defaultAssignedTo ?? $userId,
                    'notes' => $notes !== '' ? $notes : 'Imported via CSV',
                ]);
                $imported++;
            } catch (Throwable $e) {
                $errors[] = "Row {$rowNum} error: " . $e->getMessage();
            }
        }

        return [
            'total_processed' => $imported + $duplicates,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => $duplicates,
            'errors' => $errors,
        ];
    }
}
