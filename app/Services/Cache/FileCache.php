<?php

declare(strict_types=1);

namespace App\Services\Cache;

/**
 * High-performance file-based cache driver.
 * Stores serialized values with timestamps in storage/cache.
 */
class FileCache implements CacheInterface
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? (dirname(__DIR__, 3) . '/storage/cache'), '/\\');

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return $default;
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false || $raw === '') {
            return $default;
        }

        $payload = @unserialize($raw);
        if (!is_array($payload) || !isset($payload['expires_at'], $payload['data'])) {
            @unlink($filePath);
            return $default;
        }

        if (time() >= $payload['expires_at']) {
            @unlink($filePath);
            return $default;
        }

        return $payload['data'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $filePath = $this->getFilePath($key);
        $payload = [
            'expires_at' => time() + max(1, $ttl),
            'key' => $key,
            'data' => $value,
        ];

        $serialized = serialize($payload);
        $tempPath = $this->directory . '/tmp_' . bin2hex(random_bytes(8)) . '.tmp';

        if (@file_put_contents($tempPath, $serialized, LOCK_EX) === false) {
            return false;
        }

        return @rename($tempPath, $filePath);
    }

    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    public function forgetByPrefix(string $prefix): bool
    {
        $safePrefix = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix);
        $pattern = $this->directory . '/' . $safePrefix . '*.cache';
        $files = glob($pattern) ?: [];

        $success = true;
        foreach ($files as $file) {
            if (is_file($file) && !@unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    public function clear(): bool
    {
        $files = glob($this->directory . '/*.cache') ?: [];
        $success = true;
        foreach ($files as $file) {
            if (is_file($file) && !@unlink($file)) {
                $success = false;
            }
        }
        return $success;
    }

    private function getFilePath(string $key): string
    {
        $safePrefix = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key), 0, 32);
        $hash = hash('sha256', $key);
        return $this->directory . '/' . $safePrefix . '_' . $hash . '.cache';
    }
}
