<?php

declare(strict_types=1);

namespace App\Services\Storage;

/**
 * Contract for file storage backends.
 */
interface StorageInterface
{
    /**
     * Store a file. Returns the relative storage path.
     */
    public function put(string $path, string $contents): bool;

    /**
     * Store an uploaded file. Returns the relative storage path.
     */
    public function putFile(string $directory, string $tmpPath, string $storedName): string;

    /**
     * Get file contents.
     */
    public function get(string $path): ?string;

    /**
     * Delete a file.
     */
    public function delete(string $path): bool;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Get the full/absolute path or URL for serving a file.
     */
    public function path(string $relativePath): string;
}
