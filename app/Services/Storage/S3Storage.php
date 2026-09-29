<?php

declare(strict_types=1);

namespace App\Services\Storage;

use RuntimeException;

/**
 * S3-compatible storage via AWS SDK or raw HTTP (presigned URLs).
 * Works with AWS S3, Cloudflare R2, MinIO, DigitalOcean Spaces.
 *
 * Requires: aws/aws-sdk-php (composer require aws/aws-sdk-php)
 * ponytail: deferred until STORAGE_DRIVER=s3 is actually set. No SDK installed by default.
 */
class S3Storage implements StorageInterface
{
    /** @var \Aws\S3\S3Client */
    private object $client;
    private string $bucket;
    private string $prefix;

    public function __construct()
    {
        if (!class_exists(\Aws\S3\S3Client::class)) {
            throw new RuntimeException(
                'S3Storage requires aws/aws-sdk-php. Run: composer require aws/aws-sdk-php'
            );
        }

        $this->bucket = $_ENV['S3_BUCKET'] ?? '';
        $this->prefix = trim($_ENV['S3_PREFIX'] ?? 'uploads', '/');

        if ($this->bucket === '') {
            throw new RuntimeException('S3_BUCKET environment variable is required for S3 storage.');
        }

        $config = [
            'version' => 'latest',
            'region'  => $_ENV['S3_REGION'] ?? 'us-east-1',
            'credentials' => [
                'key'    => $_ENV['S3_KEY'] ?? '',
                'secret' => $_ENV['S3_SECRET'] ?? '',
            ],
        ];

        // Cloudflare R2, MinIO, or other S3-compatible endpoints
        if (!empty($_ENV['S3_ENDPOINT'])) {
            $config['endpoint'] = $_ENV['S3_ENDPOINT'];
            $config['use_path_style_endpoint'] = true;
        }

        $this->client = new \Aws\S3\S3Client($config);
    }

    private function key(string $path): string
    {
        return $this->prefix . '/' . ltrim($path, '/');
    }

    public function put(string $path, string $contents): bool
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key'    => $this->key($path),
            'Body'   => $contents,
        ]);
        return true;
    }

    public function putFile(string $directory, string $tmpPath, string $storedName): string
    {
        $relativePath = ltrim($directory, '/') . '/' . $storedName;
        $this->client->putObject([
            'Bucket'     => $this->bucket,
            'Key'        => $this->key($relativePath),
            'SourceFile' => $tmpPath,
        ]);
        return $relativePath;
    }

    public function get(string $path): ?string
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->key($path),
            ]);
            return (string)$result['Body'];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function delete(string $path): bool
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $this->key($path),
        ]);
        return true;
    }

    public function exists(string $path): bool
    {
        return $this->client->doesObjectExistV2($this->bucket, $this->key($path));
    }

    public function path(string $relativePath): string
    {
        // Return a presigned URL valid for 15 minutes for download
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $this->key($relativePath),
        ]);
        $request = $this->client->createPresignedRequest($cmd, '+15 minutes');
        return (string)$request->getUri();
    }
}
