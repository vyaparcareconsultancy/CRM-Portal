<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\RateLimiter\RateLimiter;

class RateLimitMiddleware
{
    private RateLimiter $limiter;

    public function __construct(?RateLimiter $limiter = null)
    {
        $this->limiter = $limiter ?? new RateLimiter();
    }

    /**
     * Handle incoming request rate limiting.
     *
     * @param array<string, string> $params
     * @param string|null $options Parameter string, e.g. "5,15" (5 hits per 15 min) or "5,15,login"
     */
    public function handle(array $params = [], ?string $options = null): bool
    {
        $request = Request::createFromGlobals();
        $ip = $request->ip();

        Session::start();
        $userId = Session::get('user_id');

        // Parse options: maxAttempts, decayMinutes, [keyType]
        $maxAttempts = 60;
        $decayMinutes = 1;
        $keyType = null;

        if ($options !== null && $options !== '') {
            $parts = explode(',', $options);
            $maxAttempts = !empty($parts[0]) ? (int)$parts[0] : 60;
            $decayMinutes = !empty($parts[1]) ? (int)$parts[1] : 1;
            $keyType = !empty($parts[2]) ? trim($parts[2]) : null;
        }

        $decaySeconds = max(1, $decayMinutes * 60);

        // Derive rate limit cache key
        $key = $this->resolveKey($request, $ip, $userId, $keyType);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);
            if ($retryAfter <= 0) {
                $retryAfter = 1;
            }

            if (!headers_sent()) {
                header("Retry-After: {$retryAfter}");
                header("X-RateLimit-Limit: {$maxAttempts}");
                header("X-RateLimit-Remaining: 0");
            }

            \App\Core\Logger::security("Rate limit exceeded", [
                'throttle_key' => $key,
                'max_attempts' => $maxAttempts,
                'retry_after' => $retryAfter,
                'ip' => $ip,
                'user_id' => $userId,
            ]);

            Response::error("Too many requests, try after {$retryAfter} seconds", 429);
            return false;
        }

        $hits = $this->limiter->hit($key, $decaySeconds);
        $remaining = max(0, $maxAttempts - $hits);

        if (!headers_sent()) {
            header("X-RateLimit-Limit: {$maxAttempts}");
            header("X-RateLimit-Remaining: {$remaining}");
        }

        return true;
    }

    /**
     * Resolve the rate limiting key based on endpoint type, IP, email, or user ID.
     */
    public function resolveKey(Request $request, string $ip, mixed $userId, ?string $keyType): string
    {
        $uri = $request->path();
        $method = $request->method();

        // 1. Login rate limit: per IP + email
        if ($keyType === 'login' || str_contains($uri, '/api/auth/login')) {
            $email = strtolower(trim((string)$request->body('email', '')));
            $hash = md5($ip . '|' . $email);
            return "throttle:login:{$hash}";
        }

        // 2. Forgot password rate limit: per IP + email
        if ($keyType === 'forgot' || str_contains($uri, '/api/auth/forgot')) {
            $email = strtolower(trim((string)$request->body('email', '')));
            $hash = md5($ip . '|' . $email);
            return "throttle:forgot:{$hash}";
        }

        // 3. Client creation rate limit: per user
        if ($keyType === 'client_create' || ($method === 'POST' && preg_match('#^/api/clients/?$#', $uri))) {
            $actor = $userId ? "user:{$userId}" : "ip:{$ip}";
            return "throttle:client_create:{$actor}";
        }

        // 4. Export rate limit: per user
        if ($keyType === 'export' || str_contains($uri, '/api/clients/export')) {
            $actor = $userId ? "user:{$userId}" : "ip:{$ip}";
            return "throttle:export:{$actor}";
        }

        // 5. General API rate limit: per user (fallback to IP for unauthenticated calls)
        $prefix = $keyType ?? 'api';
        $actor = $userId ? "user:{$userId}" : "ip:{$ip}";
        return "throttle:{$prefix}:{$actor}";
    }
}
