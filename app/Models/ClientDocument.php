<?php

declare(strict_types=1);

namespace App\Models;

class ClientDocument extends BaseModel
{
    protected string $table = 'client_documents';
    protected bool $softDelete = true;

    public function createDocument(array $data): int
    {
        return (int)$this->insert($data);
    }

    public function findByClientAndId(int|string $clientId, int|string $docId): ?array
    {
        return $this->first([
            'id' => (int)$docId,
            'client_id' => (int)$clientId,
        ]);
    }

    public function getByClient(int|string $clientId): array
    {
        return $this->where(['client_id' => (int)$clientId], 'AND', ['id' => 'ASC']);
    }

    public function deleteDocument(int|string $clientId, int|string $docId): bool
    {
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = ? WHERE `id` = ? AND `client_id` = ? AND `deleted_at` IS NULL";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([date('Y-m-d H:i:s'), (int)$docId, (int)$clientId]);
        return $stmt->rowCount() > 0;
    }
}
