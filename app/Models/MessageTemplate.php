<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class MessageTemplate extends BaseModel
{
    protected string $table = 'message_templates';

    /**
     * Find active template by unique code.
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `{$this->table}`
            WHERE `code` = ? AND `deleted_at` IS NULL
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Get templates matching filters.
     */
    public function getActive(array $filters = []): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `deleted_at` IS NULL AND `is_active` = 1";
        $params = [];

        if (!empty($filters['channel']) && $filters['channel'] !== 'all') {
            $sql .= " AND (`channel` = ? OR `channel` = 'all')";
            $params[] = $filters['channel'];
        }

        $sql .= " ORDER BY `name` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all templates (active & inactive) for admin management.
     */
    public function getAllTemplates(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `{$this->table}`
            WHERE `deleted_at` IS NULL
            ORDER BY `id` DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new message template.
     */
    public function createTemplate(array $data): int
    {
        return (int)$this->create([
            'code' => strtoupper(trim((string)$data['code'])),
            'name' => trim((string)$data['name']),
            'channel' => in_array($data['channel'] ?? '', ['email', 'whatsapp', 'sms', 'all'], true) ? $data['channel'] : 'all',
            'subject' => !empty($data['subject']) ? trim((string)$data['subject']) : null,
            'body_template' => trim((string)$data['body_template']),
            'dlt_template_id' => !empty($data['dlt_template_id']) ? trim((string)$data['dlt_template_id']) : null,
            'whatsapp_template_name' => !empty($data['whatsapp_template_name']) ? trim((string)$data['whatsapp_template_name']) : null,
            'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);
    }

    /**
     * Update an existing message template.
     */
    public function updateTemplate(int $id, array $data): bool
    {
        $updates = [];
        if (isset($data['name'])) {
            $updates['name'] = trim((string)$data['name']);
        }
        if (isset($data['channel']) && in_array($data['channel'], ['email', 'whatsapp', 'sms', 'all'], true)) {
            $updates['channel'] = $data['channel'];
        }
        if (array_key_exists('subject', $data)) {
            $updates['subject'] = !empty($data['subject']) ? trim((string)$data['subject']) : null;
        }
        if (isset($data['body_template'])) {
            $updates['body_template'] = trim((string)$data['body_template']);
        }
        if (array_key_exists('dlt_template_id', $data)) {
            $updates['dlt_template_id'] = !empty($data['dlt_template_id']) ? trim((string)$data['dlt_template_id']) : null;
        }
        if (array_key_exists('whatsapp_template_name', $data)) {
            $updates['whatsapp_template_name'] = !empty($data['whatsapp_template_name']) ? trim((string)$data['whatsapp_template_name']) : null;
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = (int)(bool)$data['is_active'];
        }

        if (empty($updates)) {
            return false;
        }

        return $this->update($id, $updates);
    }
}
