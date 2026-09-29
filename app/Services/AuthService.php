<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Session;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\JobQueue;

class AuthService
{
    private User $userModel;
    private LoginAttempt $loginAttemptModel;
    private PasswordReset $passwordResetModel;

    public function __construct(
        ?User $userModel = null,
        ?LoginAttempt $loginAttemptModel = null,
        ?PasswordReset $passwordResetModel = null
    ) {
        $this->userModel = $userModel ?? new User();
        $this->loginAttemptModel = $loginAttemptModel ?? new LoginAttempt();
        $this->passwordResetModel = $passwordResetModel ?? new PasswordReset();
    }

    /**
     * @return array{success: bool, status_code: int, message: string, data?: array}
     */
    public function login(string $email, string $password, string $ip): array
    {
        $email = trim(strtolower($email));
        $user = $this->userModel->findByEmail($email);

        // 1. Check account lockout status if user exists
        if ($user !== null && !empty($user['locked_until'])) {
            $lockTime = strtotime((string)$user['locked_until']);
            if ($lockTime > time()) {
                $this->loginAttemptModel->record($email, $ip, false);
                Logger::security("Login blocked: account locked", [
                    'email' => $email,
                    'locked_until' => $user['locked_until'],
                ]);

                $minutesRemaining = max(1, (int)ceil(($lockTime - time()) / 60));
                return [
                    'success' => false,
                    'status_code' => 423,
                    'message' => "Account is temporarily locked. Please try again in {$minutesRemaining} minute(s).",
                ];
            }
        }

        // 2. Check if user is inactive
        if ($user !== null && (int)$user['is_active'] !== 1) {
            $this->loginAttemptModel->record($email, $ip, false);
            Logger::security("Login blocked: inactive account", ['email' => $email]);

            return [
                'success' => false,
                'status_code' => 403,
                'message' => 'Account is inactive. Please contact your administrator.',
            ];
        }

        // 3. Verify password (or fail generically if user not found or password wrong)
        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            $this->loginAttemptModel->record($email, $ip, false);
            Logger::security("Login failed: invalid credentials", ['email' => $email]);

            if ($user !== null) {
                $failed = $this->userModel->incrementFailedAttempts(
                    (int)$user['id'],
                    (int)($user['failed_attempts'] ?? 0)
                );

                if ($failed >= 5) {
                    Logger::security("Account locked after 5 failed attempts", [
                        'user_id' => $user['id'],
                        'email' => $email,
                    ]);
                    return [
                        'success' => false,
                        'status_code' => 423,
                        'message' => 'Account is temporarily locked due to multiple failed login attempts. Please try again in 15 minutes.',
                    ];
                }
            }

            return [
                'success' => false,
                'status_code' => 401,
                'message' => 'Invalid email or password',
            ];
        }

        // 4. Successful login
        $this->loginAttemptModel->record($email, $ip, true);
        $this->userModel->resetFailedAttempts((int)$user['id']);

        // Secure session handling
        Session::start();
        Session::regenerate();
        Session::set('user_id', (int)$user['id']);
        Session::set('user_email', $user['email']);
        Session::set('user_name', $user['name']);
        Session::set('role_id', (int)$user['role_id']);

        $roleData = $this->userModel->getRoleAndPermissions((int)$user['role_id']);

        Logger::security("Login successful", [
            'user_id' => $user['id'],
            'email' => $email,
            'role' => $roleData['role']['name'] ?? 'unknown',
        ]);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $roleData['role'],
                    'permissions' => $roleData['permissions'],
                ],
                'redirect' => '/dashboard',
            ],
        ];
    }

    public function logout(): void
    {
        Session::start();
        $userId = Session::get('user_id');
        $email = Session::get('user_email');

        Logger::security("User logged out", [
            'user_id' => $userId,
            'email' => $email,
        ]);

        Session::destroy();
    }

    public function getCurrentUser(): ?array
    {
        Session::start();
        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }

        $user = $this->userModel->find((int)$userId);
        if (!$user) {
            return null;
        }

        unset($user['password_hash']);
        $roleData = $this->userModel->getRoleAndPermissions((int)$user['role_id']);

        return [
            'user' => $user,
            'role' => $roleData['role'],
            'permissions' => $roleData['permissions'],
        ];
    }

    public function sendResetLink(string $email): bool
    {
        $email = trim(strtolower($email));
        $user = $this->userModel->findByEmail($email);

        // Always proceed to mitigate timing/enumeration attacks
        if ($user !== null && (int)$user['is_active'] === 1) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $this->passwordResetModel->createToken($email, $tokenHash, 15);

            $appUrl = rtrim((string)($_ENV['APP_URL'] ?? 'http://localhost/CRM/public'), '/');
            $resetLink = "{$appUrl}/reset-password?token={$token}&email=" . urlencode($email);

            (new JobQueue())->dispatch(
                'App\Services\MailService::sendPasswordReset',
                ['email' => $email, 'resetLink' => $resetLink],
                'email'
            );
        }

        return true;
    }

    /**
     * @return array{success: bool, status_code: int, message: string}
     */
    public function resetPassword(string $email, string $token, string $newPassword): array
    {
        $email = trim(strtolower($email));
        $tokenHash = hash('sha256', $token);

        $reset = $this->passwordResetModel->findValid($email, $tokenHash);
        if (!$reset) {
            Logger::security("Password reset failed: invalid or expired token", ['email' => $email]);
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Invalid or expired password reset token.',
            ];
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => 'User not found.',
            ];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userModel->updatePassword((int)$user['id'], $hash);

        // Single-use token: remove immediately
        $this->passwordResetModel->deleteForEmail($email);

        Logger::security("Password reset successful", [
            'user_id' => $user['id'],
            'email' => $email,
        ]);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ];
    }
}
