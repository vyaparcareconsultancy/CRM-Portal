<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Core/Logger.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Models/BaseModel.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/LoginAttempt.php';
require_once dirname(__DIR__) . '/app/Models/PasswordReset.php';
require_once dirname(__DIR__) . '/app/Services/MailService.php';
require_once dirname(__DIR__) . '/app/Services/AuthService.php';

use App\Core\Database;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\AuthService;

function authAssert(bool $condition, string $msg): void
{
    if (!$condition) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Authentication & Lockout Tests (Standalone)...\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

Database::setConnection($pdo);

$pdo->exec("
    CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT, label TEXT);
    CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT, label TEXT);
    CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER);
    CREATE TABLE users (
        id INTEGER PRIMARY KEY, role_id INTEGER, name TEXT, email TEXT,
        password_hash TEXT, is_active INTEGER DEFAULT 1, failed_attempts INTEGER DEFAULT 0,
        locked_until TEXT NULL, last_login_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL, deleted_at TEXT NULL
    );
    CREATE TABLE login_attempts (id INTEGER PRIMARY KEY, email TEXT, ip_address TEXT, success INTEGER, created_at TEXT);
    CREATE TABLE password_resets (id INTEGER PRIMARY KEY, email TEXT, token_hash TEXT, expires_at TEXT, created_at TEXT);
");

$hash = password_hash('AdminPass@123', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO roles (id, name, label) VALUES (1, 'admin', 'Administrator')");
$pdo->exec("INSERT INTO permissions (id, name, label) VALUES (1, 'client.create', 'Create Client')");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, password_hash, is_active, failed_attempts) VALUES (1, 1, 'Admin', 'admin@crm.local', '{$hash}', 1, 0)");
$pdo->exec("INSERT INTO users (id, role_id, name, email, password_hash, is_active, failed_attempts) VALUES (2, 1, 'Disabled', 'disabled@crm.local', '{$hash}', 0, 0)");

$userModel = new User($pdo);
$loginAttemptModel = new LoginAttempt($pdo);
$passwordResetModel = new PasswordReset($pdo);
$authService = new AuthService($userModel, $loginAttemptModel, $passwordResetModel);

// 1. Success login
$res = $authService->login('admin@crm.local', 'AdminPass@123', '127.0.0.1');
authAssert($res['success'] && $res['status_code'] === 200, 'Successful login with valid credentials');

// 2. Generic error on bad password or email
$resBad = $authService->login('admin@crm.local', 'wrong', '127.0.0.1');
authAssert(!$resBad['success'] && $resBad['status_code'] === 401 && $resBad['message'] === 'Invalid email or password', 'Generic error message on bad password');

$resGhost = $authService->login('ghost@crm.local', 'wrong', '127.0.0.1');
authAssert(!$resGhost['success'] && $resGhost['status_code'] === 401 && $resGhost['message'] === 'Invalid email or password', 'Generic error message on non-existent email');

// 3. Inactive account blocked
$resInactive = $authService->login('disabled@crm.local', 'AdminPass@123', '127.0.0.1');
authAssert(!$resInactive['success'] && $resInactive['status_code'] === 403, 'Inactive account is blocked');

// 4. Lockout on 5 consecutive failures
// Note: $resBad above was attempt 1
for ($i = 2; $i <= 4; $i++) {
    $resFail = $authService->login('admin@crm.local', 'wrong' . $i, '127.0.0.1');
    authAssert(!$resFail['success'] && $resFail['status_code'] === 401, "Failed attempt {$i} returns 401");
}

// 5th attempt locks the account
$resLock = $authService->login('admin@crm.local', 'wrong5', '127.0.0.1');
authAssert(!$resLock['success'] && $resLock['status_code'] === 423 && str_contains($resLock['message'], 'locked'), '5th failed attempt locks account with 423');

// 6th attempt with correct password still blocked
$resBlocked = $authService->login('admin@crm.local', 'AdminPass@123', '127.0.0.1');
authAssert(!$resBlocked['success'] && $resBlocked['status_code'] === 423, 'Locked account rejects correct password');

// 5. Password Reset Token flow
$rawToken = 'reset_token_test_123';
$tokenHash = hash('sha256', $rawToken);
$passwordResetModel->createToken('admin@crm.local', $tokenHash, 15);

$resetRes = $authService->resetPassword('admin@crm.local', $rawToken, 'NewSecret@456');
authAssert($resetRes['success'], 'Password reset succeeds with valid token');

// Single use check
$resetRes2 = $authService->resetPassword('admin@crm.local', $rawToken, 'NewSecret@456');
authAssert(!$resetRes2['success'], 'Token is single-use and fails on re-use');

// New password works and clears lockout
$loginNew = $authService->login('admin@crm.local', 'NewSecret@456', '127.0.0.1');
authAssert($loginNew['success'], 'Can log in with new password');

echo "\nAll authentication and lockout checks passed successfully!\n";
