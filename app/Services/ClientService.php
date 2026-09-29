<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Helpers\Crypto;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Services\Cache\Cache;
use RuntimeException;
use Throwable;

class ClientService
{
    private Client $clientModel;
    private ClientDocument $docModel;
    private ActivityLog $activityLog;
    private User $userModel;
    private string $uploadBasePath;

    public const GST_STATE_CODES = [
        'Jammu and Kashmir' => ['01'],
        'Himachal Pradesh' => ['02'],
        'Punjab' => ['03'],
        'Chandigarh' => ['04'],
        'Uttarakhand' => ['05'],
        'Haryana' => ['06'],
        'Delhi' => ['07'],
        'Delhi (NCT)' => ['07'],
        'Rajasthan' => ['08'],
        'Uttar Pradesh' => ['09'],
        'Bihar' => ['10'],
        'Sikkim' => ['11'],
        'Arunachal Pradesh' => ['12'],
        'Nagaland' => ['13'],
        'Manipur' => ['14'],
        'Mizoram' => ['15'],
        'Tripura' => ['16'],
        'Meghalaya' => ['17'],
        'Assam' => ['18'],
        'West Bengal' => ['19'],
        'Jharkhand' => ['20'],
        'Odisha' => ['21'],
        'Chhattisgarh' => ['22'],
        'Madhya Pradesh' => ['23'],
        'Gujarat' => ['24'],
        'Dadra and Nagar Haveli and Daman and Diu' => ['26', '25'],
        'Maharashtra' => ['27'],
        'Andhra Pradesh' => ['37', '28'],
        'Karnataka' => ['29'],
        'Goa' => ['30'],
        'Lakshadweep' => ['31'],
        'Kerala' => ['32'],
        'Tamil Nadu' => ['33'],
        'Puducherry' => ['34'],
        'Andaman and Nicobar Islands' => ['35'],
        'Telangana' => ['36'],
        'Ladakh' => ['38'],
    ];

    /**
     * @return array<int, string>
     */
    public static function getGstStateCodes(string $state): array
    {
        $normalized = trim($state);
        return self::GST_STATE_CODES[$normalized] ?? [];
    }

    public function __construct(
        ?Client $clientModel = null,
        ?ClientDocument $docModel = null,
        ?ActivityLog $activityLog = null,
        ?User $userModel = null,
        ?string $uploadBasePath = null
    ) {
        $this->clientModel = $clientModel ?? new Client();
        $this->docModel = $docModel ?? new ClientDocument();
        $this->activityLog = $activityLog ?? new ActivityLog();
        $this->userModel = $userModel ?? new User();
        $this->uploadBasePath = $uploadBasePath ?? (dirname(__DIR__, 2) . '/storage/uploads/clients');
    }

    /**
     * List clients with server-side pagination, searching, filtering, and data scoping.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listClients(
        array $filters = [],
        int $page = 1,
        int $perPage = 15,
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        Session::start();
        $userId = (int)Session::get('user_id');

        $canViewAll = PermissionService::can('client.view_all');
        $canViewOwn = PermissionService::can('client.view_own');

        if (!$canViewAll && !$canViewOwn) {
            throw new RuntimeException("Unauthorized to view clients", 403);
        }

        return $this->clientModel->searchClients($filters, $userId, $canViewAll, $page, $perPage, $sortBy, $sortDir);
    }

    /**
     * Get single client with assigned user details.
     */
    public function getClient(int|string $id): ?array
    {
        Session::start();
        $userId = (int)Session::get('user_id');

        $canViewAll = PermissionService::can('client.view_all');
        $canViewOwn = PermissionService::can('client.view_own');

        if (!$canViewAll && !$canViewOwn) {
            throw new RuntimeException("Unauthorized to view clients", 403);
        }

        $client = $this->clientModel->findScoped($id, $userId, $canViewAll);
        if ($client !== null && isset($client['pan_no'])) {
            $plainPan = $client['pan_no'];
            $isAdmin = PermissionService::getRole() === 'admin' || (PermissionService::can('user.manage') && PermissionService::can('client.delete'));
            $client['pan_no'] = Crypto::maskPan($plainPan);
            if ($isAdmin && $plainPan) {
                $client['pan_no_raw'] = $plainPan;
            }
        }

        return $client;
    }

    /**
     * Get full client profile including documents and activity timeline.
     *
     * @return array{client: array, documents: array, activities: array}
     */
    public function getClientProfile(int|string $id, ?int $userId = null): array
    {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        $canViewAll = PermissionService::can('client.view_all');
        $canViewOwn = PermissionService::can('client.view_own');

        if (!$canViewAll && !$canViewOwn) {
            throw new RuntimeException("Unauthorized to view clients", 403);
        }

        $client = $this->clientModel->findScoped($id, $userId, $canViewAll);
        if (!$client) {
            $unscoped = $this->clientModel->find($id);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to view this client", 403);
            }
            throw new RuntimeException("Client not found", 404);
        }

        if (isset($client['pan_no'])) {
            $plainPan = $client['pan_no'];
            $isAdmin = PermissionService::getRole() === 'admin' || (PermissionService::can('user.manage') && PermissionService::can('client.delete'));
            $client['pan_no'] = Crypto::maskPan($plainPan);
            if ($isAdmin && $plainPan) {
                $client['pan_no_raw'] = $plainPan;
            }
        }

