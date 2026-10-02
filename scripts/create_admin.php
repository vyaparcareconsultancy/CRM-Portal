<?php

declare(strict_types=1);

/**
 * CRM Admin User Creation / Update CLI Script
 *
 * Usage:
 *   php scripts/create_admin.php
 *   php scripts/create_admin.php --name="Admin User" --email="admin@example.com" --password="Password123456"
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line." . PHP_EOL);
    exit(1);
}

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

// Hidden input helper
function promptHiddenPassword(string $prompt): string
{
    echo $prompt;

    $isTty = function_exists('posix_isatty') ? @posix_isatty(STDIN) : true;
    if (!$isTty) {
        return trim((string)fgets(STDIN));
    }

    if (stripos(PHP_OS, 'WIN') === 0) {
        $command = 'powershell -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
        $pw = @shell_exec($command);
        echo PHP_EOL;
        if ($pw !== null && $pw !== false && trim($pw) !== '') {
            return trim($pw);
        }
        return trim((string)fgets(STDIN));
    }

    $checkStty = @shell_exec('command -v stty 2>/dev/null');
    if (!empty($checkStty)) {
        @shell_exec('stty -echo');
        $pw = trim((string)fgets(STDIN));
        @shell_exec('stty echo');
        echo PHP_EOL;
        return $pw;
    }

    return trim((string)fgets(STDIN));
}

// Ensure stty echo restored on exit/interrupt
if (stripos(PHP_OS, 'WIN') !== 0) {
    register_shutdown_function(static function (): void {
        @shell_exec('stty echo 2>/dev/null');
    });
}

// Parse command line arguments if provided
$cliArgs = [];
$argvList = $_SERVER['argv'] ?? ($argv ?? []);
foreach ($argvList as $arg) {
    if (str_starts_with($arg, '--name=')) {
        $cliArgs['name'] = substr($arg, 7);
    } elseif (str_starts_with($arg, '--email=')) {
        $cliArgs['email'] = substr($arg, 8);
    } elseif (str_starts_with($arg, '--password=')) {
        $cliArgs['password'] = substr($arg, 11);
    }
}

echo "========================================================\n";
echo "  TECH-TIANS CRM — CREATE / UPDATE ADMIN USER\n";
echo "========================================================\n\n";

// 1. Get Name
$name = trim((string)($cliArgs['name'] ?? ''));
while ($name === '' || strlen($name) < 2) {
    echo "Enter Admin Name: ";
    $name = trim((string)fgets(STDIN));
    if ($name === '' || strlen($name) < 2) {
        echo "Error: Name must be at least 2 characters." . PHP_EOL;
    }
}

// 2. Get Email
$email = trim(strtolower((string)($cliArgs['email'] ?? '')));
while ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Enter Admin Email: ";
    $email = trim(strtolower((string)fgets(STDIN)));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Error: Please enter a valid email address." . PHP_EOL;
        $email = '';
    }
}

// 3. Get Password (hidden input, min 12 chars)
$password = (string)($cliArgs['password'] ?? '');
if (strlen($password) < 12) {
    while (true) {
        $password = promptHiddenPassword("Enter Admin Password (min 12 chars): ");
        if (strlen($password) < 12) {
            echo "Error: Password must be at least 12 characters long. Please try again." . PHP_EOL;
            continue;
        }
        break;
    }
}

// Connect to Database
try {
    $pdo = \App\Core\Database::getConnection();
} catch (Throwable $e) {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = (int)($_ENV['DB_PORT'] ?? 3306);
    $dbName = $_ENV['DB_NAME'] ?? 'crm_db';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

try {
    // 1. Fetch or create Admin role
    $stmt = $pdo->prepare("SELECT `id` FROM `roles` WHERE `name` = 'admin' LIMIT 1");
    $stmt->execute();
    $adminRoleId = $stmt->fetchColumn();

    if (!$adminRoleId) {
        $insertRole = $pdo->prepare("INSERT INTO `roles` (`name`, `label`) VALUES ('admin', 'Administrator')");
        $insertRole->execute();
        $adminRoleId = $pdo->lastInsertId();
    }

    $adminRoleId = (int)$adminRoleId;
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 2. Check if user with this email already exists
    $findStmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1");
    $findStmt->execute([$email]);
    $existingId = $findStmt->fetchColumn();

    if ($existingId) {
        $updateStmt = $pdo->prepare("
            UPDATE `users`
            SET `role_id` = :role_id,
                `name` = :name,
                `password_hash` = :password_hash,
                `is_active` = 1,
                `failed_attempts` = 0,
                `locked_until` = NULL,
                `deleted_at` = NULL,
                `updated_at` = NOW()
            WHERE `id` = :id
        ");
        $updateStmt->execute([
            ':role_id' => $adminRoleId,
            ':name' => $name,
            ':password_hash' => $passwordHash,
            ':id' => $existingId,
        ]);

        echo PHP_EOL . "Success: Admin user '{$email}' (ID: {$existingId}) updated successfully with the Admin role." . PHP_EOL;
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO `users` (`role_id`, `name`, `email`, `password_hash`, `is_active`, `created_at`, `updated_at`)
            VALUES (:role_id, :name, :email, :password_hash, 1, NOW(), NOW())
        ");
        $insertStmt->execute([
            ':role_id' => $adminRoleId,
            ':name' => $name,
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);
        $newId = $pdo->lastInsertId();

        echo PHP_EOL . "Success: Admin user '{$email}' (ID: {$newId}) created successfully with the Admin role." . PHP_EOL;
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . "Error: Failed to create or update admin user: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
