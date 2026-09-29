<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Session.php';
require_once dirname(__DIR__) . '/app/Core/Request.php';
require_once dirname(__DIR__) . '/app/Core/Response.php';
require_once dirname(__DIR__) . '/app/Services/RateLimiter/RateLimitStoreInterface.php';
require_once dirname(__DIR__) . '/app/Services/RateLimiter/DatabaseRateLimitStore.php';
require_once dirname(__DIR__) . '/app/Services/RateLimiter/RedisRateLimitStore.php';
require_once dirname(__DIR__) . '/app/Services/RateLimiter/RateLimiter.php';
require_once dirname(__DIR__) . '/app/Middleware/RateLimitMiddleware.php';
require_once dirname(__DIR__) . '/app/Services/TurnstileService.php';

use App\Core\Database;
use App\Core\Request;
use App\Middleware\RateLimitMiddleware;
use App\Services\RateLimiter\DatabaseRateLimitStore;
use App\Services\RateLimiter\RateLimiter;
use App\Services\TurnstileService;

function rateAssert(bool $condition, string $msg): void
{
    if (!$condition) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running RateLimiter & Turnstile Component Checks...\n";

// -------------------------------------------------------------
// 1. Setup in-memory SQLite database
// -------------------------------------------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
Database::setConnection($pdo);

$pdo->exec("
    CREATE TABLE rate_limits (
        `key` VARCHAR(191) PRIMARY KEY,
        `hits` INTEGER NOT NULL DEFAULT 1,
        `reset_at` TEXT NOT NULL,
        `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
    );
");

// -------------------------------------------------------------
// 2. Test DatabaseRateLimitStore
// -------------------------------------------------------------
$store = new DatabaseRateLimitStore($pdo);
$limiter = new RateLimiter($store);

$key = 'test:login:127.0.0.1';

rateAssert($limiter->attempts($key) === 0, "Initial attempts for fresh key is 0");
rateAssert(!$limiter->tooManyAttempts($key, 5), "Not throttled on fresh key");

// 1st hit
$hits1 = $limiter->hit($key, 60);
rateAssert($hits1 === 1, "First hit returns 1 ({$hits1})");
rateAssert($limiter->attempts($key) === 1, "Attempts count is 1");
rateAssert($limiter->remaining($key, 5) === 4, "Remaining attempts is 4");
rateAssert($limiter->availableIn($key) > 50, "AvailableIn is > 50s");

// 4 more hits to reach 5
$limiter->hit($key, 60);
$limiter->hit($key, 60);
$limiter->hit($key, 60);
$hits5 = $limiter->hit($key, 60);
rateAssert($hits5 === 5, "Fifth hit returns 5 ({$hits5})");
rateAssert($limiter->tooManyAttempts($key, 5), "Too many attempts reached at 5");
rateAssert($limiter->remaining($key, 5) === 0, "Remaining attempts is 0");

// Clear key
$limiter->clear($key);
rateAssert($limiter->attempts($key) === 0, "Attempts count reset to 0 after clear");
rateAssert(!$limiter->tooManyAttempts($key, 5), "Not throttled after clear");

// -------------------------------------------------------------
// 3. Test Key Resolution in RateLimitMiddleware
// -------------------------------------------------------------
$middleware = new RateLimitMiddleware($limiter);

// Login key: per IP + email
$_SERVER['REMOTE_ADDR'] = '192.168.1.50';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/login';
$loginReq = new Request([], ['email' => 'user@example.com'], [], [], $_SERVER);

$loginKey = $middleware->resolveKey($loginReq, '192.168.1.50', null, 'login');
$expectedLoginHash = md5('192.168.1.50|user@example.com');
rateAssert($loginKey === "throttle:login:{$expectedLoginHash}", "Login key combines IP and lowercase email correctly ({$loginKey})");

// Different email produces different key from same IP
$diffEmailReq = new Request([], ['email' => 'other@example.com'], [], [], $_SERVER);
$otherLoginKey = $middleware->resolveKey($diffEmailReq, '192.168.1.50', null, 'login');
rateAssert($loginKey !== $otherLoginKey, "Different email generates distinct rate limit key");

// Client creation key: per user
$_SERVER['REQUEST_URI'] = '/api/clients';
$clientCreateReq = new Request([], [], [], [], $_SERVER);
$createKey = $middleware->resolveKey($clientCreateReq, '192.168.1.50', 42, 'client_create');
rateAssert($createKey === 'throttle:client_create:user:42', "Client create key scopes to user ID 42 ({$createKey})");

// Export key: per user
$_SERVER['REQUEST_URI'] = '/api/clients/export';
$exportReq = new Request([], [], [], [], $_SERVER);
$exportKey = $middleware->resolveKey($exportReq, '192.168.1.50', 10, 'export');
rateAssert($exportKey === 'throttle:export:user:10', "Export key scopes to user ID 10 ({$exportKey})");

// General API key: per user (fallback to IP)
$generalKeyUser = $middleware->resolveKey($clientCreateReq, '192.168.1.50', 5, null);
rateAssert($generalKeyUser === 'throttle:api:user:5', "General API key uses user:5");
$generalKeyIp = $middleware->resolveKey($clientCreateReq, '192.168.1.50', null, null);
rateAssert($generalKeyIp === 'throttle:api:ip:192.168.1.50', "General API key falls back to IP when unauthenticated");

// -------------------------------------------------------------
// 4. Test Cloudflare Turnstile Service
// -------------------------------------------------------------
$turnstile = new TurnstileService();

// By default in local development, TURNSTILE_ENABLED is false
$_ENV['TURNSTILE_ENABLED'] = 'false';
rateAssert(!$turnstile->isEnabled(), "Turnstile is disabled when TURNSTILE_ENABLED=false");
rateAssert($turnstile->verify(null), "Turnstile verification passes when disabled (skippable in local)");

// When enabled but without token
$_ENV['TURNSTILE_ENABLED'] = 'true';
$_ENV['TURNSTILE_SITE_KEY'] = '0x4AAAAAAtestSiteKey';
$_ENV['TURNSTILE_SECRET_KEY'] = '0x4AAAAAAtestSecretKey';

rateAssert($turnstile->isEnabled(), "Turnstile is enabled when configured with key");
rateAssert(!$turnstile->verify(null), "Turnstile rejects null token when enabled");
rateAssert(!$turnstile->verify(''), "Turnstile rejects empty string token when enabled");

// Reset to disabled for clean local dev
$_ENV['TURNSTILE_ENABLED'] = 'false';
$_ENV['TURNSTILE_SITE_KEY'] = '';
$_ENV['TURNSTILE_SECRET_KEY'] = '';

echo "\nAll RateLimiter & Turnstile Tests Passed Successfully!\n";
