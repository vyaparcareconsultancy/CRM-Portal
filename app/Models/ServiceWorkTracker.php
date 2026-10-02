<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ServiceWorkTracker extends BaseModel
{
    protected string $table = 'service_work_tracker';
    protected bool $softDelete = false;

    /**
     * Get tracker items for a specific client service subscription.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByClientServiceId(int $clientServiceId): array
    {
        $sql = "
            SELECT wt.*, 
                   u.name AS assigned_to_name,
                   s.name AS service_name,
                   s.code AS service_code
            FROM `{$this->table}` wt
            JOIN `client_services` cs ON wt.client_service_id = cs.id
            JOIN `services` s ON cs.service_id = s.id
            LEFT JOIN `users` u ON wt.assigned_to = u.id
            WHERE wt.client_service_id = ?
            ORDER BY wt.id DESC
        ";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$clientServiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all work tracker items for a client across all services.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByClientId(int $clientId): array
    {
        $sql = "
            SELECT wt.*, 
                   s.name AS service_name, 
                   s.code AS service_code,
                   cs.frequency AS service_frequency,
                   u.name AS assigned_to_name
            FROM `{$this->table}` wt
            JOIN `client_services` cs ON wt.client_service_id = cs.id
            JOIN `services` s ON cs.service_id = s.id
            LEFT JOIN `users` u ON wt.assigned_to = u.id
            WHERE wt.client_id = ?
            ORDER BY wt.id DESC
        ";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
