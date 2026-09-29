<?php

declare(strict_types=1);

namespace App\Models;

class LoginAttempt extends BaseModel
{
    protected string $table = 'login_attempts';
    protected bool $timestamps = false;
    protected bool $softDelete = false;

    public function record(string $email, string $ip, bool $success): void
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO `login_attempts` (`email`, `ip_address`, `success`, `created_at`)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$email, $ip, $success ? 1 : 0]);
    }
}
