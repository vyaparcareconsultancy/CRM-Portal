<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Helpers\Crypto;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientComplianceDetail;
use App\Models\ClientService;
use App\Models\Service;
use App\Models\ServiceWorkTracker;
use RuntimeException;

class ClientComplianceService
{
    private Client $clientModel;
    private Service $serviceModel;
    private ClientService $clientServiceModel;
    private ClientComplianceDetail $complianceModel;
    private ServiceWorkTracker $workTrackerModel;
    private ActivityLog $activityLog;

    public function __construct(
        ?Client $clientModel = null,
        ?Service $serviceModel = null,
        ?ClientService $clientServiceModel = null,
        ?ClientComplianceDetail $complianceModel = null,
        ?ServiceWorkTracker $workTrackerModel = null,
        ?ActivityLog $activityLog = null
    ) {
        $this->clientModel = $clientModel ?? new Client();
        $this->serviceModel = $serviceModel ?? new Service();
        $this->clientServiceModel = $clientServiceModel ?? new ClientService();
        $this->complianceModel = $complianceModel ?? new ClientComplianceDetail();
        $this->workTrackerModel = $workTrackerModel ?? new ServiceWorkTracker();
        $this->activityLog = $activityLog ?? new ActivityLog();
    }

    // ==========================================
    // 1. Client Services (Subscriptions)
    // ==========================================

    /**
     * List services assigned to a client.
     */
    public function listClientServices(int $clientId): array
    {
        $this->ensureCanViewClient($clientId);
        return $this->clientServiceModel->getByClientId($clientId);
    }

    /**
     * Get single client service subscription details.
     */
    public function getClientService(int $id): array
    {
        $item = $this->clientServiceModel->findWithDetails($id);
        if (!$item) {
            throw new RuntimeException("Client service not found.", 404);
        }
        $this->ensureCanViewClient((int)$item['client_id']);
        return $item;
    }

    /**
     * Add a service to client profile.
     */
    public function addClientService(int $clientId, array $data): array
    {
        $this->ensureCanManageClientServices($clientId);

        $client = $this->clientModel->find($clientId);
        if (!$client) {
            throw new RuntimeException("Client not found.", 404);
        }

        $serviceId = (int)($data['service_id'] ?? 0);
        $service = $this->serviceModel->find($serviceId);
        if (!$service) {
            throw new ValidationException("Invalid service selected.", ['service_id' => 'Please select a valid service.']);
        }

        $startDate = trim((string)($data['start_date'] ?? ''));
        if ($startDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d');
        }

        $frequency = strtolower(trim((string)($data['frequency'] ?? $service['frequency'] ?? 'monthly')));
        if (!in_array($frequency, ['one_time', 'monthly', 'quarterly', 'yearly'], true)) {
            $frequency = 'monthly';
        }

        $fee = isset($data['fee']) ? (float)$data['fee'] : (float)$service['default_fee'];
        if ($fee < 0) {
            throw new ValidationException("Fee cannot be negative.", ['fee' => 'Fee must be greater than or equal to 0.']);
        }

        $status = strtolower(trim((string)($data['status'] ?? 'active')));
        if (!in_array($status, ['active', 'paused', 'completed', 'cancelled'], true)) {
            $status = 'active';
        }

        $assignedAccountantId = !empty($data['assigned_accountant_id']) ? (int)$data['assigned_accountant_id'] : null;

        $id = $this->clientServiceModel->insert([
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'assigned_accountant_id' => $assignedAccountantId,
            'fee' => $fee,
            'frequency' => $frequency,
            'start_date' => $startDate,
            'status' => $status,
            'notes' => !empty($data['notes']) ? trim((string)$data['notes']) : null,
        ]);

        $this->logActivity('client_service', (int)$id, 'create', [
            'client_id' => $clientId,
            'service_name' => $service['name'],
            'fee' => $fee,
            'status' => $status,
        ]);

        return $this->getClientService((int)$id);
    }

