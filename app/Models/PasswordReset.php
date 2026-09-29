<?php

declare(strict_types=1);

namespace App\Models;

class PasswordReset extends BaseModel
{
    protected string $table = 'password_resets';
    protected bool $timestamps = false;
    protected bool $softDelete = false;

    public function createToken(string $email, string $tokenHash, int $minutes = 15): void
    {
        $this->deleteForEmail($email);

        $expiresAt = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $stmt = $this->getPdo()->prepare("
            INSERT INTO `password_resets` (`email`, `token_hash`, `expires_at`, `created_at`)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$email, $tokenHash, $expiresAt]);
    }

    public function findValid(string $email, string $tokenHash): ?array
    {
        $stmt = $this->getPdo()->prepare("
            SELECT * FROM `password_resets`
            WHERE `email` = ? AND `token_hash` = ? AND `expires_at` > NOW()
            LIMIT 1
        ");
        $stmt->execute([$email, $tokenHash]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function deleteForEmail(string $email): void
    {
        $stmt = $this->getPdo()->prepare("DELETE FROM `password_resets` WHERE `email` = ?");
        $stmt->execute([$email]);
    }
}
