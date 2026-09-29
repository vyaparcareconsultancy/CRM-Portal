<?php

declare(strict_types=1);

/**
 * CRM Daily Follow-up Email Reminders Cron
 *
 * Runs once every morning (e.g., at 08:00 AM) to send an email summary to each
 * sales representative of their pending follow-ups due today.
 *
 * Usage: php cron/daily_reminders.php
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
require_once $projectRoot . '/app/Services/MailService.php';
require_once $projectRoot . '/app/Services/JobQueue.php';

use App\Core\Logger;
use App\Models\FollowUp;
use App\Services\JobQueue;
use App\Services\MailService;

echo "[" . date('Y-m-d H:i:s') . "] Starting cron: daily_reminders.php..." . PHP_EOL;

try {
    $followUpModel = new FollowUp();
    $pendingToday = $followUpModel->getPendingDueToday();

    if (empty($pendingToday)) {
        echo "[" . date('Y-m-d H:i:s') . "] No pending follow-ups scheduled for today (" . date('Y-m-d') . "). Done." . PHP_EOL;
        exit(0);
    }

    // Group by user_id
    $grouped = [];
    foreach ($pendingToday as $item) {
        $userId = (int)$item['user_id'];
        if (!isset($grouped[$userId])) {
            $grouped[$userId] = [
                'name' => (string)($item['user_name'] ?? 'User'),
                'email' => (string)($item['user_email'] ?? ''),
                'items' => [],
            ];
        }
        $grouped[$userId]['items'][] = $item;
    }

    $usersEmailed = 0;
    $totalReminders = 0;
    $queue = new JobQueue();

    foreach ($grouped as $userId => $group) {
        $email = $group['email'];
        $name = $group['name'];
        $items = $group['items'];

        if ($email === '') {
            continue;
        }

        echo "  -> Queuing reminder for {$name} ({$email}) with " . count($items) . " follow-up(s)..." . PHP_EOL;
        $queue->dispatch(
            'App\Services\MailService::sendDailyFollowUpsSummary',
            ['email' => $email, 'userName' => $name, 'followUps' => $items],
            'email'
        );
        $usersEmailed++;
        $totalReminders += count($items);
    }

    $msg = "Completed daily_reminders: sent summaries to {$usersEmailed} user(s) for {$totalReminders} follow-up(s).";
    echo "[" . date('Y-m-d H:i:s') . "] {$msg}" . PHP_EOL;
    Logger::info("[CRON] {$msg}", ['users_emailed' => $usersEmailed, 'total_followups' => $totalReminders]);

    exit(0);
} catch (Throwable $e) {
    $err = "Error executing daily_reminders cron: " . $e->getMessage();
    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] {$err}" . PHP_EOL);
    Logger::error("[CRON] {$err}", ['exception' => $e]);
    exit(1);
}