    /**
     * Update client service (status, fee, accountant, etc.).
     */
    public function updateClientService(int $id, array $data): array
    {
        $existing = $this->getClientService($id);
        $this->ensureCanManageClientServices((int)$existing['client_id']);

        $updates = [];
        if (isset($data['fee'])) {
            $fee = (float)$data['fee'];
            if ($fee < 0) {
                throw new ValidationException("Fee cannot be negative.", ['fee' => 'Fee must be at least 0.']);
            }
            $updates['fee'] = $fee;
        }

        if (isset($data['frequency'])) {
            $freq = strtolower(trim((string)$data['frequency']));
            if (in_array($freq, ['one_time', 'monthly', 'quarterly', 'yearly'], true)) {
                $updates['frequency'] = $freq;
            }
        }

        if (isset($data['status'])) {
            $status = strtolower(trim((string)$data['status']));
            if (in_array($status, ['active', 'paused', 'completed', 'cancelled'], true)) {
                $updates['status'] = $status;
            }
        }

        if (array_key_exists('assigned_accountant_id', $data)) {
            $updates['assigned_accountant_id'] = !empty($data['assigned_accountant_id']) ? (int)$data['assigned_accountant_id'] : null;
        }

        if (isset($data['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$data['start_date']))) {
            $updates['start_date'] = trim((string)$data['start_date']);
        }

        if (array_key_exists('notes', $data)) {
            $updates['notes'] = !empty($data['notes']) ? trim((string)$data['notes']) : null;
        }

        if (!empty($updates)) {
            $this->clientServiceModel->update($id, $updates);
            $this->logActivity('client_service', $id, 'update', $updates, $existing);
        }

        return $this->getClientService($id);
    }

    /**
     * Delete client service.
     */
    public function deleteClientService(int $id): bool
    {
        $existing = $this->getClientService($id);
        $this->ensureCanManageClientServices((int)$existing['client_id']);

        $deleted = $this->clientServiceModel->delete($id);
        if ($deleted) {
            $this->logActivity('client_service', $id, 'delete', null, $existing);
        }
        return $deleted;
    }

    // ==========================================
    // 2. Compliance Details & Encrypted Portal Notes
    // ==========================================

    /**
     * Retrieve compliance details for a client.
     * Portal credentials/passwords are only revealed when requested by Admin or Accountant.
     */
    public function getComplianceDetails(int $clientId, bool $revealCredentials = false): array
    {
        $this->ensureCanViewClient($clientId);

        $client = $this->clientModel->find($clientId);
        if (!$client) {
            throw new RuntimeException("Client not found.", 404);
        }

        $record = $this->complianceModel->getByClientId($clientId);

        $gstin = $record['gstin'] ?? $client['gst_no'] ?? null;
        $pan = $record['pan'] ?? $client['pan_no'] ?? null;
        $gstFilingType = $record['gst_filing_type'] ?? 'monthly';
        $tan = $record['tan'] ?? null;
        $cinLlpin = $record['cin_llpin'] ?? null;
        $pfEsiCodes = $record['pf_esi_codes'] ?? null;
        $financialYear = $record['financial_year'] ?? date('Y') . '-' . (date('Y') + 1);
        $portalNotes = $record['portal_notes'] ?? null;

        $hasEncryptedCredentials = !empty($record['portal_credentials_encrypted']);
        $revealedCredentials = null;

        if ($revealCredentials) {
            // Enforce strict access control: Only Admin or Accountant (or user with client.view_tax)
            $this->ensureCanViewTaxSecrets();

            if ($hasEncryptedCredentials) {
                $revealedCredentials = Crypto::decryptSecret((string)$record['portal_credentials_encrypted']);
            }

            // Create immutable audit log entry for credential reveal
            $this->logActivity('client_compliance', $clientId, 'reveal_portal_credential', [
                'client_code' => $client['client_code'],
                'client_name' => $client['name'],
                'revealed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'client_id' => $clientId,
            'client_code' => $client['client_code'],
            'gstin' => $gstin,
            'gst_filing_type' => $gstFilingType,
            'pan' => $pan ? Crypto::maskPan($pan) : null,
            'pan_raw' => $this->canViewTaxSecrets() ? ($pan ? Crypto::decryptPan($pan) : null) : null,
            'tan' => $tan,
            'cin_llpin' => $cinLlpin,
            'pf_esi_codes' => $pfEsiCodes,
            'financial_year' => $financialYear,
            'portal_notes' => $portalNotes,
            'has_portal_credentials' => $hasEncryptedCredentials,
            'portal_credentials' => $revealedCredentials,
        ];
    }

    /**
     * Save compliance details for a client.
     * Encrypts portal passwords/credentials before writing to database.
     */
    public function saveComplianceDetails(int $clientId, array $data): array
    {
        $this->ensureCanManageClientServices($clientId);

        $client = $this->clientModel->find($clientId);
        if (!$client) {
            throw new RuntimeException("Client not found.", 404);
        }

        $existing = $this->complianceModel->getByClientId($clientId);

        $saveData = [
            'gstin' => !empty($data['gstin']) ? strtoupper(trim((string)$data['gstin'])) : null,
            'gst_filing_type' => in_array($data['gst_filing_type'] ?? '', ['monthly', 'qrmp', 'composition', 'none'], true)
                ? $data['gst_filing_type']
                : 'monthly',
            'pan' => !empty($data['pan']) ? strtoupper(trim((string)$data['pan'])) : null,
            'tan' => !empty($data['tan']) ? strtoupper(trim((string)$data['tan'])) : null,
            'cin_llpin' => !empty($data['cin_llpin']) ? strtoupper(trim((string)$data['cin_llpin'])) : null,
            'pf_esi_codes' => !empty($data['pf_esi_codes']) ? trim((string)$data['pf_esi_codes']) : null,
            'financial_year' => !empty($data['financial_year']) ? trim((string)$data['financial_year']) : date('Y') . '-' . (date('Y') + 1),
            'portal_notes' => !empty($data['portal_notes']) ? trim((string)$data['portal_notes']) : null,
        ];

        // If portal credentials are provided, encrypt them! Never store in plain text.
        if (array_key_exists('portal_credentials', $data) && $data['portal_credentials'] !== null) {
            $plainCredentials = trim((string)$data['portal_credentials']);
            if ($plainCredentials !== '') {
                $saveData['portal_credentials_encrypted'] = Crypto::encryptSecret($plainCredentials);
            } else {
                $saveData['portal_credentials_encrypted'] = null;
            }
        } elseif ($existing && isset($existing['portal_credentials_encrypted'])) {
            $saveData['portal_credentials_encrypted'] = $existing['portal_credentials_encrypted'];
        }

        $this->complianceModel->saveForClient($clientId, $saveData);

        // Also sync GSTIN and PAN back to the main client record if provided
        $clientUpdates = [];
        if (!empty($saveData['gstin']) && $saveData['gstin'] !== $client['gst_no']) {
            $clientUpdates['gst_no'] = $saveData['gstin'];
        }
        if (!empty($saveData['pan']) && $saveData['pan'] !== $client['pan_no']) {
            $clientUpdates['pan_no'] = Crypto::encryptPan($saveData['pan']);
        }
        if (!empty($clientUpdates)) {
            $this->clientModel->update($clientId, $clientUpdates);
        }

        $this->logActivity('client_compliance', $clientId, 'save', [
            'gstin' => $saveData['gstin'],
            'gst_filing_type' => $saveData['gst_filing_type'],
            'tan' => $saveData['tan'],
            'cin_llpin' => $saveData['cin_llpin'],
            'has_credentials' => !empty($saveData['portal_credentials_encrypted']),
        ]);

        return $this->getComplianceDetails($clientId, false);
    }

    // ==========================================
    // 3. Work Tracker per Service Period
    // ==========================================

    /**
     * List work tracker items for a client (optionally filtered by client_service_id).
     */
    public function listWorkTracker(int $clientId, ?int $clientServiceId = null): array
    {
        $this->ensureCanViewClient($clientId);

        if ($clientServiceId !== null && $clientServiceId > 0) {
            return $this->workTrackerModel->getByClientServiceId($clientServiceId);
        }

        return $this->workTrackerModel->getByClientId($clientId);
    }

    /**
     * Add a work tracker period entry (e.g. GST Return Sep-2026).
     */
    public function addWorkTrackerItem(int $clientId, int $clientServiceId, array $data): array
    {
        $this->ensureCanManageClientServices($clientId);

        $clientService = $this->clientServiceModel->find($clientServiceId);
        if (!$clientService || (int)$clientService['client_id'] !== $clientId) {
            throw new RuntimeException("Client service subscription not found.", 404);
        }

        $period = trim((string)($data['period'] ?? ''));
        if ($period === '') {
            throw new ValidationException("Period is required.", ['period' => 'Period cannot be empty (e.g. Sep-2026, Q2-2026).']);
        }

        $status = strtolower(trim((string)($data['status'] ?? 'pending')));
        if (!in_array($status, ['pending', 'data_received', 'filed', 'acknowledged'], true)) {
            $status = 'pending';
        }

        $ackNo = !empty($data['acknowledgment_no']) ? trim((string)$data['acknowledgment_no']) : null;
        $filingDate = !empty($data['filing_date']) ? trim((string)$data['filing_date']) : null;
        $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : ($clientService['assigned_accountant_id'] ?? null);
        $notes = !empty($data['notes']) ? trim((string)$data['notes']) : null;

        $id = $this->workTrackerModel->insert([
            'client_service_id' => $clientServiceId,
            'client_id' => $clientId,
            'period' => $period,
            'status' => $status,
            'acknowledgment_no' => $ackNo,
            'filing_date' => $filingDate,
            'assigned_to' => $assignedTo,
            'notes' => $notes,
        ]);

        $this->logActivity('work_tracker', (int)$id, 'create', [
            'client_id' => $clientId,
            'client_service_id' => $clientServiceId,
            'period' => $period,
            'status' => $status,
        ]);

        return $this->workTrackerModel->find((int)$id) ?: [];
    }

    /**
     * Update status, acknowledgment number, filing date of a work tracker period.
     */
    public function updateWorkTrackerItem(int $id, array $data): array
    {
        $existing = $this->workTrackerModel->find($id);
        if (!$existing) {
            throw new RuntimeException("Work tracker item not found.", 404);
        }

        $clientId = (int)$existing['client_id'];
        $this->ensureCanManageClientServices($clientId);

        $updates = [];

        if (isset($data['period']) && trim((string)$data['period']) !== '') {
            $updates['period'] = trim((string)$data['period']);
        }

        if (isset($data['status'])) {
            $status = strtolower(trim((string)$data['status']));
            if (in_array($status, ['pending', 'data_received', 'filed', 'acknowledged'], true)) {
                $updates['status'] = $status;
                // If moving to filed/acknowledged and filing_date is not set, set to today
                if (in_array($status, ['filed', 'acknowledged'], true) && empty($existing['filing_date']) && empty($data['filing_date'])) {
                    $updates['filing_date'] = date('Y-m-d');
                }
            }
        }

        if (array_key_exists('acknowledgment_no', $data)) {
            $updates['acknowledgment_no'] = !empty($data['acknowledgment_no']) ? trim((string)$data['acknowledgment_no']) : null;
        }

        if (array_key_exists('filing_date', $data)) {
            $updates['filing_date'] = !empty($data['filing_date']) ? trim((string)$data['filing_date']) : null;
        }

        if (array_key_exists('assigned_to', $data)) {
            $updates['assigned_to'] = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;
        }

        if (array_key_exists('notes', $data)) {
            $updates['notes'] = !empty($data['notes']) ? trim((string)$data['notes']) : null;
        }

        if (!empty($updates)) {
            $this->workTrackerModel->update($id, $updates);
            $this->logActivity('work_tracker', $id, 'update', $updates, $existing);
        }

        return $this->workTrackerModel->find($id) ?: [];
    }

    /**
     * Delete work tracker item.
     */
    public function deleteWorkTrackerItem(int $id): bool
    {
        $existing = $this->workTrackerModel->find($id);
        if (!$existing) {
            throw new RuntimeException("Work tracker item not found.", 404);
        }

        $this->ensureCanManageClientServices((int)$existing['client_id']);
        $deleted = $this->workTrackerModel->delete($id);
        if ($deleted) {
            $this->logActivity('work_tracker', $id, 'delete', null, $existing);
        }
        return $deleted;
    }

    // ==========================================
    // Security & Permission Enforcement
    // ==========================================

    private function ensureCanViewClient(int $clientId): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('user_role');

        if (!$userId) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        if ($userRole === 'admin') {
            return;
        }

        if (PermissionService::can('client.view_all')) {
            return;
        }

        if (PermissionService::can('client.view_own')) {
            $client = $this->clientModel->find($clientId);
            if ($client && ((int)$client['assigned_to'] === $userId || (int)$client['created_by'] === $userId)) {
                return;
            }
        }

        throw new RuntimeException("Forbidden: insufficient permissions to view this client.", 403);
    }

    private function ensureCanManageClientServices(int $clientId): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('user_role');

        if (!$userId) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        if ($userRole === 'admin') {
            return;
        }

        if (PermissionService::can('client_service.manage') || PermissionService::can('client.edit')) {
            return;
        }

        throw new RuntimeException("Forbidden: insufficient permissions to manage client services.", 403);
    }

    private function canViewTaxSecrets(): bool
    {
        Session::start();
        $userRole = (string)Session::get('user_role');
        return in_array($userRole, ['admin', 'accountant'], true);
    }

    private function ensureCanViewTaxSecrets(): void
    {
        if (!$this->canViewTaxSecrets()) {
            throw new RuntimeException("Forbidden: only Admin and Accountant can reveal portal credentials.", 403);
        }
    }

    private function logActivity(string $entityType, int $entityId, string $action, ?array $newValues = null, ?array $oldValues = null): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $request = Request::createFromGlobals();

        $this->activityLog->log(
            $userId > 0 ? $userId : null,
            $entityType,
            $entityId,
            $action,
            $newValues,
            $oldValues,
            $request->ip(),
            $request->userAgent()
        );
    }
}
