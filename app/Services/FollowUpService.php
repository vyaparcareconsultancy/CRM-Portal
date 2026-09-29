<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\FollowUp;
use App\Services\Cache\Cache;
use RuntimeException;

class FollowUpService
{
    private FollowUp $followUpModel;
    private Client $clientModel;
    private ActivityLog $activityLog;

    public function __construct(
        ?FollowUp $followUpModel = null,
        ?Client $clientModel = null,
        ?ActivityLog $activityLog = null
    ) {
        $this->followUpModel = $followUpModel ?? new FollowUp();
        $this->clientModel = $clientModel ?? new Client();
        $this->activityLog = $activityLog ?? new ActivityLog();
    }

    /**
     * List follow-ups with server-side pagination, search, tab filters, and scoping.
     */
    public function list(array $filters): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('client.view_all');

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

        // Check client accessibility
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
     * Find single follow-up respecting scoping.
     */
    public function get(int|string $id): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('client.view_all');

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
     * Create a follow-up.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data, ?int $clientId = null, ?string $ip = null, ?string $userAgent = null): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('client.view_all');

        $targetClientId = $clientId ?? (int)($data['client_id'] ?? 0);
        if ($targetClientId <= 0) {
            throw new ValidationException(['client_id' => 'Please select a valid client.']);
        }

        $client = $this->clientModel->findScoped($targetClientId, $userId, $canViewAll);
        if (!$client) {
            throw new RuntimeException("Client not found or access denied.", 404);
        }

        $errors = [];
        $dueAt = trim((string)($data['due_at'] ?? ''));
        if ($dueAt === '') {
            $errors['due_at'] = 'Due date and time is required.';
        } elseif (strtotime($dueAt) === false) {
            $errors['due_at'] = 'Please enter a valid date and time.';
        } else {
            $dueAt = date('Y-m-d H:i:s', strtotime($dueAt));
        }

        $type = strtolower(trim((string)($data['type'] ?? 'call')));
        if (!in_array($type, ['call', 'meeting', 'email'], true)) {
            $errors['type'] = 'Type must be call, meeting, or email.';
        }

        $status = strtolower(trim((string)($data['status'] ?? 'pending')));
        if (!in_array($status, ['pending', 'done', 'missed'], true)) {
            $status = 'pending';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Rep assignment: sales rep always self; admin/manager can assign or default to client assignee / self
        $assignedUserId = $userId;
        if ($canViewAll && !empty($data['user_id'])) {
            $assignedUserId = (int)$data['user_id'];
        } elseif ($canViewAll && !empty($client['assigned_to'])) {
            $assignedUserId = (int)$client['assigned_to'];
        }

        $notes = trim((string)($data['notes'] ?? ''));

        $insertData = [
            'client_id' => $targetClientId,
            'user_id' => $assignedUserId,
            'due_at' => $dueAt,
            'type' => $type,
            'notes' => $notes !== '' ? $notes : null,
            'status' => $status,
        ];

        $newId = (int)$this->followUpModel->insert($insertData);

        // Activity log
        $clientIp = $ip ?? '127.0.0.1';
        $clientUa = $userAgent ?? '';
        $this->activityLog->log(
            $userId,
            'client',
            $targetClientId,
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

        Cache::forgetByPrefix('dashboard:stats');

        return $this->get($newId);
    }

    /**
     * Update follow-up details or mark as done with outcome note.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $canViewAll = PermissionService::can('client.view_all');

        $existing = $this->get($id);
        $clientId = (int)$existing['client_id'];

        $updateData = [];
        $errors = [];

        // Check if marking done or outcome provided
        $outcomeNote = trim((string)($data['outcome'] ?? $data['outcome_note'] ?? ''));
        $targetStatus = isset($data['status']) ? strtolower(trim((string)$data['status'])) : null;

        if ($outcomeNote !== '' || $targetStatus === 'done') {
            $targetStatus = 'done';
            $updateData['status'] = 'done';

            $currentNotes = (string)($existing['notes'] ?? '');
            if ($outcomeNote !== '') {
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
            $type = strtolower(trim((string)$data['type']));
            if (!in_array($type, ['call', 'meeting', 'email'], true)) {
                $errors['type'] = 'Type must be call, meeting, or email.';
            } else {
                $updateData['type'] = $type;
            }
        }

        if (isset($data['notes']) && !array_key_exists('notes', $updateData)) {
            $updateData['notes'] = trim((string)$data['notes']);
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        if (empty($updateData)) {
            return $existing;
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

        $this->followUpModel->update($id, $updateData);

        if (!empty($diffNew)) {
            $action = ($diffNew['status'] ?? '') === 'done' ? 'followup_done' : 'followup_updated';
            $clientIp = $ip ?? '127.0.0.1';
            $clientUa = $userAgent ?? '';
            $this->activityLog->log(
                $userId,
                'client',
                $clientId,
                $action,
                array_merge(['followup_id' => $id], $diffNew),
                $diffOld,
                $clientIp,
                $clientUa
            );

            Cache::forgetByPrefix('dashboard:stats');
        }

        return $this->get($id);
    }

    /**
     * Soft delete follow-up with activity logging.
     */
    public function delete(int|string $id, ?string $ip = null, ?string $userAgent = null): bool
    {
        Session::start();
        $userId = (int)Session::get('user_id');

        $existing = $this->get($id);
        $clientId = (int)$existing['client_id'];

        $success = $this->followUpModel->softDelete($id);
        if ($success) {
            Cache::forgetByPrefix('dashboard:stats');

            $clientIp = $ip ?? '127.0.0.1';
            $clientUa = $userAgent ?? '';
            $this->activityLog->log(
                $userId,
                'client',
                $clientId,
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
