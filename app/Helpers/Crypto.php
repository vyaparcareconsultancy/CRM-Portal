<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Cryptographic helper for encrypting/decrypting sensitive data at rest
 * using native OpenSSL AES-256-GCM.
 */
class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12; // 96 bits recommended for GCM
    private const TAG_LENGTH = 16; // 128-bit authentication tag

    /**
     * Get or derive a 32-byte key for AES-256.
     */
    public static function getKey(): string
    {
        $raw = $_ENV['APP_KEY']
            ?? $_ENV['PAN_ENCRYPTION_KEY']
            ?? 'crm_techtians_default_secret_key_32_bytes!';

        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }

        if (strlen($raw) === 32) {
            return $raw;
        }

        return hash('sha256', $raw, true);
    }

    /**
     * Encrypt PAN at rest using OpenSSL AES-256-GCM.
     */
    public static function encryptPan(?string $pan): ?string
    {
        if ($pan === null || trim($pan) === '') {
            return null;
        }

        $plainText = strtoupper(trim($pan));
        $key = self::getKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($cipherText === false) {
            throw new \RuntimeException("Encryption of PAN failed.");
        }

        // Format: base64(IV + TAG + CIPHERTEXT)
        return base64_encode($iv . $tag . $cipherText);
    }

    /**
     * Decrypt PAN from AES-256-GCM ciphertext.
     */
    public static function decryptPan(?string $payload): ?string
    {
        if ($payload === null || trim($payload) === '') {
            return null;
        }

        $trimmed = trim($payload);

        // Backward compatibility: If already plain PAN format (10 chars), return directly
        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $trimmed)) {
            return $trimmed;
        }

        $raw = base64_decode($trimmed, true);
        if ($raw === false || strlen($raw) < (self::IV_LENGTH + self::TAG_LENGTH)) {
            return $trimmed;
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $cipherText = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);
        $key = self::getKey();

        $plain = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plain !== false ? $plain : $trimmed;
    }

    /**
     * Mask PAN for safe display (e.g. ABCDE1234F -> ******234F).
     */
    public static function maskPan(?string $pan): ?string
    {
        if ($pan === null || trim($pan) === '') {
            return null;
        }

        // If encrypted, decrypt first
        $decrypted = self::decryptPan($pan);
        if ($decrypted === null || $decrypted === '') {
            return null;
        }

        $len = strlen($decrypted);
        if ($len > 4) {
            return str_repeat('*', $len - 4) . substr($decrypted, -4);
        }

        return str_repeat('*', $len);
    }

    /**
     * Mask Aadhaar number: strictly store and display only the last 4 digits (e.g. XXXX-XXXX-1234).
     * Never stores or returns full Aadhaar number.
     */
    public static function maskAadhaar(?string $aadhaar): ?string
    {
        if ($aadhaar === null || trim($aadhaar) === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', $aadhaar);
        if ($clean === null || $clean === '') {
            return 'XXXX-XXXX-XXXX';
        }

        if (strlen($clean) >= 4) {
            return 'XXXX-XXXX-' . substr($clean, -4);
        }

        return str_repeat('X', strlen($clean));
    }

    /**
     * Encrypt file data payload at rest using OpenSSL AES-256-GCM.
     */
    public static function encryptFile(string $data): string
    {
        $key = self::getKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipherText = openssl_encrypt(
            $data,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($cipherText === false) {
            throw new \RuntimeException("File encryption failed.");
        }

        return $iv . $tag . $cipherText;
    }

    /**
     * Decrypt file data payload from OpenSSL AES-256-GCM.
     */
    public static function decryptFile(string $payload): string
    {
        if (strlen($payload) < (self::IV_LENGTH + self::TAG_LENGTH)) {
            return $payload;
        }

        $iv = substr($payload, 0, self::IV_LENGTH);
        $tag = substr($payload, self::IV_LENGTH, self::TAG_LENGTH);
        $cipherText = substr($payload, self::IV_LENGTH + self::TAG_LENGTH);
        $key = self::getKey();

        $plain = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plain !== false ? $plain : $payload;
    }

    /**
     * Check if a string is already masked.
     */
    public static function isMasked(?string $value): bool
    {
        return $value !== null && str_contains($value, '*');
    }

    /**
     * Encrypt any sensitive secret/password at rest using OpenSSL AES-256-GCM.
     */
    public static function encryptSecret(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        $key = self::getKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipherText = openssl_encrypt(
            $text,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($cipherText === false) {
            throw new \RuntimeException("Encryption of secret failed.");
        }

        return base64_encode($iv . $tag . $cipherText);
    }

    /**
     * Decrypt sensitive secret/password from AES-256-GCM ciphertext.
     */
    public static function decryptSecret(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $trimmed = trim($payload);
        $raw = base64_decode($trimmed, true);
        if ($raw === false || strlen($raw) < (self::IV_LENGTH + self::TAG_LENGTH)) {
            return $trimmed;
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $cipherText = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);
        $key = self::getKey();

        $plain = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plain !== false ? $plain : $trimmed;
    }
}
