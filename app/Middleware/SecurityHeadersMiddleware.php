<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

class SecurityHeadersMiddleware
{
    /**
     * Apply security hardening HTTP response headers.
     */
    public static function apply(): void
    {
        if (headers_sent()) {
            return;
        }

        // 1. Content-Security-Policy (self only, with safe inline scripts and data URIs for bundled assets)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");

        // 2. Anti-clickjacking
        header("X-Frame-Options: DENY");

        // 3. Disable MIME-sniffing
        header("X-Content-Type-Options: nosniff");

        // 4. Strict referrer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");

        // 5. Modern permissions policy
        header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()");

        // 6. HTTP Strict Transport Security (HSTS) when running over HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        if ($isHttps) {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        }
    }

    public function handle(Request $request): void
    {
        self::apply();
    }
}
