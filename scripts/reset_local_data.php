<?php

declare(strict_types=1);

/**
 * Reset Local Development Data
 *
 * Empties all demo/test records and local storage files.
 * Strictly restricted to APP_ENV=local.
 *
 * Usage:
 *   php scripts/reset_local_data.php
 *   php scripts/reset_local_data.php --yes
 */

$projectRoot = dirname(__DIR__);

// Load Composer autoloader
$composerAutoload = $projectRoot . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// 1. Safety check: confirm APP_ENV=local
if (class_exists(\Dotenv\Dotenv::class) && file_exists($projectRoot . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
}

$cliEnv = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? null);
$appEnv = strtolower((string)($cliEnv ?: ($_ENV['APP_ENV'] ?? '')));

if ($appEnv !== 'local') {
    fwrite(STDERR, "========================================================\n");
    fwrite(STDERR, "ERROR: Safety check failed!\n");
    fwrite(STDERR, "reset_local_data.php is strictly restricted to APP_ENV=local.\n");
    fwrite(STDERR, "Current environment detected: " . ($appEnv === '' ? '[empty]' : $appEnv) . "\n");
    fwrite(STDERR, "Aborting immediately.\n");
    fwrite(STDERR, "========================================================\n");
    exit(1);
}

// Confirmation prompt unless --yes or -y passed
$args = $_SERVER['argv'] ?? ($argv ?? []);
$autoConfirm = in_array('--yes', $args, true) || in_array('-y', $args, true);
if (!$autoConfirm) {
    echo "========================================================\n";
    echo "  TECH-TIANS CRM — RESET LOCAL DEVELOPMENT DATA\n";
    echo "========================================================\n";
    echo "Target Database : " . ($_ENV['DB_NAME'] ?? 'crm_db') . "\n";
    echo "Environment     : {$appEnv}\n";
    echo "Host            : " . ($_ENV['DB_HOST'] ?? '127.0.0.1') . ":" . ($_ENV['DB_PORT'] ?? '3306') . "\n";
    echo "\nWARNING: This will permanently empty all client, document, follow-up,\n";
    echo "log, session, and cache data from your local development environment.\n\n";
    echo "Type YES to continue: ";

    $handle = fopen('php://stdin', 'r');
    $input = trim((string)fgets($handle));
    fclose($handle);

    if ($input !== 'YES') {
        echo "Aborted by user. No data was changed.\n";
        exit(0);
    }
}

$dbHost = (string)($_ENV['DB_HOST'] ?? '127.0.0.1');
$dbPort = (string)($_ENV['DB_PORT'] ?? '3306');
$dbName = (string)($_ENV['DB_NAME'] ?? 'crm_db');
$dbUser = (string)($_ENV['DB_USER'] ?? 'root');
$dbPass = (string)($_ENV['DB_PASS'] ?? '');
$adminEmail = (string)($_ENV['ADMIN_EMAIL'] ?? 'admin@crm.local');

