<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ClientComplianceDetail extends BaseModel
{
    protected string $table = 'client_compliance_details';
    protected bool $softDelete = false;

    public function getByClientId(int $clientId): ?array
    {
        $stmt = $this->getPdo()->prepare("SELECT * FROM `{$this->table}` WHERE `client_id` = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveForClient(int $clientId, array $data): array
    {
        $existing = $this->getByClientId($clientId);
        $data['client_id'] = $clientId;

        if ($existing) {
            $this->update((int)$existing['id'], $data);
            return array_merge($existing, $data);
        }

        $id = $this->insert($data);
        return array_merge($data, ['id' => $id]);
    }
}
