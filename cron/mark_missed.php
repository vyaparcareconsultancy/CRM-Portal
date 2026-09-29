<?php

declare(strict_types=1);

/**
 * CRM Follow-up Missed Status Updater Cron
 *
 * Runs periodically (e.g., hourly or at midnight) to find past-due follow-ups
 * still marked as 'pending' and transition them to 'missed'.
 *
 * Usage: php cron/mark_missed.php
 */

$projectRoot = dirname(__DIR__);

// Load Composer autoloader if present
$composerAutoload = $projectRoot . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Load environment variables
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

require_once $projectRoot . '/app/Core/Database.php';
require_once $projectRoot . '/app/Core/Logger.php';
require_once $projectRoot . '/app/Core/Model.php';
require_once $projectRoot . '/app/Models/BaseModel.php';
require_once $projectRoot . '/app/Models/FollowUp.php';

use App\Core\Logger;
use App\Models\FollowUp;

echo "[" . date('Y-m-d H:i:s') . "] Starting cron: mark_missed.php..." . PHP_EOL;

try {
    $followUpModel = new FollowUp();
    $affected = $followUpModel->markMissedPastDue();

    $msg = "Completed mark_missed: {$affected} past-due pending follow-ups marked as missed.";
    echo "[" . date('Y-m-d H:i:s') . "] {$msg}" . PHP_EOL;
    Logger::info("[CRON] {$msg}", ['affected_rows' => $affected]);

    exit(0);
} catch (Throwable $e) {
    $err = "Error executing mark_missed cron: " . $e->getMessage();
    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] {$err}" . PHP_EOL);
    Logger::error("[CRON] {$err}", ['exception' => $e]);
    exit(1);
}