        $client['assigned_user_name'] = $client['assigned_to_name'] ?? null;

        $documents = $this->docModel->getByClient($id);
        $activities = $this->activityLog->getForClient($id, 50);

        return [
            'client' => $client,
            'documents' => $documents,
            'activities' => $activities,
        ];
    }

    /**
     * Update client details with validation, duplicate checks (ignoring self),
     * sales user immutability on assignment, and diff-only activity logging.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function updateClient(
        int|string $id,
        array $data,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        if (!PermissionService::can('client.edit')) {
            throw new RuntimeException("Forbidden: insufficient permissions to edit clients", 403);
        }

        $canViewAll = PermissionService::can('client.view_all');
        $existing = $this->clientModel->findScoped($id, $userId, $canViewAll);
        if (!$existing) {
            $unscoped = $this->clientModel->find($id);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to modify this client", 403);
            }
            throw new RuntimeException("Client not found", 404);
        }

        $clientId = (int)$existing['id'];

        // 1. Normalization
        $clientType = isset($data['client_type'])
            ? strtolower(trim((string)$data['client_type']))
            : (string)$existing['client_type'];
        $name = isset($data['name']) ? trim((string)$data['name']) : (string)$existing['name'];
        $contactPerson = isset($data['contact_person']) ? trim((string)$data['contact_person']) : (string)($existing['contact_person'] ?? '');
        $email = isset($data['email']) ? strtolower(trim((string)$data['email'])) : (string)$existing['email'];
        $mobile = isset($data['mobile']) ? preg_replace('/\s+/', '', trim((string)$data['mobile'])) : (string)$existing['mobile'];
        $altMobile = isset($data['alt_mobile'])
            ? (!empty($data['alt_mobile']) ? preg_replace('/\s+/', '', trim((string)$data['alt_mobile'])) : null)
            : $existing['alt_mobile'];
        $rawPanInput = isset($data['pan_no']) ? trim((string)$data['pan_no']) : null;
        if ($rawPanInput !== null && $rawPanInput !== '' && Crypto::isMasked($rawPanInput)) {
            $panNo = $existing['pan_no'];
        } elseif ($rawPanInput !== null && $rawPanInput !== '') {
            $panNo = strtoupper($rawPanInput);
        } elseif (isset($data['pan_no'])) {
            $panNo = null;
        } else {
            $panNo = $existing['pan_no'];
        }
        $gstNo = isset($data['gst_no'])
            ? (!empty($data['gst_no']) ? strtoupper(trim((string)$data['gst_no'])) : null)
            : $existing['gst_no'];
        $website = isset($data['website'])
            ? (!empty($data['website']) ? trim((string)$data['website']) : null)
            : $existing['website'];
        $addressLine1 = isset($data['address_line1']) ? trim((string)$data['address_line1']) : (string)$existing['address_line1'];
        $addressLine2 = isset($data['address_line2'])
            ? (!empty($data['address_line2']) ? trim((string)$data['address_line2']) : null)
            : $existing['address_line2'];
        $city = isset($data['city']) ? trim((string)$data['city']) : (string)$existing['city'];
        $state = isset($data['state']) ? trim((string)$data['state']) : (string)$existing['state'];
        $pincode = isset($data['pincode']) ? trim((string)$data['pincode']) : (string)$existing['pincode'];
        $country = isset($data['country']) ? trim((string)$data['country']) : (string)$existing['country'];
        $industry = isset($data['industry'])
            ? (!empty($data['industry']) ? trim((string)$data['industry']) : null)
            : $existing['industry'];
        $companySize = isset($data['company_size'])
            ? (!empty($data['company_size']) ? trim((string)$data['company_size']) : null)
            : $existing['company_size'];
        $leadSource = isset($data['lead_source'])
            ? (!empty($data['lead_source']) ? trim((string)$data['lead_source']) : null)
            : $existing['lead_source'];
        $status = isset($data['status']) && in_array($data['status'], ['new', 'active', 'inactive'], true)
            ? (string)$data['status']
            : (string)$existing['status'];
        $tags = isset($data['tags'])
            ? (!empty($data['tags']) ? trim((string)$data['tags']) : null)
            : $existing['tags'];
        $notes = isset($data['notes'])
            ? (!empty($data['notes']) ? trim((string)$data['notes']) : null)
            : $existing['notes'];

        // 2. Validation
        $errors = [];

        if (!in_array($clientType, ['individual', 'company'], true)) {
            $errors['client_type'] = 'Client type must be either Individual or Company.';
        }

        if ($name === '' || mb_strlen($name) < 2) {
            $errors['name'] = $clientType === 'company' ? 'Company name is required.' : 'Full name is required.';
        }

        if ($clientType === 'company' && $contactPerson === '') {
            $errors['contact_person'] = 'Contact person is required for company clients.';
        }

        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif ($this->clientModel->existsByEmail($email, $clientId)) {
            $errors['email'] = 'Client with this email already exists.';
        }

        if ($mobile === '') {
            $errors['mobile'] = 'Mobile number is required.';
        } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $errors['mobile'] = 'Must be a valid 10-digit Indian mobile number starting with 6-9.';
        } elseif ($this->clientModel->existsByMobile($mobile, $clientId)) {
            $errors['mobile'] = 'Client with this mobile number already exists.';
        }

        if ($altMobile !== null && !preg_match('/^[6-9]\d{9}$/', $altMobile)) {
            $errors['alt_mobile'] = 'Alternate mobile must be a valid 10-digit number starting with 6-9.';
        }

        if ($panNo !== null && !Crypto::isMasked($panNo) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $panNo)) {
            $errors['pan_no'] = 'Invalid PAN format. Must be 5 letters, 4 digits, 1 letter (e.g. ABCDE1234F).';
        }

        if ($gstNo !== null) {
            if (!preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstNo)) {
                $errors['gst_no'] = 'Invalid GSTIN format (15 characters, e.g. 27AAAAA0000A1Z5).';
            } elseif ($this->clientModel->existsByGst($gstNo, $clientId)) {
                $errors['gst_no'] = 'Client with this GST number already exists.';
            } elseif ($state !== null && !empty($state)) {
                $expectedCodes = self::getGstStateCodes($state);
                $gstPrefix = substr($gstNo, 0, 2);
                if (!empty($expectedCodes) && !in_array($gstPrefix, $expectedCodes, true)) {
                    $errors['gst_no'] = "GSTIN state code ({$gstPrefix}) does not match selected state ({$state}).";
                }
            }
        }

        if ($website !== null) {
            $urlCheck = (str_starts_with($website, 'http://') || str_starts_with($website, 'https://'))
                ? $website
                : 'https://' . $website;
            if (!filter_var($urlCheck, FILTER_VALIDATE_URL)) {
                $errors['website'] = 'Please enter a valid website URL.';
            }
        }

        if ($addressLine1 === '') {
            $errors['address_line1'] = 'Address line 1 is required.';
        }

        if ($pincode === '') {
            $errors['pincode'] = 'Pincode is required.';
        } elseif (!preg_match('/^\d{6}$/', $pincode)) {
            $errors['pincode'] = 'Pincode must be exactly 6 digits.';
        }

        if ($city === '') {
            $errors['city'] = 'City is required.';
        }

        if ($state === '') {
            $errors['state'] = 'State is required.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Validation failed. Please correct the highlighted errors.');
        }

        // Assigned to: sales user cannot reassign clients
        if (!$canViewAll) {
            $assignedTo = (int)$existing['assigned_to'];
        } else {
            $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : (int)$existing['assigned_to'];
        }

        $candidate = [
            'client_type' => $clientType,
            'name' => $name,
            'contact_person' => $clientType === 'company' ? $contactPerson : null,
            'email' => $email,
            'mobile' => $mobile,
            'alt_mobile' => $altMobile,
            'gst_no' => $gstNo,
            'pan_no' => $panNo,
            'industry' => $industry,
            'company_size' => $companySize,
            'website' => $website,
            'address_line1' => $addressLine1,
            'address_line2' => $addressLine2,
            'city' => $city,
            'state' => $state,
            'pincode' => $pincode,
            'country' => $country,
            'lead_source' => $leadSource,
            'assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            'status' => $status,
            'tags' => $tags,
            'notes' => $notes,
        ];

        // 3. Diff only changed fields
        $oldValues = [];
        $newValues = [];
        $fieldsToUpdate = [];

        foreach ($candidate as $field => $newVal) {
            $oldVal = $existing[$field] ?? null;
            if ((string)$oldVal !== (string)$newVal) {
                $oldValues[$field] = $oldVal;
                $newValues[$field] = $newVal;
                $fieldsToUpdate[$field] = $newVal;
            }
        }

        if (!empty($fieldsToUpdate)) {
            $this->clientModel->update($clientId, $fieldsToUpdate);

            $logNewValues = $newValues;
            $logOldValues = $oldValues;
            if (isset($logNewValues['pan_no'])) {
                $logNewValues['pan_no'] = Crypto::maskPan($logNewValues['pan_no']);
            }
            if (isset($logOldValues['pan_no'])) {
                $logOldValues['pan_no'] = Crypto::maskPan($logOldValues['pan_no']);
            }

            $this->activityLog->log(
                $userId,
                'client',
                $clientId,
                'update',
                $logNewValues,
                $logOldValues,
                $ip,
                $userAgent
            );

            Cache::forgetByPrefix('dashboard:stats');
        }

        return $this->clientModel->findScoped($clientId, $userId, $canViewAll) ?? $existing;
    }

    /**
     * Soft delete client with permission check and audit log.
     */
    public function deleteClient(
        int|string $id,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): bool {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        if (!PermissionService::can('client.delete')) {
            throw new RuntimeException("Forbidden: insufficient permissions to delete clients", 403);
        }

        $canViewAll = PermissionService::can('client.view_all');
        $client = $this->clientModel->findScoped($id, $userId, $canViewAll);
        if (!$client) {
            $unscoped = $this->clientModel->find($id);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to delete this client", 403);
            }
            throw new RuntimeException("Client not found", 404);
        }

        $deleted = $this->clientModel->deleteScoped($id, $userId, $canViewAll);
        if ($deleted) {
            Cache::forgetByPrefix('dashboard:stats');

            $this->activityLog->log(
                $userId,
                'client',
                $client['id'],
                'delete',
                ['deleted_at' => date('Y-m-d H:i:s')],
                ['status' => $client['status']],
                $ip,
                $userAgent
            );
        }

        return $deleted;
    }

    /**
     * Add single document attachment to existing client.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function addDocument(
        int|string $clientId,
        array $file,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        if (!PermissionService::can('client.edit')) {
            throw new RuntimeException("Forbidden: insufficient permissions", 403);
        }

        $canViewAll = PermissionService::can('client.view_all');
        $client = $this->clientModel->findScoped($clientId, $userId, $canViewAll);
        if (!$client) {
            $unscoped = $this->clientModel->find($clientId);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to modify this client", 403);
            }
            throw new RuntimeException("Client not found", 404);
        }

        $errors = [];
        $validated = $this->extractAndValidateFiles(['documents' => $file], $errors);
        if (!empty($errors) || empty($validated)) {
            throw new ValidationException($errors, 'Document validation failed.');
        }

        $docFile = $validated[0];
        $clientDir = $this->uploadBasePath . '/' . $clientId;
        if (!is_dir($clientDir) && !mkdir($clientDir, 0755, true) && !is_dir($clientDir)) {
            throw new RuntimeException("Failed to create storage directory for client documents.");
        }

        $ext = strtolower(pathinfo($docFile['name'], PATHINFO_EXTENSION));
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = $clientDir . '/' . $storedName;

        if (is_uploaded_file($docFile['tmp_name'])) {
            if (!move_uploaded_file($docFile['tmp_name'], $destPath)) {
                throw new RuntimeException("Failed to upload document file.");
            }
        } else {
            if (!copy($docFile['tmp_name'], $destPath)) {
                throw new RuntimeException("Failed to save document file.");
            }
        }

        $docId = $this->docModel->createDocument([
            'client_id' => (int)$clientId,
            'original_name' => basename($docFile['name']),
            'stored_name' => $storedName,
            'mime_type' => $docFile['mime'],
            'size_bytes' => $docFile['size'],
            'uploaded_by' => $userId,
        ]);

        $this->activityLog->log(
            $userId,
            'client',
            $clientId,
            'document_upload',
            ['document_id' => $docId, 'file_name' => basename($docFile['name'])],
            null,
            $ip,
            $userAgent
        );

        return $this->docModel->find($docId) ?? ['id' => $docId];
    }

    /**
     * Delete document attachment from client.
     */
    public function deleteDocument(
        int|string $clientId,
        int|string $docId,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): bool {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        if (!PermissionService::can('client.edit')) {
            throw new RuntimeException("Forbidden: insufficient permissions", 403);
        }

        $canViewAll = PermissionService::can('client.view_all');
        $client = $this->clientModel->findScoped($clientId, $userId, $canViewAll);
        if (!$client) {
            $unscoped = $this->clientModel->find($clientId);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to delete documents for this client", 403);
            }
            throw new RuntimeException("Client not found", 404);
        }

        $doc = $this->docModel->findByClientAndId($clientId, $docId);
        if (!$doc) {
            throw new RuntimeException("Document not found.", 404);
        }

        $deleted = $this->docModel->deleteDocument($clientId, $docId);
        if ($deleted) {
            $this->activityLog->log(
                $userId,
                'client',
                $clientId,
                'document_delete',
                ['document_id' => $docId, 'file_name' => $doc['original_name']],
                null,
                $ip,
                $userAgent
            );
        }

        return $deleted;
    }

    /**
     * Export clients with current filters to XLSX or CSV using PhpSpreadsheet.
     * PAN is masked (showing only last 4 chars) unless user is admin.
     *
     * @param array<string, mixed> $filters
     * @return array{file_path: string, filename: string, mime_type: string}
     */
    public function exportClients(array $filters = [], string $format = 'xlsx', ?int $userId = null): array
    {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        if (!PermissionService::can('client.export')) {
            throw new RuntimeException("Forbidden: insufficient permissions to export clients", 403);
        }

        $canViewAll = PermissionService::can('client.view_all');
        $rows = $this->clientModel->getClientsForExport($filters, $userId, $canViewAll);

        $userRole = PermissionService::getRole();
        $isAdmin = ($userRole === 'admin') || (PermissionService::can('user.manage') && PermissionService::can('client.delete'));

        $headers = [
            'Client Code', 'Type', 'Name', 'Contact Person', 'Email', 'Mobile', 'Alt Mobile',
            'GSTIN', 'PAN', 'Industry', 'Company Size', 'Website', 'Address Line 1',
            'Address Line 2', 'City', 'State', 'Pincode', 'Country', 'Lead Source',
            'Assigned To', 'Status', 'Created At'
        ];

        $dataRows = [];
        foreach ($rows as $r) {
            $plainPan = Crypto::decryptPan($r['pan_no'] ?? null);
            $pan = $isAdmin ? ($plainPan ?? '') : (Crypto::maskPan($plainPan) ?? '');

            $dataRows[] = [
                $r['client_code'],
                ucfirst((string)($r['client_type'] ?? 'individual')),
                $r['name'],
                $r['contact_person'] ?? '',
                $r['email'],
                $r['mobile'],
                $r['alt_mobile'] ?? '',
                $r['gst_no'] ?? '',
                $pan,
                $r['industry'] ?? '',
                $r['company_size'] ?? '',
                $r['website'] ?? '',
                $r['address_line1'] ?? '',
                $r['address_line2'] ?? '',
                $r['city'] ?? '',
                $r['state'] ?? '',
                $r['pincode'] ?? '',
                $r['country'] ?? 'India',
                $r['lead_source'] ?? '',
                $r['assigned_to_name'] ?? 'Unassigned',
                ucfirst((string)($r['status'] ?? 'new')),
                $r['created_at'] ?? '',
            ];
        }

        $timestamp = date('Ymd_His');
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';

        // Check if PhpSpreadsheet is available
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Clients');

            $sheet->fromArray([$headers], null, 'A1');
            if (!empty($dataRows)) {
                $sheet->fromArray($dataRows, null, 'A2');
            }

            $sheet->getStyle('A1:V1')->getFont()->setBold(true);

            $tempFile = tempnam(sys_get_temp_dir(), 'crm_export_');
            if ($format === 'xlsx') {
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save($tempFile);
                return [
                    'file_path' => $tempFile,
                    'filename' => "clients_export_{$timestamp}.xlsx",
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $writer->save($tempFile);
            return [
                'file_path' => $tempFile,
                'filename' => "clients_export_{$timestamp}.csv",
                'mime_type' => 'text/csv; charset=utf-8',
            ];
        }

        // Native CSV fallback if PhpSpreadsheet is not installed yet
        $tempFile = tempnam(sys_get_temp_dir(), 'crm_export_');
        $fp = fopen($tempFile, 'w');
        // Add UTF-8 BOM for Excel compatibility
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, $headers);
        foreach ($dataRows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        return [
            'file_path' => $tempFile,
            'filename' => "clients_export_{$timestamp}.csv",
            'mime_type' => 'text/csv; charset=utf-8',
        ];
    }

    /**
     * Create a new client and process file attachments within a single transaction.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return array{id: int, client_code: string}
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function createClient(
        array $data,
        array $files = [],
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        if ($userId <= 0) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        if (!PermissionService::can('client.create')) {
            throw new RuntimeException("Forbidden: insufficient permissions", 403);
        }

        // 1. Normalization
        $clientType = strtolower(trim((string)($data['client_type'] ?? 'individual')));
        $name = trim((string)($data['name'] ?? ''));
        $contactPerson = trim((string)($data['contact_person'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $mobile = preg_replace('/\s+/', '', trim((string)($data['mobile'] ?? '')));
        $altMobile = !empty($data['alt_mobile']) ? preg_replace('/\s+/', '', trim((string)$data['alt_mobile'])) : null;
        $panNo = !empty($data['pan_no']) ? strtoupper(trim((string)$data['pan_no'])) : null;
        $gstNo = !empty($data['gst_no']) ? strtoupper(trim((string)$data['gst_no'])) : null;
        $website = !empty($data['website']) ? trim((string)$data['website']) : null;
        $addressLine1 = trim((string)($data['address_line1'] ?? ''));
        $addressLine2 = !empty($data['address_line2']) ? trim((string)$data['address_line2']) : null;
        $city = trim((string)($data['city'] ?? ''));
        $state = trim((string)($data['state'] ?? ''));
        $pincode = trim((string)($data['pincode'] ?? ''));
        $country = !empty($data['country']) ? trim((string)$data['country']) : 'India';
        $industry = !empty($data['industry']) ? trim((string)$data['industry']) : null;
        $companySize = !empty($data['company_size']) ? trim((string)$data['company_size']) : null;
        $leadSource = !empty($data['lead_source']) ? trim((string)$data['lead_source']) : null;
        $status = in_array($data['status'] ?? '', ['new', 'active', 'inactive'], true) ? (string)$data['status'] : 'new';
        $tags = !empty($data['tags']) ? trim((string)$data['tags']) : null;
        $notes = !empty($data['notes']) ? trim((string)$data['notes']) : null;
        $consentGiven = !empty($data['consent_given']) && $data['consent_given'] !== '0' && $data['consent_given'] !== false;

        // 2. Validation
        $errors = [];

        if (!in_array($clientType, ['individual', 'company'], true)) {
            $errors['client_type'] = 'Client type must be either Individual or Company.';
        }

        if ($name === '' || mb_strlen($name) < 2) {
            $errors['name'] = $clientType === 'company' ? 'Company name is required.' : 'Full name is required.';
        }

        if ($clientType === 'company' && $contactPerson === '') {
            $errors['contact_person'] = 'Contact person is required for company clients.';
        }

        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif ($this->clientModel->existsByEmail($email)) {
            $errors['email'] = 'Client with this email already exists.';
        }

        if ($mobile === '') {
            $errors['mobile'] = 'Mobile number is required.';
        } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $errors['mobile'] = 'Must be a valid 10-digit Indian mobile number starting with 6-9.';
        } elseif ($this->clientModel->existsByMobile($mobile)) {
            $errors['mobile'] = 'Client with this mobile number already exists.';
        }

        if ($altMobile !== null && !preg_match('/^[6-9]\d{9}$/', $altMobile)) {
            $errors['alt_mobile'] = 'Alternate mobile must be a valid 10-digit number starting with 6-9.';
        }

        if ($panNo !== null && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $panNo)) {
            $errors['pan_no'] = 'Invalid PAN format. Must be 5 letters, 4 digits, 1 letter (e.g. ABCDE1234F).';
        }

        if ($gstNo !== null) {
            if (!preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstNo)) {
                $errors['gst_no'] = 'Invalid GSTIN format (15 characters, e.g. 27AAAAA0000A1Z5).';
            } elseif ($this->clientModel->existsByGst($gstNo)) {
                $errors['gst_no'] = 'Client with this GST number already exists.';
            } elseif ($state !== null && !empty($state)) {
                $expectedCodes = self::getGstStateCodes($state);
                $gstPrefix = substr($gstNo, 0, 2);
                if (!empty($expectedCodes) && !in_array($gstPrefix, $expectedCodes, true)) {
                    $errors['gst_no'] = "GSTIN state code ({$gstPrefix}) does not match selected state ({$state}).";
                }
            }
        }

        if ($website !== null) {
            $urlCheck = (str_starts_with($website, 'http://') || str_starts_with($website, 'https://'))
                ? $website
                : 'https://' . $website;
            if (!filter_var($urlCheck, FILTER_VALIDATE_URL)) {
                $errors['website'] = 'Please enter a valid website URL.';
            }
        }

        if ($addressLine1 === '') {
            $errors['address_line1'] = 'Address line 1 is required.';
        }

        if ($pincode === '') {
            $errors['pincode'] = 'Pincode is required.';
        } elseif (!preg_match('/^\d{6}$/', $pincode)) {
            $errors['pincode'] = 'Pincode must be exactly 6 digits.';
        }

        if ($city === '') {
            $errors['city'] = 'City is required.';
        }

        if ($state === '') {
            $errors['state'] = 'State is required.';
        }

        if (!$consentGiven) {
            $errors['consent_given'] = 'Client consent is mandatory to register and store records.';
        }

        // 3. Document files validation
        $normalizedFiles = $this->extractAndValidateFiles($files, $errors);

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Validation failed. Please correct the highlighted errors.');
        }

        // 4. Sales users: force assigned_to = self
        $canViewAll = PermissionService::can('client.view_all');
        if (!$canViewAll) {
            $assignedTo = $userId;
        } else {
            $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : $userId;
        }

        // 5. DB Transaction & Concurrency-Safe Code Generation
        $pdo = $this->clientModel->getPdo();
        $pdo->beginTransaction();

        $savedFilePaths = [];
        $clientDir = null;

        try {
            $year = (int)date('Y');
            $clientCode = $this->clientModel->generateClientCode($year);

            $clientRecord = [
                'client_code' => $clientCode,
                'client_type' => $clientType,
                'name' => $name,
                'contact_person' => $clientType === 'company' ? $contactPerson : null,
                'email' => $email,
                'mobile' => $mobile,
                'alt_mobile' => $altMobile,
                'gst_no' => $gstNo,
                'pan_no' => $panNo,
                'industry' => $industry,
                'company_size' => $companySize,
                'website' => $website,
                'address_line1' => $addressLine1,
                'address_line2' => $addressLine2,
                'city' => $city,
                'state' => $state,
                'pincode' => $pincode,
                'country' => $country,
                'lead_source' => $leadSource,
                'assigned_to' => $assignedTo,
                'status' => $status,
                'tags' => $tags,
                'notes' => $notes,
                'consent_given' => 1,
                'consent_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ];

            $clientId = (int)$this->clientModel->insert($clientRecord);

            // Handle file storage and client_documents table
            if (!empty($normalizedFiles)) {
                $clientDir = $this->uploadBasePath . '/' . $clientId;
                if (!is_dir($clientDir) && !mkdir($clientDir, 0755, true) && !is_dir($clientDir)) {
                    throw new RuntimeException("Failed to create storage directory for client documents.");
                }

                foreach ($normalizedFiles as $file) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
                    $destPath = $clientDir . '/' . $storedName;

                    if (is_uploaded_file($file['tmp_name'])) {
                        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                            throw new RuntimeException("Failed to move uploaded document: " . $file['name']);
                        }
                    } else {
                        if (!copy($file['tmp_name'], $destPath)) {
                            throw new RuntimeException("Failed to save document file: " . $file['name']);
                        }
                    }

                    $savedFilePaths[] = $destPath;

                    $this->docModel->createDocument([
                        'client_id' => $clientId,
                        'original_name' => basename($file['name']),
                        'stored_name' => $storedName,
                        'mime_type' => $file['mime'],
                        'size_bytes' => $file['size'],
                        'uploaded_by' => $userId,
                    ]);
                }
            }

            // Write activity log
            $logClientRecord = $clientRecord;
            if (isset($logClientRecord['pan_no']) && $logClientRecord['pan_no'] !== null) {
                $logClientRecord['pan_no'] = Crypto::maskPan($panNo);
            }

            $this->activityLog->log(
                $userId,
                'client',
                $clientId,
                'create',
                $logClientRecord,
                null,
                $ip,
                $userAgent
            );

            $pdo->commit();

            Cache::forgetByPrefix('dashboard:stats');

            return [
                'id' => $clientId,
                'client_code' => $clientCode,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($savedFilePaths as $filePath) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            if ($clientDir !== null && is_dir($clientDir)) {
                @rmdir($clientDir);
            }

            throw $e;
        }
    }

    /**
     * Get document metadata and absolute path after verifying authorization.
     *
     * @return array{file_path: string, original_name: string, mime_type: string, size_bytes: int}
     */
    public function getDocument(int|string $clientId, int|string $docId, ?int $userId = null): array
    {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        if ($userId <= 0) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        $canViewAll = PermissionService::can('client.view_all');
        $canViewOwn = PermissionService::can('client.view_own');

        if (!$canViewAll && !$canViewOwn) {
            throw new RuntimeException("Forbidden: insufficient permissions", 403);
        }

        $client = $this->clientModel->findScoped($clientId, $userId, $canViewAll);
        if (!$client) {
            $unscoped = $this->clientModel->find($clientId);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to access documents for this client", 403);
            }
            throw new RuntimeException("Client not found", 404);
        }

        $doc = $this->docModel->findByClientAndId($clientId, $docId);
        if (!$doc) {
            throw new RuntimeException("Document not found.", 404);
        }

        $filePath = $this->uploadBasePath . '/' . $clientId . '/' . $doc['stored_name'];
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found on server storage.", 404);
        }

        return [
            'file_path' => $filePath,
            'original_name' => (string)$doc['original_name'],
            'mime_type' => (string)$doc['mime_type'],
            'size_bytes' => (int)$doc['size_bytes'],
        ];
    }

    /**
     * Get lookups data: industries, states, lead sources, active staff.
     *
     * @return array<string, mixed>
     */
    public function getLookups(): array
    {
        /** @var array{industries: list<string>, states: list<string>, lead_sources: list<string>} $staticLookups */
        $staticLookups = Cache::remember('crm:lookups:static', 86400, static function (): array {
            return [
                'industries' => [
                    'IT & Software',
                    'Manufacturing',
                    'Healthcare & Pharma',
                    'Financial Services',
                    'Retail & E-commerce',
                    'Real Estate & Construction',
                    'Professional Consulting',
                    'Education & Training',
                    'Logistics & Supply Chain',
                    'Other',
                ],
                'states' => [
                    'Andhra Pradesh',
                    'Arunachal Pradesh',
                    'Assam',
                    'Bihar',
                    'Chhattisgarh',
                    'Goa',
                    'Gujarat',
                    'Haryana',
                    'Himachal Pradesh',
                    'Jharkhand',
                    'Karnataka',
                    'Kerala',
                    'Madhya Pradesh',
                    'Maharashtra',
                    'Manipur',
                    'Meghalaya',
                    'Mizoram',
                    'Nagaland',
                    'Odisha',
                    'Punjab',
                    'Rajasthan',
                    'Sikkim',
                    'Tamil Nadu',
                    'Telangana',
                    'Tripura',
                    'Uttar Pradesh',
                    'Uttarakhand',
                    'West Bengal',
                    'Andaman and Nicobar Islands',
                    'Chandigarh',
                    'Dadra and Nagar Haveli and Daman and Diu',
                    'Delhi',
                    'Jammu and Kashmir',
                    'Ladakh',
                    'Lakshadweep',
                    'Puducherry',
                ],
                'lead_sources' => [
                    'Website',
                    'Referral',
                    'Cold Call',
                    'Social Media',
                    'Exhibition',
                    'Partner',
                    'Other',
                ],
            ];
        });

        $staffUsers = $this->userModel->where(['is_active' => 1]);
        $staff = array_map(static fn($u) => [
            'id' => (int)$u['id'],
            'name' => (string)$u['name'],
            'email' => (string)$u['email'],
        ], $staffUsers);

        return [
            'industries' => $staticLookups['industries'],
            'states' => $staticLookups['states'],
            'lead_sources' => $staticLookups['lead_sources'],
            'staff' => $staff,
        ];
    }

    /**
     * @param array<string, mixed> $files
     * @param array<string, string> $errors
     * @return array<int, array{name: string, tmp_name: string, size: int, mime: string}>
     */
    private function extractAndValidateFiles(array $files, array &$errors): array
    {
        $fileList = [];

        $raw = $files['documents'] ?? $files['documents[]'] ?? null;
        if ($raw === null || !is_array($raw)) {
            return [];
        }

        if (isset($raw['name']) && is_array($raw['name'])) {
            $count = count($raw['name']);
            for ($i = 0; $i < $count; $i++) {
                if (
                    isset($raw['error'][$i]) &&
                    $raw['error'][$i] !== UPLOAD_ERR_NO_FILE &&
                    !empty($raw['tmp_name'][$i])
                ) {
                    $fileList[] = [
                        'name' => (string)$raw['name'][$i],
                        'tmp_name' => (string)$raw['tmp_name'][$i],
                        'size' => (int)($raw['size'][$i] ?? 0),
                        'error' => (int)$raw['error'][$i],
                    ];
                }
            }
        } elseif (isset($raw['name']) && is_string($raw['name'])) {
            if (isset($raw['error']) && $raw['error'] !== UPLOAD_ERR_NO_FILE && !empty($raw['tmp_name'])) {
                $fileList[] = [
                    'name' => (string)$raw['name'],
                    'tmp_name' => (string)$raw['tmp_name'],
                    'size' => (int)($raw['size'] ?? 0),
                    'error' => (int)$raw['error'],
                ];
            }
        } elseif (is_array($raw) && !empty($raw)) {
            foreach ($raw as $item) {
                if (is_array($item) && !empty($item['tmp_name'])) {
                    $fileList[] = [
                        'name' => (string)($item['name'] ?? 'document'),
                        'tmp_name' => (string)$item['tmp_name'],
                        'size' => (int)($item['size'] ?? 0),
                        'error' => (int)($item['error'] ?? UPLOAD_ERR_OK),
                    ];
                }
            }
        }

        if (count($fileList) > 3) {
            $errors['documents'] = 'Maximum 3 documents allowed.';
            return [];
        }

        $validatedFiles = [];
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        $maxBytes = 5 * 1024 * 1024; // 5 MB

        foreach ($fileList as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors['documents'] = 'Upload error encountered for file: ' . htmlspecialchars($file['name']);
                break;
            }

            if ($file['size'] > $maxBytes) {
                $errors['documents'] = "File '{$file['name']}' exceeds maximum allowed size of 5 MB.";
                break;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts, true)) {
                $errors['documents'] = "File '{$file['name']}' format not supported. Only PDF, JPG, and PNG are allowed.";
                break;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';

            if (!in_array($realMime, $allowedMimes, true)) {
                $errors['documents'] = "File '{$file['name']}' has an invalid content type ({$realMime}).";
                break;
            }

            $validatedFiles[] = [
                'name' => $file['name'],
                'tmp_name' => $file['tmp_name'],
                'size' => $file['size'],
                'mime' => $realMime,
            ];
        }

        return $validatedFiles;
    }

    /**
     * Anonymize client personal data pursuant to DPDP Act 2023 (Right to Erasure).
     * Restrictable to admin users. Removes physical documents, redacts PII, and soft deletes.
     */
    public function anonymizeClient(
        int|string $id,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): bool {
        Session::start();
        if ($userId === null) {
            $userId = (int)Session::get('user_id');
        }

        if ($userId <= 0) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        $isAdmin = (PermissionService::getRole() === 'admin') || PermissionService::can('user.manage');
        if (!$isAdmin) {
            throw new RuntimeException("Forbidden: Only administrators can anonymize client records.", 403);
        }

        $client = $this->clientModel->find($id);
        if (!$client) {
            throw new RuntimeException("Client not found.", 404);
        }

        $clientId = (int)$id;

        // 1. Purge physical files in storage/uploads/clients/{id}
        $clientDir = $this->uploadBasePath . '/' . $clientId;
        if (is_dir($clientDir)) {
            $files = glob($clientDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($clientDir);
        }

        // 2. Redact client_documents metadata
        $pdo = $this->clientModel->getPdo();
        $stmt = $pdo->prepare("UPDATE `client_documents` SET `stored_name` = '', `original_name` = '[Redacted]', `deleted_at` = ? WHERE `client_id` = ?");
        $stmt->execute([date('Y-m-d H:i:s'), $clientId]);

        // 3. Anonymize/Pseudonymize all PII fields in clients table
        $randomSuffix = bin2hex(random_bytes(4));
        $anonymized = [
            'name' => 'Anonymized Client #' . $clientId,
            'contact_person' => null,
            'email' => "anonymized_{$clientId}_{$randomSuffix}@deleted.local",
            'mobile' => '99' . str_pad((string)$clientId, 8, '0', STR_PAD_LEFT),
            'alt_mobile' => null,
            'gst_no' => null,
            'pan_no' => null,
            'website' => null,
            'address_line1' => '[Redacted for Privacy]',
            'address_line2' => null,
            'city' => '[Redacted]',
            'state' => '[Redacted]',
            'pincode' => '000000',
            'notes' => '[Redacted pursuant to DPDP Act Section 12 erasure request]',
            'tags' => null,
            'status' => 'inactive',
            'deleted_at' => date('Y-m-d H:i:s'),
        ];

        $updated = $this->clientModel->update($clientId, $anonymized);

        Cache::forgetByPrefix('dashboard:stats');

        // 4. Log audit trail without leaking erased PII
        $this->activityLog->log(
            $userId,
            'client',
            $clientId,
            'dpdp_anonymize',
            ['action' => 'dpdp_erasure', 'timestamp' => date('Y-m-d H:i:s')],
            ['client_code' => $client['client_code']],
            $ip,
            $userAgent
        );

        return $updated;
    }
}
