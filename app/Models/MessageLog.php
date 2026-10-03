<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class MessageLog extends BaseModel
{
    protected string $table = 'message_logs';
    protected bool $softDelete = false;

    /**
     * Create an outbound message log entry.
     */
    public function logMessage(array $data): int
    {
        $channel = strtolower((string)($data['channel'] ?? 'email'));
        $status = (string)($data['status'] ?? 'queued');
        $sentAt = ($status === 'sent') ? date('Y-m-d H:i:s') : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO `{$this->table}` (
                `channel`, `template_id`, `entity_type`, `entity_id`,
                `recipient`, `subject`, `message_body`, `status`,
                `gateway_message_id`, `error_message`, `sent_by`, `sent_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $channel,
            !empty($data['template_id']) ? (int)$data['template_id'] : null,
            in_array($data['entity_type'] ?? '', ['client', 'student', 'lead', 'custom'], true) ? $data['entity_type'] : 'custom',
            !empty($data['entity_id']) ? (int)$data['entity_id'] : null,
            trim((string)$data['recipient']),
            !empty($data['subject']) ? trim((string)$data['subject']) : null,
            (string)$data['message_body'],
            $status,
            !empty($data['gateway_message_id']) ? trim((string)$data['gateway_message_id']) : null,
            !empty($data['error_message']) ? (string)$data['error_message'] : null,
            !empty($data['sent_by']) ? (int)$data['sent_by'] : null,
            $sentAt,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update delivery status of a logged message.
     */
    public function updateDeliveryStatus(
        int $id,
        string $status,
        ?string $gatewayMessageId = null,
        ?string $errorMessage = null
    ): bool {
        $sql = "UPDATE `{$this->table}` SET `status` = ?, `updated_at` = NOW()";
        $params = [$status];

        if ($status === 'sent') {
            $sql .= ", `sent_at` = NOW()";
        }
        if ($gatewayMessageId !== null) {
            $sql .= ", `gateway_message_id` = ?";
            $params[] = $gatewayMessageId;
        }
        if ($errorMessage !== null) {
            $sql .= ", `error_message` = ?";
            $params[] = $errorMessage;
        }

        $sql .= " WHERE `id` = ?";
        $params[] = $id;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Retrieve paginated delivery logs with joins.
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT ml.*,
                   mt.name AS template_name,
                   mt.code AS template_code,
                   u.name AS sender_name
            FROM `{$this->table}` ml
            LEFT JOIN `message_templates` mt ON ml.template_id = mt.id
            LEFT JOIN `users` u ON ml.sent_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['channel'])) {
            $sql .= " AND ml.channel = ?";
            $params[] = $filters['channel'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ml.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $sql .= " AND (ml.recipient LIKE ? OR ml.subject LIKE ? OR ml.message_body LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['entity_type']) && !empty($filters['entity_id'])) {
            $sql .= " AND ml.entity_type = ? AND ml.entity_id = ?";
            $params[] = $filters['entity_type'];
            $params[] = (int)$filters['entity_id'];
        }

        $sql .= " ORDER BY ml.id DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count logs matching filters.
     */
    public function countLogs(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` ml WHERE 1=1";
        $params = [];

        if (!empty($filters['channel'])) {
            $sql .= " AND ml.channel = ?";
            $params[] = $filters['channel'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ml.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $sql .= " AND (ml.recipient LIKE ? OR ml.subject LIKE ? OR ml.message_body LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['entity_type']) && !empty($filters['entity_id'])) {
            $sql .= " AND ml.entity_type = ? AND ml.entity_id = ?";
            $params[] = $filters['entity_type'];
            $params[] = (int)$filters['entity_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }
}
