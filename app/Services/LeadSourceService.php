<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\LeadSource;

class LeadSourceService
{
    private LeadSource $sourceModel;

    public function __construct(?LeadSource $sourceModel = null)
    {
        $this->sourceModel = $sourceModel ?? new LeadSource();
    }

    public function all(): array
    {
        return $this->sourceModel->all();
    }

    public function getActive(): array
    {
        return $this->sourceModel->getActive();
    }

    public function create(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'Source name is required.']);
        }

        $existing = $this->sourceModel->findByName($name);
        if ($existing) {
            throw new ValidationException(['name' => 'A lead source with this name already exists.']);
        }

        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        $id = $this->sourceModel->insert([
            'name' => $name,
            'is_active' => $isActive,
        ]);

        return $this->sourceModel->find($id);
    }

    public function update(int|string $id, array $data): array
    {
        $existing = $this->sourceModel->find($id);
        if (!$existing) {
            throw new ValidationException(['source' => 'Lead source not found.']);
        }

        $updateData = [];

        if (isset($data['name'])) {
            $name = trim((string)$data['name']);
            if ($name === '') {
                throw new ValidationException(['name' => 'Source name cannot be empty.']);
            }
            $duplicate = $this->sourceModel->findByName($name);
            if ($duplicate && (int)$duplicate['id'] !== (int)$id) {
                throw new ValidationException(['name' => 'A lead source with this name already exists.']);
            }
            $updateData['name'] = $name;
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int)(bool)$data['is_active'];
        }

        if (!empty($updateData)) {
            $this->sourceModel->update($id, $updateData);
        }

        return $this->sourceModel->find($id);
    }
}
