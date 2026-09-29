<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\AuthService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private PDO $pdo;
    private AuthService $authService;
    private User $userModel;
    private LoginAttempt $loginAttemptModel;
    private PasswordReset $passwordResetModel;

    protected function setUp(): void
    {
        // Setup in-memory SQLite schema
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        Database::setConnection($this->pdo);

        $this->pdo->exec("
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                label TEXT
            );
            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                label TEXT
            );
            CREATE TABLE role_permissions (
                role_id INTEGER,
                permission_id INTEGER,
                PRIMARY KEY (role_id, permission_id)
            );
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INTEGER,
                name TEXT,
                email TEXT UNIQUE,
                password_hash TEXT,
                is_active INTEGER DEFAULT 1,
                failed_attempts INTEGER DEFAULT 0,
                locked_until TEXT NULL,
                last_login_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT,
                ip_address TEXT,
                success INTEGER,
                created_at TEXT
            );
            CREATE TABLE password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT,
                token_hash TEXT,
                expires_at TEXT,
                created_at TEXT
            );
        ");

        // Seed basic role & permissions
        $this->pdo->exec("INSERT INTO roles (id, name, label) VALUES (1, 'admin', 'Administrator')");
        $this->pdo->exec("INSERT INTO permissions (id, name, label) VALUES (1, 'client.create', 'Create Client')");
        $this->pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1)");

        // Seed test active user (Password: Secret@123)
        $hash = password_hash('Secret@123', PASSWORD_DEFAULT);
        $this->pdo->exec("
            INSERT INTO users (id, role_id, name, email, password_hash, is_active, failed_attempts)
            VALUES (1, 1, 'Test Admin', 'admin@example.com', '{$hash}', 1, 0)
        ");

        // Seed inactive user
        $this->pdo->exec("
            INSERT INTO users (id, role_id, name, email, password_hash, is_active, failed_attempts)
            VALUES (2, 1, 'Inactive User', 'inactive@example.com', '{$hash}', 0, 0)
        ");

        $this->userModel = new User($this->pdo);
        $this->loginAttemptModel = new LoginAttempt($this->pdo);
        $this->passwordResetModel = new PasswordReset($this->pdo);

        $this->authService = new AuthService(
            $this->userModel,
            $this->loginAttemptModel,
            $this->passwordResetModel
        );

        // Reset session state
        $_SESSION = [];
    }

    public function testSuccessfulLogin(): void
    {
        $res = $this->authService->login('admin@example.com', 'Secret@123', '127.0.0.1');

        $this->assertTrue($res['success']);
        $this->assertSame(200, $res['status_code']);
        $this->assertSame('Login successful', $res['message']);
        $this->assertSame(1, Session::get('user_id'));
        $this->assertSame('admin@example.com', Session::get('user_email'));

        // Verify successful attempt was recorded
        $attempts = $this->pdo->query("SELECT * FROM login_attempts WHERE email = 'admin@example.com'")->fetchAll();
        $this->assertCount(1, $attempts);
        $this->assertSame(1, (int)$attempts[0]['success']);
    }

    public function testFailedLoginGivesGenericError(): void
    {
        // 1. Wrong password
        $res = $this->authService->login('admin@example.com', 'WrongPassword', '127.0.0.1');
        $this->assertFalse($res['success']);
        $this->assertSame(401, $res['status_code']);
        $this->assertSame('Invalid email or password', $res['message']);

        // 2. Non-existent email gives same generic message
        $resUnknown = $this->authService->login('ghost@example.com', 'Secret@123', '127.0.0.1');
        $this->assertFalse($resUnknown['success']);
        $this->assertSame(401, $resUnknown['status_code']);
        $this->assertSame('Invalid email or password', $resUnknown['message']);
    }

    public function testAccountLockoutAfterFiveFailedAttempts(): void
    {
        // Attempts 1 to 4 fail with standard 401
        for ($i = 1; $i <= 4; $i++) {
            $res = $this->authService->login('admin@example.com', 'BadPass' . $i, '127.0.0.1');
            $this->assertFalse($res['success']);
            $this->assertSame(401, $res['status_code']);
            $this->assertSame('Invalid email or password', $res['message']);

            $user = $this->userModel->findByEmail('admin@example.com');
            $this->assertSame($i, (int)$user['failed_attempts']);
            $this->assertNull($user['locked_until']);
        }

        // 5th attempt triggers lockout
        $res5 = $this->authService->login('admin@example.com', 'BadPass5', '127.0.0.1');
        $this->assertFalse($res5['success']);
        $this->assertSame(423, $res5['status_code']);
        $this->assertStringContainsString('temporarily locked', $res5['message']);

        $user = $this->userModel->findByEmail('admin@example.com');
        $this->assertSame(5, (int)$user['failed_attempts']);
        $this->assertNotNull($user['locked_until']);

        // 6th attempt even with correct password is blocked while locked
        $res6 = $this->authService->login('admin@example.com', 'Secret@123', '127.0.0.1');
        $this->assertFalse($res6['success']);
        $this->assertSame(423, $res6['status_code']);
        $this->assertStringContainsString('temporarily locked', $res6['message']);
    }

    public function testInactiveUserIsBlocked(): void
    {
        $res = $this->authService->login('inactive@example.com', 'Secret@123', '127.0.0.1');
        $this->assertFalse($res['success']);
        $this->assertSame(403, $res['status_code']);
        $this->assertStringContainsString('inactive', $res['message']);
    }

    public function testForgotPasswordAndResetFlow(): void
    {
        // 1. Request reset link
        $sent = $this->authService->sendResetLink('admin@example.com');
        $this->assertTrue($sent);

        // Retrieve token from database
        $row = $this->pdo->query("SELECT * FROM password_resets WHERE email = 'admin@example.com'")->fetch();
        $this->assertNotEmpty($row);
        $tokenHash = $row['token_hash'];

        // 2. Cannot reset with bad token
        $badReset = $this->authService->resetPassword('admin@example.com', 'wrong_token', 'NewSecret@999');
        $this->assertFalse($badReset['success']);
        $this->assertSame(400, $badReset['status_code']);

        // 3. Reset successfully by simulating matching token
        // Create known token
        $rawToken = 'my_secret_token_123';
        $knownHash = hash('sha256', $rawToken);
        $this->passwordResetModel->createToken('admin@example.com', $knownHash, 15);

        $goodReset = $this->authService->resetPassword('admin@example.com', $rawToken, 'BrandNew@123');
        $this->assertTrue($goodReset['success']);
        $this->assertSame(200, $goodReset['status_code']);

        // 4. Token must be single-use (cleared)
        $secondAttempt = $this->authService->resetPassword('admin@example.com', $rawToken, 'BrandNew@123');
        $this->assertFalse($secondAttempt['success']);

        // 5. User can now login with new password
        $loginRes = $this->authService->login('admin@example.com', 'BrandNew@123', '127.0.0.1');
        $this->assertTrue($loginRes['success']);
    }

    public function testSecurityLogSanitizesPasswords(): void
    {
        $tempLog = tempnam(sys_get_temp_dir(), 'sec_');
        Logger::security('Test auth event', [
            'email' => 'admin@example.com',
            'password' => 'SuperSecretPassword',
            'token' => 'raw_token_xyz',
        ]);

        $date = date('Y-m-d');
        $logFile = dirname(__DIR__) . "/storage/logs/security-{$date}.log";

        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $this->assertStringNotContainsString('SuperSecretPassword', $content);
            $this->assertStringNotContainsString('raw_token_xyz', $content);
            $this->assertStringContainsString('[REDACTED]', $content);
        }
    }
}
