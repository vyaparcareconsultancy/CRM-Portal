<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Setting extends BaseModel
{
    protected string $table = 'settings';
    protected array $fillable = ['key', 'value'];

    /**
     * Get a setting by key, with optional fallback default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    }

    /**
     * Set a setting value (insert or update).
     */
    public static function set(string $key, ?string $value): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO `settings` (`key`, `value`) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        $stmt->execute([$key, $value]);
    }

    /**
     * Get all settings as key => value array.
     */
    public static function getAll(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT `key`, `value` FROM `settings`");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // Defaults if missing
        $defaults = [
            'business_name' => 'Vyapar Care Consultancy & Training Institute',
            'business_address' => '101, Business Towers, Commercial Complex, Mumbai, Maharashtra - 400001',
            'business_phone' => '+91 98765 43210',
            'business_email' => 'accounts@vyaparcare.com',
            'business_gstin' => '27AABCU9603R1ZM',
            'business_pan' => 'AABCU9603R',
            'business_logo_url' => '/assets/images/logo.png',
            'receipt_prefix' => 'REC',
            'invoice_prefix' => 'INV',
        ];

        return array_merge($defaults, $rows);
    }
}
