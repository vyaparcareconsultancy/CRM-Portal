<?php

declare(strict_types=1);

namespace App\Services\Storage;

/**
 * Factory that returns the configured storage driver.
 * Reads STORAGE_DRIVER from .env: 'local' (default) or 's3'.
 */
final class Storage
{
    private static ?StorageInterface $instance = null;

    public static function disk(): StorageInterface
    {
        if (self::$instance === null) {
            $driver = strtolower($_ENV['STORAGE_DRIVER'] ?? 'local');
            if ($driver === 's3') {
                if (!class_exists(\Aws\S3\S3Client::class)) {
                    throw new \RuntimeException("S3 storage driver requires the 'aws/aws-sdk-php' package. Please install it via: composer require aws/aws-sdk-php");
                }
                self::$instance = new S3Storage();
            } else {
                self::$instance = new LocalStorage();
            }
        }
        return self::$instance;
    }

    /** For testing: inject a custom storage instance. */
    public static function setInstance(?StorageInterface $storage): void
    {
        self::$instance = $storage;
    }
}
