<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service to handle Cloudflare Turnstile CAPTCHA verification.
 * Configurable via .env and safely skippable in local/testing environments.
 */
class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isEnabled(): bool
    {
        $enabled = filter_var($_ENV['TURNSTILE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $secret = trim((string)($_ENV['TURNSTILE_SECRET_KEY'] ?? ''));

        return $enabled && $secret !== '';
    }

    public function getSiteKey(): string
    {
        return trim((string)($_ENV['TURNSTILE_SITE_KEY'] ?? ''));
    }

    /**
     * Verify the client-provided Turnstile response token.
     * Returns true if verification succeeded, or if Turnstile is disabled.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if ($token === null || trim($token) === '') {
            \App\Core\Logger::security("Turnstile verification failed: missing token", ['ip' => $ip]);
            return false;
        }

        $secret = (string)$_ENV['TURNSTILE_SECRET_KEY'];
        $postData = [
            'secret' => $secret,
            'response' => trim($token),
        ];

        if ($ip !== null && $ip !== '') {
            $postData['remoteip'] = $ip;
        }

        $body = http_build_query($postData);

        if (function_exists('curl_init')) {
            $ch = curl_init(self::VERIFY_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $err = curl_error($ch);

            if ($response === false || !empty($err)) {
                return false;
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n",
                    'content' => $body,
                    'timeout' => 5,
                ],
            ]);
            $response = @file_get_contents(self::VERIFY_URL, false, $context);
            if ($response === false) {
                return false;
            }
        }

        $result = json_decode((string)$response, true);

        return is_array($result) && !empty($result['success']);
    }
}
