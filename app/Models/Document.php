<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Document extends ClientDocument
{
    protected string $table = 'client_documents';
    protected bool $softDelete = true;

    /**
     * List documents for a given polymorphic entity (client, lead, student).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForEntity(string $entityType, int|string $entityId): array
    {
        $id = (int)$entityId;
        $sql = "
            SELECT d.*, u.`name` AS `uploaded_by_name`, u.`email` AS `uploaded_by_email`
            FROM `{$this->table}` d
            LEFT JOIN `users` u ON u.`id` = d.`uploaded_by`
            WHERE d.`deleted_at` IS NULL
              AND (
                  (d.`entity_type` = ? AND d.`entity_id` = ?)
                  OR (? = 'client' AND d.`client_id` = ?)
                  OR (? = 'lead' AND d.`lead_id` = ?)
                  OR (? = 'student' AND d.`student_id` = ?)
              )
            ORDER BY d.`id` DESC
        ";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$entityType, $id, $entityType, $id, $entityType, $id, $entityType, $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find single document by ID with uploader info.
     */
    public function findDocument(int|string $id): ?array
    {
        $sql = "
            SELECT d.*, u.`name` AS `uploaded_by_name`, u.`email` AS `uploaded_by_email`
            FROM `{$this->table}` d
            LEFT JOIN `users` u ON u.`id` = d.`uploaded_by`
            WHERE d.`id` = ? AND d.`deleted_at` IS NULL
            LIMIT 1
        ";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
