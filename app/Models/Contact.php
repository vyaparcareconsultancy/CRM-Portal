<?php

declare(strict_types=1);

namespace App\Models;

class Contact extends BaseModel
{
    protected string $table = 'contacts';
    protected bool $softDelete = true;

    /**
     * Find contact by mobile number.
     */
    public function findByMobile(string $mobile): ?array
    {
        $cleanMobile = preg_replace('/[^0-9]/', '', $mobile);
        $stmt = $this->getPdo()->prepare("SELECT * FROM `{$this->table}` WHERE `mobile` = ? AND `deleted_at` IS NULL LIMIT 1");
        $stmt->execute([$cleanMobile]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Find or create contact by mobile.
     */
    public function firstOrCreate(array $data): array
    {
        $cleanMobile = preg_replace('/[^0-9]/', '', (string)($data['mobile'] ?? ''));
        $existing = $this->findByMobile($cleanMobile);
        if ($existing) {
            // Update fields if provided and not currently set
            $updates = [];
            foreach (['name', 'email', 'whatsapp_number', 'pan_no', 'city', 'state'] as $field) {
                if (!empty($data[$field]) && empty($existing[$field])) {
                    $updates[$field] = $data[$field];
                }
            }
            if (!empty($updates)) {
                $this->update((int)$existing['id'], $updates);
                return array_merge($existing, $updates);
            }
            return $existing;
        }

        $insertData = [
            'name' => trim((string)($data['name'] ?? '')),
            'mobile' => $cleanMobile,
            'whatsapp_number' => !empty($data['whatsapp_number']) ? preg_replace('/[^0-9]/', '', (string)$data['whatsapp_number']) : $cleanMobile,
            'email' => !empty($data['email']) ? trim((string)$data['email']) : null,
            'contact_type' => $data['contact_type'] ?? 'individual',
            'pan_no' => $data['pan_no'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'pincode' => $data['pincode'] ?? null,
        ];

        $id = $this->insert($insertData);
        $insertData['id'] = (int)$id;
        return $insertData;
    }
}
