<?php

declare(strict_types=1);

namespace App\Services\Storage;

use RuntimeException;

class LocalStorage implements StorageInterface
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? (dirname(__DIR__, 3) . '/storage/uploads');
    }

    public function put(string $path, string $contents): bool
    {
        $full = $this->basePath . '/' . ltrim($path, '/');
        $dir = dirname($full);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }
        return file_put_contents($full, $contents) !== false;
    }

    public function putFile(string $directory, string $tmpPath, string $storedName): string
    {
        $dir = $this->basePath . '/' . ltrim($directory, '/');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }

        $dest = $dir . '/' . $storedName;
        if (is_uploaded_file($tmpPath)) {
            if (!move_uploaded_file($tmpPath, $dest)) {
                throw new RuntimeException("Failed to move uploaded file to: {$dest}");
            }
        } else {
            if (!copy($tmpPath, $dest)) {
                throw new RuntimeException("Failed to copy file to: {$dest}");
            }
        }

        return ltrim($directory, '/') . '/' . $storedName;
    }

    public function get(string $path): ?string
    {
        $full = $this->basePath . '/' . ltrim($path, '/');
        if (!file_exists($full)) {
            return null;
        }
        $contents = file_get_contents($full);
        return $contents !== false ? $contents : null;
    }

    public function delete(string $path): bool
    {
        $full = $this->basePath . '/' . ltrim($path, '/');
        if (file_exists($full)) {
            return unlink($full);
        }
        return true;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->basePath . '/' . ltrim($path, '/'));
    }

    public function path(string $relativePath): string
    {
        return $this->basePath . '/' . ltrim($relativePath, '/');
    }
}
