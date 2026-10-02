<?php

declare(strict_types=1);

/**
 * CRM Migration Runner
 * CLI command: php database/migrate.php
 */

$projectRoot = dirname(__DIR__);

// Load Composer autoloader if available
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

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_NAME'] ?? 'crm_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

echo "Connecting to MySQL server at {$host}:{$port}..." . PHP_EOL;

try {
    // 1. Connect to MySQL server and ensure target database exists
    $multiStatementsAttr = defined('Pdo\Mysql::ATTR_MULTI_STATEMENTS')
        ? Pdo\Mysql::ATTR_MULTI_STATEMENTS
        : PDO::MYSQL_ATTR_MULTI_STATEMENTS;

    $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $pdo = new PDO($serverDsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        $multiStatementsAttr => true,
    ]);

    $safeDbName = str_replace('`', '``', $dbName);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$safeDbName}`");

    // 2. Ensure migrations tracking table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL,
            `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Scan migrations directory
    $migrationsDir = __DIR__ . '/migrations';
    $files = glob($migrationsDir . '/*.sql');
    if ($files === false) {
        throw new RuntimeException("Failed to read migrations directory: {$migrationsDir}");
    }

    sort($files, SORT_NATURAL);

    // 4. Retrieve already executed migrations
    $executed = $pdo->query("SELECT `migration` FROM `migrations`")->fetchAll(PDO::FETCH_COLUMN);

    // 5. Determine pending migrations
    $pending = [];
    foreach ($files as $filePath) {
        $fileName = basename($filePath);
        if (!in_array($fileName, $executed, true)) {
            $pending[] = $filePath;
        }
    }

    if (empty($pending)) {
        echo "Nothing to migrate. Database is up to date." . PHP_EOL;
        exit(0);
    }

    // 6. Calculate next batch number
    $maxBatch = (int)$pdo->query("SELECT COALESCE(MAX(`batch`), 0) FROM `migrations`")->fetchColumn();
    $currentBatch = $maxBatch + 1;

    echo "Running " . count($pending) . " pending migration(s) (Batch {$currentBatch})..." . PHP_EOL;

    $recordStmt = $pdo->prepare("INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, ?)");

    foreach ($pending as $filePath) {
        $fileName = basename($filePath);
        $sql = file_get_contents($filePath);
        if ($sql === false || trim($sql) === '') {
            echo "Skipping empty migration: {$fileName}" . PHP_EOL;
            continue;
        }

        echo "Migrating: {$fileName}... ";
        $pdo->exec($sql);
        $recordStmt->execute([$fileName, $currentBatch]);
        echo "DONE" . PHP_EOL;
    }

    echo PHP_EOL . "Migration complete! Successfully applied " . count($pending) . " migration(s)." . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . "Migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
