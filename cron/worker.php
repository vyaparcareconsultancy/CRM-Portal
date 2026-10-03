<?php

declare(strict_types=1);

/**
 * CRM Job Queue Worker
 *
 * Processes pending jobs from the `jobs` table. Intended to run via cron every minute:
 *   * * * * * /usr/bin/php /var/www/crm/current/cron/worker.php >> /var/www/crm/shared/storage/logs/worker.log 2>&1
 *
 * Each invocation processes up to 20 jobs then exits (no daemon, no memory leaks).
 * ponytail: cron-driven batch, not a long-running daemon. Add supervisor when queue depth > 100/min.
 */

$projectRoot = dirname(__DIR__);

$composerAutoload = $projectRoot . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

if (class_exists(\Dotenv\Dotenv::class) && file_exists($projectRoot . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
} elseif (file_exists($projectRoot . '/.env')) {
    $lines = file($projectRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

use App\Core\Logger;
use App\Services\JobQueue;

$maxJobs = (int)($argv[1] ?? 20);
$queue = new JobQueue();
$processed = 0;
$failed = 0;

echo "[" . date('Y-m-d H:i:s') . "] Worker started. Processing up to {$maxJobs} jobs..." . PHP_EOL;

for ($i = 0; $i < $maxJobs; $i++) {
    $job = $queue->pop('default');
    if ($job === null) {
        // Also check email queue
        $job = $queue->pop('email');
    }
    if ($job === null) {
        $job = $queue->pop('export');
    }
    if ($job === null) {
        $job = $queue->pop('broadcast');
    }
    if ($job === null) {
        $job = $queue->pop('message');
    }
    if ($job === null) {
        break; // No more jobs
    }

    $jobId = (int)$job['id'];
    $handler = (string)$job['handler'];
    /** @var array<string, mixed> $payload */
    $payload = $job['payload'] ?? [];

    echo "  -> Processing job #{$jobId}: {$handler}..." . PHP_EOL;

    try {
        // Handler format: 'ClassName::methodName' (static) or 'ClassName->methodName' (instance)
        if (str_contains($handler, '->')) {
            [$className, $method] = explode('->', $handler, 2);
            $instance = new $className();
            $instance->$method(...array_values($payload));
        } elseif (str_contains($handler, '::')) {
            [$className, $method] = explode('::', $handler, 2);
            $className::$method(...array_values($payload));
        } else {
            // Assume invokable class
            $instance = new $handler();
            $instance($payload);
        }

        $queue->complete($jobId);
        $processed++;
        echo "     Done." . PHP_EOL;
    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
        $attempts = (int)$job['attempts'];
        $maxAttempts = (int)$job['max_attempts'];

        if ($attempts >= $maxAttempts) {
            $queue->fail($jobId, $errorMsg);
            $failed++;
            echo "     FAILED (max attempts reached): {$errorMsg}" . PHP_EOL;
            Logger::error("[WORKER] Job #{$jobId} permanently failed: {$errorMsg}", [
                'handler' => $handler,
                'attempts' => $attempts,
            ]);
        } else {
            // Release back for retry with exponential backoff: 30s, 60s, 120s...
            $delay = 30 * (int)pow(2, $attempts - 1);
            $queue->release($jobId, $delay);
            echo "     Retry scheduled in {$delay}s: {$errorMsg}" . PHP_EOL;
        }
    }
}

$msg = "Worker finished: {$processed} processed, {$failed} failed.";
echo "[" . date('Y-m-d H:i:s') . "] {$msg}" . PHP_EOL;
if ($processed > 0 || $failed > 0) {
    Logger::info("[WORKER] {$msg}");
}
