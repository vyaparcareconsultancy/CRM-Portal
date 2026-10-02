<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Models\Service;
use RuntimeException;

class ServiceCatalogService
{
    private Service $serviceModel;

    public function __construct(?Service $serviceModel = null)
    {
        $this->serviceModel = $serviceModel ?? new Service();
    }

    /**
     * List all services from master catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listServices(?bool $activeOnly = null): array
    {
        if ($activeOnly === true) {
            return $this->serviceModel->getActive();
        }

        $stmt = $this->serviceModel->getPdo()->query("
            SELECT * FROM `services` 
            WHERE `deleted_at` IS NULL 
            ORDER BY `name` ASC
        ");
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get a specific service by ID.
     */
    public function getService(int $id): array
    {
        $service = $this->serviceModel->find($id);
        if (!$service) {
            throw new RuntimeException("Service not found.", 404);
        }
        return $service;
    }

    /**
     * Create a new service in catalog (Admin only).
     */
    public function createService(array $data): array
    {
        $this->ensureCanManageServices();

        $errors = [];
        $name = trim((string)($data['name'] ?? ''));
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        $type = strtolower(trim((string)($data['type'] ?? 'recurring')));
        $frequency = !empty($data['frequency']) ? strtolower(trim((string)$data['frequency'])) : null;
        $defaultFee = (float)($data['default_fee'] ?? 0.00);
        $category = strtolower(trim((string)($data['category'] ?? 'other')));

        if ($name === '') {
            $errors['name'] = 'Service name is required.';
        }

        if (!in_array($type, ['one_time', 'recurring'], true)) {
            $errors['type'] = 'Service type must be either one_time or recurring.';
        }

        if ($type === 'recurring') {
            if (!$frequency || !in_array($frequency, ['monthly', 'quarterly', 'yearly'], true)) {
                $errors['frequency'] = 'Recurring service requires frequency (monthly, quarterly, or yearly).';
            }
        } else {
            $frequency = null;
        }

        if ($defaultFee < 0) {
            $errors['default_fee'] = 'Default fee cannot be negative.';
        }

        if ($code === '') {
            // Auto-generate unique code from name
            $prefix = 'SRV-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));
            $code = $prefix . '-' . substr(uniqid(), -4);
        }

        // Check unique code
        $stmt = $this->serviceModel->getPdo()->prepare("SELECT id FROM services WHERE code = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$code]);
        if ($stmt->fetchColumn()) {
            $errors['code'] = "Service code '{$code}' is already in use.";
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        $id = $this->serviceModel->insert([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'frequency' => $frequency,
            'default_fee' => $defaultFee,
            'category' => $category,
            'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        return $this->getService((int)$id);
    }

    /**
     * Update an existing service.
     */
    public function updateService(int $id, array $data): array
    {
        $this->ensureCanManageServices();
        $existing = $this->getService($id);

        $errors = [];
        $updateData = [];

        if (isset($data['name'])) {
            $name = trim((string)$data['name']);
            if ($name === '') {
                $errors['name'] = 'Service name cannot be empty.';
            } else {
                $updateData['name'] = $name;
            }
        }

        if (isset($data['type'])) {
            $type = strtolower(trim((string)$data['type']));
            if (!in_array($type, ['one_time', 'recurring'], true)) {
                $errors['type'] = 'Service type must be either one_time or recurring.';
            } else {
                $updateData['type'] = $type;
                if ($type === 'one_time') {
                    $updateData['frequency'] = null;
                }
            }
        }

        if (isset($data['frequency'])) {
            $freq = strtolower(trim((string)$data['frequency']));
            $curType = $updateData['type'] ?? $existing['type'];
            if ($curType === 'recurring') {
                if (!in_array($freq, ['monthly', 'quarterly', 'yearly'], true)) {
                    $errors['frequency'] = 'Frequency must be monthly, quarterly, or yearly.';
                } else {
                    $updateData['frequency'] = $freq;
                }
            } else {
                $updateData['frequency'] = null;
            }
        }

        if (isset($data['default_fee'])) {
            $fee = (float)$data['default_fee'];
            if ($fee < 0) {
                $errors['default_fee'] = 'Default fee cannot be negative.';
            } else {
                $updateData['default_fee'] = $fee;
            }
        }

        if (isset($data['category'])) {
            $updateData['category'] = strtolower(trim((string)$data['category']));
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int)(bool)$data['is_active'];
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        if (!empty($updateData)) {
            $this->serviceModel->update($id, $updateData);
        }

        return $this->getService($id);
    }

    /**
     * Delete a service (soft delete).
     */
    public function deleteService(int $id): bool
    {
        $this->ensureCanManageServices();
        $this->getService($id);
        return $this->serviceModel->delete($id);
    }

    private function ensureCanManageServices(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('user_role');

        if (!$userId) {
            throw new RuntimeException("Unauthenticated", 401);
        }

        if ($userRole !== 'admin' && !PermissionService::can('service.manage')) {
            throw new RuntimeException("Forbidden: you do not have permission to manage services.", 403);
        }
    }
}
