<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ChannelOptOut extends BaseModel
{
    protected string $table = 'channel_opt_outs';
    protected bool $softDelete = false;

    /**
     * Check if a contact/identifier has opted out of a specific channel (or all channels).
     *
     * Invariant: Never message opted-out contacts.
     */
    public function isOptedOut(string $channel, string $identifier): bool
    {
        $identifier = $this->normalizeIdentifier($identifier);
        $channel = strtolower(trim($channel));

        $stmt = $this->pdo->prepare("
            SELECT `id` FROM `{$this->table}`
            WHERE `identifier` = ? AND (`channel` = ? OR `channel` = 'all')
            LIMIT 1
        ");
        $stmt->execute([$identifier, $channel]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Record an opt-out for an identifier on a channel.
     */
    public function optOut(
        string $channel,
        string $identifier,
        string $entityType = 'other',
        ?int $entityId = null,
        ?string $reason = null
    ): bool {
        $identifier = $this->normalizeIdentifier($identifier);
        $channel = in_array($channel, ['email', 'whatsapp', 'sms', 'all'], true) ? $channel : 'all';

        $stmt = $this->pdo->prepare("
            INSERT INTO `{$this->table}` (`channel`, `identifier`, `entity_type`, `entity_id`, `reason`, `opted_out_at`)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`), `opted_out_at` = NOW()
        ");

        return $stmt->execute([
            $channel,
            $identifier,
            in_array($entityType, ['client', 'student', 'lead', 'other'], true) ? $entityType : 'other',
            $entityId,
            $reason ?: 'User requested opt-out',
        ]);
    }

    /**
     * Remove an opt-out (opt-in again).
     */
    public function optIn(string $channel, string $identifier): bool
    {
        $identifier = $this->normalizeIdentifier($identifier);
        $channel = strtolower(trim($channel));

        if ($channel === 'all') {
            $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `identifier` = ?");
            return $stmt->execute([$identifier]);
        }

        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `identifier` = ? AND (`channel` = ? OR `channel` = 'all')");
        return $stmt->execute([$identifier, $channel]);
    }

    /**
     * List current opt-outs with filters.
     */
    public function getOptOuts(array $filters = []): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE 1=1";
        $params = [];

        if (!empty($filters['channel'])) {
            $sql .= " AND `channel` = ?";
            $params[] = $filters['channel'];
        }

        if (!empty($filters['identifier'])) {
            $sql .= " AND `identifier` LIKE ?";
            $params[] = '%' . trim($filters['identifier']) . '%';
        }

        $sql .= " ORDER BY `opted_out_at` DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Normalize phone/email identifier.
     */
    public function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if (str_contains($identifier, '@')) {
            return strtolower($identifier);
        }

        // Strip non-digits for phone numbers
        $digits = preg_replace('/\D+/', '', $identifier) ?: '';
        if (strlen($digits) === 10) {
            // Standard Indian 10-digit number -> prepend 91 for consistency
            return '91' . $digits;
        }

        return $digits ?: $identifier;
    }
}
