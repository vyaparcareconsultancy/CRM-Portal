<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use Throwable;

class HealthController
{
    /**
     * Comprehensive health check endpoint (GET /health).
     * Returns HTTP 200 if all services are healthy, 503 if any critical service is degraded.
     */
    public function check(): void
    {
        $checks = [];
        $isHealthy = true;

        // 1. Database Connectivity & Latency Check
        $dbStart = microtime(true);
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('SELECT 1');
            $dbSuccess = ($stmt !== false && $stmt->fetchColumn() == 1);
            $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);

            if ($dbSuccess) {
                $checks['database'] = [
                    'status' => 'ok',
                    'latency_ms' => $dbLatencyMs,
                ];
            } else {
                $isHealthy = false;
                $checks['database'] = [
                    'status' => 'fail',
                    'message' => 'Query returned invalid result',
                    'latency_ms' => $dbLatencyMs,
                ];
            }
        } catch (Throwable $e) {
            $isHealthy = false;
            $checks['database'] = [
                'status' => 'fail',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'latency_ms' => round((microtime(true) - $dbStart) * 1000, 2),
            ];
        }

        // 2. Storage Directory Writability Check
        $storageDir = dirname(__DIR__, 2) . '/storage';
        $subDirs = ['logs', 'cache', 'uploads'];
        $storageOk = true;
        $pathStatus = [];

        foreach ($subDirs as $sub) {
            $dir = $storageDir . '/' . $sub;
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $probe = $dir . '/.probe_' . bin2hex(random_bytes(4));
            $writable = (@file_put_contents($probe, 'ok') !== false);
            if ($writable) {
                @unlink($probe);
                $pathStatus[$sub] = true;
            } else {
                $pathStatus[$sub] = false;
                $storageOk = false;
            }
        }

        if (!$storageOk) {
            $isHealthy = false;
        }

        $checks['storage'] = [
            'status' => $storageOk ? 'ok' : 'fail',
            'writable' => $storageOk,
            'directories' => $pathStatus,
        ];

        // 3. Disk Free Space Check
        $freeBytes = @disk_free_space($storageDir);
        $totalBytes = @disk_total_space($storageDir);

        if ($freeBytes !== false && $totalBytes !== false && $totalBytes > 0) {
            $usedBytes = $totalBytes - $freeBytes;
            $usedPercent = round(($usedBytes / $totalBytes) * 100, 1);
            $freePercent = round(($freeBytes / $totalBytes) * 100, 1);

            // Fail if less than 500MB free or >95% full; Warn if >85% full
            $diskStatus = 'ok';
            if ($freeBytes < (500 * 1024 * 1024) || $usedPercent >= 95.0) {
                $diskStatus = 'fail';
                $isHealthy = false;
            } elseif ($usedPercent >= 85.0) {
                $diskStatus = 'warning';
            }

            $checks['disk'] = [
                'status' => $diskStatus,
                'free_bytes' => (int)$freeBytes,
                'free_readable' => self::formatBytes($freeBytes),
                'total_readable' => self::formatBytes($totalBytes),
                'used_percent' => $usedPercent,
                'free_percent' => $freePercent,
            ];
        } else {
            $checks['disk'] = [
                'status' => 'unknown',
                'message' => 'Unable to determine disk space metrics',
            ];
        }

        // 4. Return HTTP 200 or 503
        $statusCode = $isHealthy ? 200 : 503;
        $statusText = $isHealthy ? 'success' : 'error';

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Request-Id: ' . Request::requestId());
        }

        echo json_encode([
            'status' => $statusText,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'request_id' => Request::requestId(),
            'checks' => $checks,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
