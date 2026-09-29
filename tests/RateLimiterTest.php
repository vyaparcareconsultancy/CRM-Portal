<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Request;
use App\Middleware\RateLimitMiddleware;
use App\Services\RateLimiter\DatabaseRateLimitStore;
use App\Services\RateLimiter\RateLimiter;
use App\Services\TurnstileService;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private PDO $pdo;
    private DatabaseRateLimitStore $store;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        Database::setConnection($this->pdo);

        $this->pdo->exec("
            CREATE TABLE rate_limits (
                `key` VARCHAR(191) PRIMARY KEY,
                `hits` INTEGER NOT NULL DEFAULT 1,
                `reset_at` TEXT NOT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->store = new DatabaseRateLimitStore($this->pdo);
        $this->limiter = new RateLimiter($this->store);
    }

    public function testHitIncrementsAttemptsAndRespectsThreshold(): void
    {
        $key = 'test:throttle:key1';

        $this->assertSame(0, $this->limiter->attempts($key));
        $this->assertFalse($this->limiter->tooManyAttempts($key, 3));

        $hits = $this->limiter->hit($key, 60);
        $this->assertSame(1, $hits);
        $this->assertSame(1, $this->limiter->attempts($key));

        $this->limiter->hit($key, 60);
        $this->limiter->hit($key, 60);
        $this->assertSame(3, $this->limiter->attempts($key));
        $this->assertTrue($this->limiter->tooManyAttempts($key, 3));

        $this->limiter->clear($key);
        $this->assertSame(0, $this->limiter->attempts($key));
        $this->assertFalse($this->limiter->tooManyAttempts($key, 3));
    }

    public function testRemainingAndAvailableIn(): void
    {
        $key = 'test:remaining';
        $this->assertSame(5, $this->limiter->remaining($key, 5));

        $this->limiter->hit($key, 30);
        $this->assertSame(4, $this->limiter->remaining($key, 5));
        $this->assertGreaterThan(20, $this->limiter->availableIn($key));
    }

    public function testMiddlewareKeyResolutionForSpecialEndpoints(): void
    {
        $middleware = new RateLimitMiddleware($this->limiter);

        // Login key
        $loginReq = new Request([], ['email' => 'User@Test.Com'], [], [], [
            'REQUEST_URI' => '/api/auth/login',
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '10.0.0.1'
        ]);
        $loginKey = $middleware->resolveKey($loginReq, '10.0.0.1', null, 'login');
        $expected = 'throttle:login:' . md5('10.0.0.1|user@test.com');
        $this->assertSame($expected, $loginKey);

        // Forgot password key
        $forgotReq = new Request([], ['email' => 'User@Test.Com'], [], [], [
            'REQUEST_URI' => '/api/auth/forgot',
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '10.0.0.1'
        ]);
        $forgotKey = $middleware->resolveKey($forgotReq, '10.0.0.1', null, 'forgot');
        $this->assertSame('throttle:forgot:' . md5('10.0.0.1|user@test.com'), $forgotKey);

        // Client create key
        $createReq = new Request([], [], [], [], [
            'REQUEST_URI' => '/api/clients',
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '10.0.0.1'
        ]);
        $createKey = $middleware->resolveKey($createReq, '10.0.0.1', 88, 'client_create');
        $this->assertSame('throttle:client_create:user:88', $createKey);

        // Export key
        $exportReq = new Request([], [], [], [], [
            'REQUEST_URI' => '/api/clients/export',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '10.0.0.1'
        ]);
        $exportKey = $middleware->resolveKey($exportReq, '10.0.0.1', 88, 'export');
        $this->assertSame('throttle:export:user:88', $exportKey);
    }

    public function testTurnstileServiceSkippableInLocal(): void
    {
        $_ENV['TURNSTILE_ENABLED'] = 'false';
        $turnstile = new TurnstileService();

        $this->assertFalse($turnstile->isEnabled());
        $this->assertTrue($turnstile->verify(null));
        $this->assertTrue($turnstile->verify('any_token'));
    }
}
