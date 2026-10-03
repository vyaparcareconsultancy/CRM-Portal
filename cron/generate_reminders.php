<?php

declare(strict_types=1);

/**
 * CRM Automated Reminders Generator & Dispatcher Cron
 *
 * Runs daily (e.g. at 07:00 AM or 08:00 AM) to:
 * 1. Evaluate admin-configured reminder rules against active client services, student fees, and clients.
 * 2. Generate reminder instances per client/student per period without duplicates.
 * 3. Dispatch in-app bell notifications to assigned staff and send reminder emails.
 *
 * Usage:
 *   php cron/generate_reminders.php
 *   php cron/generate_reminders.php --date=2026-10-08
 */

$projectRoot = dirname(__DIR__);

// Load Composer autoloader
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
require_once $projectRoot . '/app/Models/User.php';
require_once $projectRoot . '/app/Models/Notification.php';
require_once $projectRoot . '/app/Models/ReminderRule.php';
require_once $projectRoot . '/app/Models/Reminder.php';
require_once $projectRoot . '/app/Services/MailService.php';
require_once $projectRoot . '/app/Services/ReminderService.php';

use App\Core\Logger;
use App\Services\ReminderService;

$targetDate = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--date=')) {
        $targetDate = substr($arg, 7);
    }
}

$effectiveDate = $targetDate ?: date('Y-m-d');
$logTime = date('Y-m-d H:i:s');
echo "[{$logTime}] Starting cron: generate_reminders.php for date: {$effectiveDate}..." . PHP_EOL;

try {
    $reminderService = new ReminderService();

    // 1. Generate reminder instances for today / effective date
    $genStats = $reminderService->generateReminders($effectiveDate);
    echo "  -> Generated {$genStats['generated']} new reminder instance(s) across {$genStats['rules_processed']} active rule(s)." . PHP_EOL;

    // 2. Dispatch pending reminders (In-app notifications + Emails)
    $dispatchStats = $reminderService->dispatchPendingReminders($effectiveDate);
    echo "  -> Dispatched {$dispatchStats['dispatched']} reminder(s) (In-app: {$dispatchStats['in_app']}, Emails: {$dispatchStats['emails']})." . PHP_EOL;

    $summary = "Completed generate_reminders cron: {$genStats['generated']} generated, {$dispatchStats['dispatched']} dispatched.";
    Logger::info($summary, [
        'date' => $effectiveDate,
        'generated' => $genStats['generated'],
        'dispatched' => $dispatchStats['dispatched'],
    ]);
    echo "[{$logTime}] {$summary}" . PHP_EOL;
} catch (Throwable $e) {
    $errorMsg = "Error executing generate_reminders cron: " . $e->getMessage();
    Logger::error($errorMsg, [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    echo "[{$logTime}] [ERROR] {$errorMsg}" . PHP_EOL;
    exit(1);
}
