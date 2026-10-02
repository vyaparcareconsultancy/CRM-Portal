<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ClientService extends BaseModel
{
    protected string $table = 'client_services';
    protected bool $softDelete = true;

    /**
     * Get all services assigned to a client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByClientId(int $clientId): array
    {
        $sql = "
            SELECT cs.*, 
                   s.name AS service_name, 
                   s.code AS service_code, 
                   s.type AS service_type, 
                   s.category AS service_category,
                   u.name AS accountant_name,
                   u.email AS accountant_email
            FROM `{$this->table}` cs
            JOIN `services` s ON cs.service_id = s.id
            LEFT JOIN `users` u ON cs.assigned_accountant_id = u.id
            WHERE cs.client_id = ? AND cs.deleted_at IS NULL
            ORDER BY cs.id DESC
        ";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find a client service with joined details.
     */
    public function findWithDetails(int $id): ?array
    {
        $sql = "
            SELECT cs.*, 
                   s.name AS service_name, 
                   s.code AS service_code, 
                   s.type AS service_type, 
                   s.category AS service_category,
                   u.name AS accountant_name,
                   u.email AS accountant_email,
                   c.name AS client_name,
                   c.client_code
            FROM `{$this->table}` cs
            JOIN `services` s ON cs.service_id = s.id
            JOIN `clients` c ON cs.client_id = c.id
            LEFT JOIN `users` u ON cs.assigned_accountant_id = u.id
            WHERE cs.id = ? AND cs.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