// 2. Backup first: run mysqldump to ~/crm_backup_before_clean_<timestamp>.sql
$homeDir = getenv('HOME') ?: (getenv('USERPROFILE') ?: sys_get_temp_dir());
$timestamp = date('Ymd_His');
$backupPath = rtrim($homeDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "crm_backup_before_clean_{$timestamp}.sql";

echo "\n[Step 1/6] Creating database backup...\n";
$passwordFlag = $dbPass !== '' ? ' -p' . escapeshellarg($dbPass) : '';
$dumpCmd = sprintf(
    'mysqldump -h %s -P %s -u %s%s %s > %s 2>&1',
    escapeshellarg($dbHost),
    escapeshellarg($dbPort),
    escapeshellarg($dbUser),
    $passwordFlag,
    escapeshellarg($dbName),
    escapeshellarg($backupPath)
);

exec($dumpCmd, $dumpOutput, $dumpCode);

if (!file_exists($backupPath) || filesize($backupPath) === 0) {
    fwrite(STDERR, "ERROR: Database backup failed! File not created or empty.\n");
    if (!empty($dumpOutput)) {
        fwrite(STDERR, implode("\n", $dumpOutput) . "\n");
    }
    exit(1);
}

echo "✔ Backup saved: {$backupPath} (" . round(filesize($backupPath) / 1024, 2) . " KB)\n";

// Connect via PDO
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// 3. Run SHOW TABLES
echo "\n[Step 2/6] Inspecting database tables...\n";
$tablesStmt = $pdo->query("SHOW TABLES");
$allTables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

echo "Tables in {$dbName} (" . count($allTables) . " total):\n";
foreach ($allTables as $t) {
    echo "  - {$t}\n";
}

// 4. Empty all data tables
echo "\n[Step 3/6] Emptying data tables...\n";
$keepTables = ['migrations', 'roles', 'permissions', 'role_permissions', 'users'];
$truncateTables = array_diff($allTables, $keepTables);

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
foreach ($truncateTables as $tbl) {
    $pdo->exec("TRUNCATE TABLE `{$tbl}`;");
    echo "  ✔ Truncated: {$tbl}\n";
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// 5. users table: delete all users except admin matching ADMIN_EMAIL
echo "\n[Step 4/6] Cleaning users table...\n";
$delStmt = $pdo->prepare("DELETE FROM `users` WHERE `email` != :admin_email;");
$delStmt->execute(['admin_email' => $adminEmail]);
$deletedUsers = $delStmt->rowCount();
echo "  ✔ Deleted {$deletedUsers} non-admin user(s). Admin user preserved: {$adminEmail}\n";

// 6. Delete all files inside storage/uploads, storage/logs and storage/cache (keeping .gitkeep & .htaccess)
echo "\n[Step 5/6] Cleaning storage directories...\n";
$cleanStorageDirs = [
    $projectRoot . '/storage/uploads',
    $projectRoot . '/storage/logs',
    $projectRoot . '/storage/cache',
];

$deletedFileCount = 0;
foreach ($cleanStorageDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $filename = $item->getFilename();
        // Preserve .gitkeep and .htaccess files
        if ($filename === '.gitkeep' || $filename === '.htaccess') {
            continue;
        }

        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
            $deletedFileCount++;
        }
    }
    echo "  ✔ Cleaned: " . str_replace($projectRoot . '/', '', $dir) . "\n";
}
echo "  Total deleted storage items: {$deletedFileCount}\n";

// 8. Verification
echo "\n[Step 6/6] Verifying database and state...\n";
echo "Row counts across all tables:\n";
echo str_repeat('-', 40) . "\n";
printf("%-25s %10s\n", "Table Name", "Row Count");
echo str_repeat('-', 40) . "\n";

foreach ($allTables as $t) {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    printf("%-25s %10d\n", $t, $count);
}
echo str_repeat('-', 40) . "\n";

echo "\nRemaining users:\n";
$userStmt = $pdo->query("SELECT id, name, email, role_id, is_active FROM `users`");
$users = $userStmt->fetchAll();
foreach ($users as $u) {
    echo "  - ID: {$u['id']} | Name: {$u['name']} | Email: {$u['email']} | Role ID: {$u['role_id']} | Active: {$u['is_active']}\n";
}

// Verify client code numbering restarts from CL-<year>-0001
$currentYear = (int)date('Y');
$prefix = sprintf('CL-%d-', $currentYear);
$codeStmt = $pdo->prepare("SELECT `client_code` FROM `clients` WHERE `client_code` LIKE ? ORDER BY `id` DESC LIMIT 1");
$codeStmt->execute([$prefix . '%']);
$lastCode = $codeStmt->fetchColumn();

if (!$lastCode || !is_string($lastCode)) {
    $nextCode = sprintf('CL-%d-0001', $currentYear);
} else {
    $parts = explode('-', $lastCode);
    $seq = isset($parts[2]) ? (int)$parts[2] + 1 : 1;
    $nextCode = sprintf('CL-%d-%04d', $currentYear, $seq);
}

echo "\nClient Code Numbering Verification:\n";
echo "  Last client code in DB : " . ($lastCode ?: '[None - table is empty]') . "\n";
echo "  Next client code will be: {$nextCode}\n";

if ($nextCode === sprintf('CL-%d-0001', $currentYear)) {
    echo "  ✔ Confirmed: Client code numbering will restart from CL-{$currentYear}-0001.\n";
} else {
    echo "  ⚠ Warning: Client code did not reset to CL-{$currentYear}-0001.\n";
}

// 9. Display backup file path and exact restore command
echo "\n========================================================\n";
echo "✔ CLEANUP COMPLETED SUCCESSFULLY!\n";
echo "========================================================\n";
echo "Backup file:\n  {$backupPath}\n\n";
echo "Exact command to restore if needed:\n";
if ($dbPass !== '') {
    echo "  mysql -h {$dbHost} -P {$dbPort} -u {$dbUser} -p {$dbName} < {$backupPath}\n";
} else {
    echo "  mysql -h {$dbHost} -P {$dbPort} -u {$dbUser} {$dbName} < {$backupPath}\n";
}
echo "========================================================\n";
