<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Note;
use App\Models\Notification;
use PDO;
use RuntimeException;

class TimelineService
{
    private Note $noteModel;
    private Notification $notificationModel;
    private ActivityLog $activityLog;
    private PDO $pdo;

    public function __construct(
        ?Note $noteModel = null,
        ?Notification $notificationModel = null,
        ?ActivityLog $activityLog = null,
        ?PDO $pdo = null
    ) {
        $this->noteModel = $noteModel ?? new Note();
        $this->notificationModel = $notificationModel ?? new Notification();
        $this->activityLog = $activityLog ?? new ActivityLog();
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Unified timeline per lead/client/student:
     * notes, calls, follow-ups, payments, documents, status changes, messages sent — newest first, filter by type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTimeline(string $entityType, int|string $entityId, ?string $filterType = null): array
    {
        $entityType = strtolower(trim($entityType));
        if (!in_array($entityType, ['lead', 'client', 'student'], true)) {
            throw new RuntimeException("Invalid entity type: {$entityType}", 422);
        }

        $entityId = (int)$entityId;
        if ($entityId <= 0) {
            throw new RuntimeException("Valid entity ID is required.", 422);
        }

        $events = [];

        // 1. NOTES
        $notes = $this->fetchNotes($entityType, $entityId);
        foreach ($notes as $n) {
            $events[] = [
                'id' => 'note_' . $n['id'],
                'source_id' => (int)$n['id'],
                'type' => 'note',
                'title' => !empty($n['is_pinned']) ? 'Pinned Note' : 'Staff Note',
                'description' => (string)$n['note'],
                'author_name' => (string)($n['author_name'] ?? 'Staff'),
                'author_email' => (string)($n['author_email'] ?? ''),
                'timestamp' => (string)$n['created_at'],
                'badge_class' => !empty($n['is_pinned']) ? 'bg-warning text-dark' : 'bg-primary-subtle text-primary border border-primary-subtle',
                'icon' => 'note',
                'is_pinned' => !empty($n['is_pinned']),
                'metadata' => [
                    'note_id' => (int)$n['id'],
                    'user_id' => (int)$n['user_id'],
                    'is_pinned' => !empty($n['is_pinned']),
                ],
            ];
        }

        // 2. FOLLOW-UPS & CALLS
        $followUps = $this->fetchFollowUps($entityType, $entityId);
        foreach ($followUps as $f) {
            $mode = strtolower(trim((string)($f['type'] ?? 'call')));
            $isCall = ($mode === 'call');
            $outcome = !empty($f['outcome']) ? ucfirst(str_replace('_', ' ', (string)$f['outcome'])) : null;
            $status = ucfirst((string)($f['status'] ?? 'pending'));

            $descParts = [];
            if ($outcome) {
                $descParts[] = "Outcome: {$outcome}";
            }
            if (!empty($f['remarks'])) {
                $descParts[] = (string)$f['remarks'];
            } elseif (!empty($f['notes'])) {
                $descParts[] = (string)$f['notes'];
            }
            $description = implode(' — ', $descParts);

            $events[] = [
                'id' => 'followup_' . $f['id'],
                'source_id' => (int)$f['id'],
                'type' => $isCall ? 'call' : 'followup',
                'title' => ($isCall ? 'Phone Call' : ucfirst($mode) . ' Follow-up') . " ({$status})",
                'description' => $description !== '' ? $description : "Scheduled {$mode} interaction",
                'author_name' => (string)($f['staff_name'] ?? 'Representative'),
                'timestamp' => (string)(($f['due_at'] ?? $f['scheduled_at'] ?? null) ?: $f['created_at']),
                'badge_class' => $isCall ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle',
                'icon' => $isCall ? 'call' : 'followup',
                'is_pinned' => false,
                'metadata' => [
                    'mode' => $mode,
                    'status' => $f['status'] ?? 'pending',
                    'outcome' => $f['outcome'] ?? null,
                ],
            ];
        }

        // 3. PAYMENTS & INVOICES
        $payments = $this->fetchPayments($entityType, $entityId);
        foreach ($payments as $p) {
            $amountFormatted = number_format((float)$p['amount'], 2);
            $mode = strtoupper((string)($p['payment_mode'] ?? 'CASH'));
            $desc = "Received ₹{$amountFormatted} via {$mode}";
            if (!empty($p['receipt_no'])) {
                $desc .= " (Receipt #{$p['receipt_no']})";
            }
            if (!empty($p['reference_no'])) {
                $desc .= " | Ref: {$p['reference_no']}";
            }

            $events[] = [
                'id' => 'payment_' . $p['id'],
                'source_id' => (int)$p['id'],
                'type' => 'payment',
                'title' => "Payment Received — ₹{$amountFormatted}",
                'description' => $desc,
                'author_name' => (string)($p['received_by_name'] ?? 'Accountant'),
                'timestamp' => (string)($p['payment_date'] ? $p['payment_date'] . ' 12:00:00' : $p['created_at']),
                'badge_class' => 'bg-success-subtle text-success border border-success-subtle',
                'icon' => 'payment',
                'is_pinned' => false,
                'metadata' => [
                    'amount' => (float)$p['amount'],
                    'receipt_no' => $p['receipt_no'] ?? null,
                    'mode' => $p['payment_mode'] ?? null,
                ],
            ];
        }

        // 4. DOCUMENTS
        $docs = $this->fetchDocuments($entityType, $entityId);
        foreach ($docs as $d) {
            $docTypeLabel = ucwords(str_replace('_', ' ', (string)($d['document_type'] ?? 'other')));
            $desc = "Uploaded file: " . ($d['original_name'] ?? 'document');
            if (!empty($d['document_number'])) {
                $desc .= " (" . $d['document_number'] . ")";
            }
            if (!empty($d['financial_year'])) {
                $desc .= " | FY: " . $d['financial_year'];
            }

            $events[] = [
                'id' => 'doc_' . $d['id'],
                'source_id' => (int)$d['id'],
                'type' => 'document',
                'title' => "Document: {$docTypeLabel}",
                'description' => $desc,
                'author_name' => (string)($d['uploader_name'] ?? 'Staff'),
                'timestamp' => (string)$d['created_at'],
                'badge_class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                'icon' => 'document',
                'is_pinned' => false,
                'metadata' => [
                    'doc_id' => (int)$d['id'],
                    'document_type' => $d['document_type'],
                    'original_name' => $d['original_name'],
                    'is_encrypted' => !empty($d['is_encrypted']),
                ],
            ];
        }

        // 5. STATUS CHANGES & ACTIVITY LOG (Status changes, messages sent)
        $activities = $this->fetchActivities($entityType, $entityId);
        foreach ($activities as $a) {
            $action = (string)$a['action'];
            $isMessage = in_array($action, ['message_sent', 'whatsapp_sent', 'email_sent', 'sms_sent'], true);
            $isStatusChange = str_contains($action, 'status') || str_contains($action, 'converted') || str_contains($action, 'admitted');

            $type = $isMessage ? 'message' : ($isStatusChange ? 'status' : 'activity');

            $title = ucwords(str_replace('_', ' ', $action));
            $desc = '';
            if (!empty($a['new_values'])) {
                $decoded = is_string($a['new_values']) ? json_decode($a['new_values'], true) : $a['new_values'];
                if (is_array($decoded)) {
                    if (isset($decoded['status'])) {
                        $desc = "Status updated to " . ucfirst((string)$decoded['status']);
                    } elseif (isset($decoded['message'])) {
                        $desc = (string)$decoded['message'];
                    } else {
                        $desc = json_encode($decoded);
                    }
                }
            }
            if ($desc === '') {
                $desc = "Action '{$action}' performed";
            }

            $events[] = [
                'id' => 'activity_' . $a['id'],
                'source_id' => (int)$a['id'],
                'type' => $type,
                'title' => $title,
                'description' => $desc,
                'author_name' => (string)($a['actor_name'] ?? 'System'),
                'timestamp' => (string)$a['created_at'],
                'badge_class' => $isMessage
                    ? 'bg-info-subtle text-info border border-info-subtle'
                    : 'bg-dark-subtle text-dark border border-dark-subtle',
                'icon' => $isMessage ? 'message' : 'status',
                'is_pinned' => false,
                'metadata' => [
                    'action' => $action,
                ],
            ];
        }

        // Filter by type if requested
        if ($filterType !== null && $filterType !== '' && strtolower($filterType) !== 'all') {
            $normalizedFilter = strtolower(trim($filterType));
            $events = array_values(array_filter($events, function (array $ev) use ($normalizedFilter): bool {
                $t = $ev['type'];
                if ($normalizedFilter === 'notes' || $normalizedFilter === 'note') {
                    return $t === 'note';
                }
                if ($normalizedFilter === 'calls' || $normalizedFilter === 'call') {
                    return $t === 'call';
                }
                if ($normalizedFilter === 'followups' || $normalizedFilter === 'followup' || $normalizedFilter === 'follow_ups') {
                    return $t === 'followup' || $t === 'call';
                }
                if ($normalizedFilter === 'payments' || $normalizedFilter === 'payment') {
                    return $t === 'payment';
                }
                if ($normalizedFilter === 'documents' || $normalizedFilter === 'document') {
                    return $t === 'document';
                }
                if ($normalizedFilter === 'status' || $normalizedFilter === 'status_changes') {
                    return $t === 'status';
                }
                if ($normalizedFilter === 'messages' || $normalizedFilter === 'message') {
                    return $t === 'message';
                }
                return $t === $normalizedFilter;
            }));
        }

        // Sort: newest first (timestamp DESC), with pinned notes on top when viewing all/notes
        usort($events, function (array $a, array $b): int {
            if ($a['is_pinned'] !== $b['is_pinned']) {
                return $a['is_pinned'] ? -1 : 1;
            }
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return $events;
    }

    /**
     * Quick "Add note" with mention of staff (@name) creates an in-app notification.
     *
     * @return array<string, mixed>
     */
    public function addNote(
        string $entityType,
        int|string $entityId,
        int $authorId,
        string $noteText,
        bool $isPinned = false
    ): array {
        $entityType = strtolower(trim($entityType));
        if (!in_array($entityType, ['lead', 'client', 'student'], true)) {
            throw new RuntimeException("Invalid entity type: {$entityType}", 422);
        }

        $entityId = (int)$entityId;
        if ($entityId <= 0) {
            throw new RuntimeException("Valid entity ID is required.", 422);
        }

        $noteText = trim($noteText);
        if ($noteText === '') {
            throw new RuntimeException("Note text cannot be empty.", 422);
        }

        // Fetch author name for notification message
        $stmtAuthor = $this->pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmtAuthor->execute([$authorId]);
        $author = $stmtAuthor->fetch(PDO::FETCH_ASSOC);
        $authorName = $author['name'] ?? 'A team member';

        // 1. Create Note in DB
        $noteId = $this->noteModel->addNote($entityType, $entityId, $authorId, $noteText, $isPinned);

        // 2. Parse @mentions
        $mentionedUserIds = $this->detectAndNotifyMentions(
            $noteText,
            $authorId,
            $authorName,
            $entityType,
            $entityId
        );

        // 3. Log to activity log
        $this->activityLog->log($authorId, $entityType, $entityId, 'note_added', [
            'note_id' => $noteId,
            'is_pinned' => $isPinned,
            'mentions_count' => count($mentionedUserIds),
        ]);

        return [
            'id' => $noteId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $authorId,
            'author_name' => $authorName,
            'note' => $noteText,
            'is_pinned' => $isPinned,
            'mentioned_users' => $mentionedUserIds,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Detect @name patterns and create in-app notifications.
     *
     * @return array<int, int> List of notified user IDs
     */
    public function detectAndNotifyMentions(
        string $text,
        int $authorId,
        string $authorName,
        string $entityType,
        int $entityId
    ): array {
        // Matches tokens like @Rahul, @rahul.sharma, @Admin, @JaneDoe
        if (!preg_match_all('/@([a-zA-Z0-9_\.-]+)/', $text, $matches)) {
            return [];
        }

        $rawTokens = array_unique($matches[1] ?? []);
        if (empty($rawTokens)) {
            return [];
        }

        // Entity Link Builder
        $link = match ($entityType) {
            'client' => "/clients/{$entityId}",
            'student' => "/students/profile?id={$entityId}",
            'lead' => "/leads?id={$entityId}",
            default => "/dashboard",
        };

        $notifiedIds = [];

        foreach ($rawTokens as $token) {
            $tokenClean = strtolower(trim($token));
            if ($tokenClean === '') {
                continue;
            }

            // Find matching user: check username/email prefix, full name without spaces, or first name
            $sql = "
                SELECT id, name, email FROM users
                WHERE is_active = 1 AND deleted_at IS NULL AND (
                    LOWER(name) = ?
                    OR LOWER(REPLACE(name, ' ', '')) = ?
                    OR LOWER(SUBSTRING_INDEX(name, ' ', 1)) = ?
                    OR LOWER(SUBSTRING_INDEX(email, '@', 1)) = ?
                )
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tokenClean, $tokenClean, $tokenClean, $tokenClean]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($targetUser) {
                $targetId = (int)$targetUser['id'];

                // Avoid duplicate notification to same user in same note
                if (!in_array($targetId, $notifiedIds, true)) {
                    $snippet = mb_strimwidth($text, 0, 140, '...');
                    $title = "{$authorName} mentioned you in a note";

                    $this->notificationModel->createNotification(
                        $targetId,
                        $title,
                        $snippet,
                        $link,
                        'mention'
                    );

                    $notifiedIds[] = $targetId;
                }
            }
        }

        return $notifiedIds;
    }

    /**
     * Toggle note pinned state.
     */
    public function togglePinNote(int|string $noteId, int $userId): bool
    {
        return $this->noteModel->togglePin((int)$noteId);
    }

    /**
     * Get unread notifications for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUnreadNotifications(int $userId): array
    {
        return $this->notificationModel->getUnreadForUser($userId);
    }

    /**
     * Mark a notification as read.
     */
    public function markNotificationAsRead(int|string $notificationId, int $userId): bool
    {
        return $this->notificationModel->markAsRead((int)$notificationId, $userId);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllNotificationsAsRead(int $userId): bool
    {
        return $this->notificationModel->markAllAsRead($userId);
    }

    // =========================================================================
    // PRIVATE QUERIES
    // =========================================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchNotes(string $entityType, int $entityId): array
    {
        return $this->noteModel->listForEntity($entityType, $entityId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFollowUps(string $entityType, int $entityId): array
    {
        if ($entityType === 'client') {
            $sql = "
                SELECT f.*, u.name AS staff_name
                FROM follow_ups f
                LEFT JOIN users u ON u.id = f.user_id
                WHERE f.client_id = ? AND f.deleted_at IS NULL
                ORDER BY COALESCE(f.due_at, f.created_at) DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$entityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if ($entityType === 'lead') {
            $sql = "
                SELECT f.*, u.name AS staff_name
                FROM follow_ups f
                LEFT JOIN users u ON u.id = f.user_id
                WHERE f.lead_id = ? AND f.deleted_at IS NULL
                ORDER BY COALESCE(f.due_at, f.created_at) DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$entityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // Student: fetch follow-ups from converted lead if available
        $sql = "
            SELECT f.*, u.name AS staff_name
            FROM follow_ups f
            LEFT JOIN users u ON u.id = f.user_id
            WHERE f.lead_id IN (
                SELECT l.id FROM leads l WHERE l.converted_student_id = ?
            ) AND f.deleted_at IS NULL
            ORDER BY COALESCE(f.due_at, f.created_at) DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPayments(string $entityType, int $entityId): array
    {
        if ($entityType === 'lead') {
            return [];
        }

        $column = ($entityType === 'client') ? 'client_id' : 'student_id';
        $sql = "
            SELECT p.*, i.invoice_no, u.name AS received_by_name
            FROM payments p
            JOIN invoices i ON i.id = p.invoice_id
            LEFT JOIN users u ON u.id = p.received_by
            WHERE (p.{$column} = ? OR i.{$column} = ?) AND p.deleted_at IS NULL
            ORDER BY COALESCE(p.payment_date, p.created_at) DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$entityId, $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDocuments(string $entityType, int $entityId): array
    {
        $sql = "
            SELECT d.*, u.name AS uploader_name
            FROM client_documents d
            LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE (
                (d.entity_type = ? AND d.entity_id = ?)
                OR (d.client_id = ? AND ? = 'client')
                OR (d.lead_id = ? AND ? = 'lead')
                OR (d.student_id = ? AND ? = 'student')
            ) AND d.deleted_at IS NULL
            ORDER BY d.created_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$entityType, $entityId, $entityId, $entityType, $entityId, $entityType, $entityId, $entityType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActivities(string $entityType, int $entityId): array
    {
        $sql = "
            SELECT a.*, u.name AS actor_name
            FROM activity_log a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.entity_type = ? AND a.entity_id = ?
            ORDER BY a.created_at DESC
            LIMIT 50
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$entityType, $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
