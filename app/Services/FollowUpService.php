<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Services\Cache\Cache;
use RuntimeException;

class FollowUpService
{
    private FollowUp $followUpModel;
    private Client $clientModel;
    private ?Lead $leadModel = null;
    private ActivityLog $activityLog;

    public function __construct(
        ?FollowUp $followUpModel = null,
        ?Client $clientModel = null,
        mixed $param3 = null,
        ?Lead $leadModel = null
    ) {
        $this->followUpModel = $followUpModel ?? new FollowUp();
        $this->clientModel = $clientModel ?? new Client();

        if (!class_exists(Lead::class)) {
            $leadFile = dirname(__DIR__) . '/Models/Lead.php';
            if (file_exists($leadFile)) {
                require_once $leadFile;
            }
        }

        if ($param3 instanceof Lead) {
            $this->leadModel = $param3;
            $this->activityLog = new ActivityLog();
        } elseif ($param3 instanceof ActivityLog) {
            $this->activityLog = $param3;
            $this->leadModel = $leadModel ?? (class_exists(Lead::class) ? new Lead() : null);
        } else {
            $this->activityLog = new ActivityLog();
            $this->leadModel = $leadModel ?? (class_exists(Lead::class) ? new Lead() : null);
        }
    }

    /**
     * List follow-ups with server-side pagination, search, tab filters, and scoping.
     */
    public function list(array $filters): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('client.view_all') || PermissionService::can('lead.view_all');

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min((int)($filters['per_page'] ?? 20), 100));
        $sortBy = (string)($filters['sort_by'] ?? 'due_at');
        $sortDir = (string)($filters['sort_dir'] ?? 'ASC');

        return $this->followUpModel->search($filters, $userId, $canViewAll, $page, $perPage, $sortBy, $sortDir);
    }

    /**
     * Get all follow-ups for a specific client.
     */
    public function getByClient(int|string $clientId): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('client.view_all');

        $client = $this->clientModel->findScoped($clientId, $userId, $canViewAll);
        if (!$client) {
            $unscoped = $this->clientModel->find($clientId);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to view follow-ups for this client", 403);
            }
            throw new RuntimeException("Client not found", 404);
        }

        return $this->followUpModel->getByClient($clientId, $userId, $canViewAll);
    }

    /**
     * Get all follow-ups for a specific lead.
     */
    public function getByLead(int|string $leadId): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('lead.view_all');

        $lead = $this->leadModel->findScoped($leadId, $userId, $canViewAll);
        if (!$lead) {
            $unscoped = $this->leadModel->find($leadId);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to view follow-ups for this lead", 403);
            }
            throw new RuntimeException("Lead not found", 404);
        }

        return $this->followUpModel->getByLead($leadId, $userId, $canViewAll);
    }

    /**
     * Find single follow-up respecting scoping.
     */
    public function get(int|string $id): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('client.view_all') || PermissionService::can('lead.view_all');

        $followUp = $this->followUpModel->findScoped($id, $userId, $canViewAll);
        if (!$followUp) {
            $unscoped = $this->followUpModel->find($id);
            if ($unscoped !== null) {
                throw new RuntimeException("Forbidden: you do not have permission to access this follow-up", 403);
            }
            throw new RuntimeException("Follow-up not found", 404);
        }

        return $followUp;
    }

    /**
     * Create a follow-up for a client or lead.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, ?int $clientId = null, ?string $ip = null, ?string $userAgent = null): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAllClients = PermissionService::can('client.view_all');
        $canViewAllLeads = PermissionService::can('lead.view_all');

        $targetClientId = $clientId ?? (!empty($data['client_id']) ? (int)$data['client_id'] : null);
        $targetLeadId = !empty($data['lead_id']) ? (int)$data['lead_id'] : null;

        if (!$targetClientId && !$targetLeadId) {
            throw new ValidationException(['target' => 'Please select a valid client or lead for this follow-up.']);
        }

        $entityType = 'client';
        $entityId = 0;
        $defaultAssignee = $userId;

        if ($targetClientId) {
            $client = $this->clientModel->findScoped($targetClientId, $userId, $canViewAllClients);
            if (!$client) {
                throw new RuntimeException("Client not found or access denied.", 404);
            }
            $entityType = 'client';
            $entityId = $targetClientId;
            if (!empty($client['assigned_to'])) {
                $defaultAssignee = (int)$client['assigned_to'];
            }
        } elseif ($targetLeadId) {
            $lead = $this->leadModel->findScoped($targetLeadId, $userId, $canViewAllLeads);
            if (!$lead) {
                throw new RuntimeException("Lead not found or access denied.", 404);
            }
            $entityType = 'lead';
            $entityId = $targetLeadId;
            if (!empty($lead['assigned_to'])) {
                $defaultAssignee = (int)$lead['assigned_to'];
            }
        }

        $errors = [];
        $dueAt = trim((string)($data['due_at'] ?? $data['due_date'] ?? $data['follow_up_date'] ?? ''));
        if ($dueAt === '') {
            $errors['due_at'] = 'Due date and time is required.';
        } elseif (strtotime($dueAt) === false) {
            $errors['due_at'] = 'Please enter a valid date and time.';
        } else {
            $dueAt = date('Y-m-d H:i:s', strtotime($dueAt));
        }

        $validTypes = ['call', 'whatsapp', 'visit', 'meeting', 'email'];
        $type = strtolower(trim((string)($data['type'] ?? 'call')));
        if (!in_array($type, $validTypes, true)) {
            $errors['type'] = 'Mode must be Call, WhatsApp, Visit, Meeting, or Email.';
        }

        $status = strtolower(trim((string)($data['status'] ?? 'pending')));
        if ($status === 'completed') {
            $status = 'done';
        } elseif (!in_array($status, ['pending', 'done', 'missed'], true)) {
            $status = 'pending';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Assigned user
        $assignedUserId = $userId;
        $requestedUser = !empty($data['user_id']) ? (int)$data['user_id'] : (!empty($data['assigned_to']) ? (int)$data['assigned_to'] : null);
        if (($canViewAllClients || $canViewAllLeads) && $requestedUser !== null) {
            $assignedUserId = $requestedUser;
        } else {
            $assignedUserId = $defaultAssignee ?: $userId;
        }

        $notes = trim((string)($data['notes'] ?? ''));
        $remarks = trim((string)($data['remarks'] ?? ''));

        $insertData = [
            'user_id' => $assignedUserId,
            'due_at' => $dueAt,
            'type' => $type,
            'notes' => $notes !== '' ? $notes : null,
            'status' => $status,
        ];

        if ($targetClientId !== null) {
            $insertData['client_id'] = $targetClientId;
        }
        if ($targetLeadId !== null) {
            $insertData['lead_id'] = $targetLeadId;
        }
        if ($remarks !== '') {
            $insertData['remarks'] = $remarks;
        }

        if (!empty($data['outcome'])) {
            $validOutcomes = ['connected', 'not_picked', 'busy', 'switched_off', 'call_back'];
            $out = strtolower(trim((string)$data['outcome']));
            if (in_array($out, $validOutcomes, true)) {
                $insertData['outcome'] = $out;
            }
        }

        $newId = (int)$this->followUpModel->insert($insertData);

        // Activity log
        $clientIp = $ip ?? '127.0.0.1';
        $clientUa = $userAgent ?? '';
        $this->activityLog->log(
            $userId,
            $entityType,
            $entityId,
            'followup_created',
            [
                'followup_id' => $newId,
                'type' => $type,
                'due_at' => $dueAt,
                'status' => $status,
                'notes' => $notes,
            ],
            null,
            $clientIp,
            $clientUa
        );

        if (class_exists(Cache::class)) {
            Cache::forgetByPrefix('dashboard:stats');
        }

        return $this->get($newId);
    }

    /**
     * Update follow-up details or complete it with outcome & remarks.
     */
    public function update(int|string $id, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');

        $existing = $this->get($id);
        $clientId = !empty($existing['client_id']) ? (int)$existing['client_id'] : null;
        $leadId = !empty($existing['lead_id']) ? (int)$existing['lead_id'] : null;
        $entityType = $leadId ? 'lead' : 'client';
        $entityId = $leadId ?: ($clientId ?: 0);

        $updateData = [];
        $errors = [];

        // Outcome handling
        $outcomeNote = trim((string)($data['outcome_note'] ?? ''));
        $rawOutcome = trim((string)($data['outcome'] ?? ''));
        $validOutcomes = ['connected', 'not_picked', 'busy', 'switched_off', 'call_back'];
        $outcome = '';

        if ($rawOutcome !== '') {
            if (in_array(strtolower($rawOutcome), $validOutcomes, true)) {
                $outcome = strtolower($rawOutcome);
            } elseif ($outcomeNote === '') {
                $outcomeNote = $rawOutcome;
            }
        }

        $targetStatus = isset($data['status']) ? strtolower(trim((string)$data['status'])) : null;

        if ($outcome !== '' || $outcomeNote !== '' || $targetStatus === 'done') {
            $targetStatus = 'done';
            $updateData['status'] = 'done';

            if ($outcome !== '') {
                $updateData['outcome'] = $outcome;
            }

            if ($outcomeNote !== '') {
                $currentNotes = (string)($existing['notes'] ?? '');
                $updateData['notes'] = $currentNotes !== ''
                    ? $currentNotes . "\n\n[Outcome]: " . $outcomeNote
                    : "[Outcome]: " . $outcomeNote;
            }
        } elseif ($targetStatus !== null) {
            if (!in_array($targetStatus, ['pending', 'done', 'missed'], true)) {
                $errors['status'] = 'Invalid status value.';
            } else {
                $updateData['status'] = $targetStatus;
            }
        }

        if (isset($data['remarks'])) {
            $updateData['remarks'] = trim((string)$data['remarks']) ?: null;
        }

        if (isset($data['due_at'])) {
            $dueAtStr = trim((string)$data['due_at']);
            if ($dueAtStr === '') {
                $errors['due_at'] = 'Due date is required.';
            } elseif (strtotime($dueAtStr) === false) {
                $errors['due_at'] = 'Invalid due date.';
            } else {
                $updateData['due_at'] = date('Y-m-d H:i:s', strtotime($dueAtStr));
            }
        }

        if (isset($data['type'])) {
            $validTypes = ['call', 'whatsapp', 'visit', 'meeting', 'email'];
            $type = strtolower(trim((string)$data['type']));
            if (!in_array($type, $validTypes, true)) {
                $errors['type'] = 'Invalid interaction type.';
            } else {
                $updateData['type'] = $type;
            }
        }

        if (isset($data['notes']) && !array_key_exists('notes', $updateData)) {
            $updateData['notes'] = trim((string)$data['notes']);
        }

        // Auto-prompt next follow-up handling
        $nextFollowUpAt = trim((string)($data['next_follow_up_at'] ?? $data['next_follow_up_date'] ?? ''));
        if ($nextFollowUpAt !== '') {
            if (strtotime($nextFollowUpAt) === false) {
                $errors['next_follow_up_at'] = 'Invalid next follow-up date and time.';
            } else {
                $updateData['next_follow_up_at'] = date('Y-m-d H:i:s', strtotime($nextFollowUpAt));
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        if (empty($updateData)) {
            return $existing;
        }

        $this->followUpModel->update($id, $updateData);

        // If next follow-up requested upon completion, auto-create the next follow-up!
        $nextFollowUpId = null;
        if (!empty($updateData['next_follow_up_at'])) {
            $nextData = [
                'client_id' => $clientId,
                'lead_id' => $leadId,
                'user_id' => $existing['user_id'],
                'due_at' => $updateData['next_follow_up_at'],
                'type' => $data['next_follow_up_type'] ?? $data['next_type'] ?? $existing['type'] ?? 'call',
                'notes' => $data['next_follow_up_notes'] ?? $data['next_notes'] ?? sprintf('Next follow-up following: %s', $outcome ?: 'previous interaction'),
            ];
            $createdNext = $this->create($nextData, null, $ip, $userAgent);
            $nextFollowUpId = (int)($createdNext['id'] ?? 0);
        }

        // Calculate diff for activity log
        $diffOld = [];
        $diffNew = [];
        foreach ($updateData as $k => $v) {
            $oldVal = $existing[$k] ?? null;
            if ((string)$oldVal !== (string)$v) {
                $diffOld[$k] = $oldVal;
                $diffNew[$k] = $v;
            }
        }

        if (!empty($diffNew)) {
            $action = ($diffNew['status'] ?? '') === 'done' ? 'followup_done' : 'followup_updated';
            $clientIp = $ip ?? '127.0.0.1';
            $clientUa = $userAgent ?? '';
            $this->activityLog->log(
                $userId,
                $entityType,
                $entityId,
                $action,
                array_merge(['followup_id' => $id], $diffNew),
                $diffOld,
                $clientIp,
                $clientUa
            );

            if (class_exists(Cache::class)) {
                Cache::forgetByPrefix('dashboard:stats');
            }
        }

        $res = $this->get($id);
        if ($nextFollowUpId) {
            $res['next_follow_up_id'] = $nextFollowUpId;
        }
        return $res;
    }

    /**
     * Log outcome for a follow-up and optionally auto-prompt/schedule next follow-up.
     */
    public function logOutcome(int|string $id, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $payload = array_merge($data, [
            'status' => 'done',
        ]);
        return $this->update($id, $payload, $ip, $userAgent);
    }

    /**
     * Soft delete follow-up with activity logging.
     */
    public function delete(int|string $id, ?string $ip = null, ?string $userAgent = null): bool
    {
        Session::start();
        $userId = (int)Session::get('user_id');

        $existing = $this->get($id);
        $clientId = !empty($existing['client_id']) ? (int)$existing['client_id'] : null;
        $leadId = !empty($existing['lead_id']) ? (int)$existing['lead_id'] : null;
        $entityType = $leadId ? 'lead' : 'client';
        $entityId = $leadId ?: ($clientId ?: 0);

        $success = $this->followUpModel->softDelete($id);
        if ($success) {
            if (class_exists(Cache::class)) {
            Cache::forgetByPrefix('dashboard:stats');
        }

            $clientIp = $ip ?? '127.0.0.1';
            $clientUa = $userAgent ?? '';
            $this->activityLog->log(
                $userId,
                $entityType,
                $entityId,
                'followup_deleted',
                null,
                [
                    'followup_id' => $id,
                    'type' => $existing['type'],
                    'due_at' => $existing['due_at'],
                    'notes' => $existing['notes'],
                ],
                $clientIp,
                $clientUa
            );
        }

        return $success;
    }
}
