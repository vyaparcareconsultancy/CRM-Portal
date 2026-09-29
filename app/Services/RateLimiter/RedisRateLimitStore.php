<?php

declare(strict_types=1);

namespace App\Services\RateLimiter;

use RuntimeException;

/**
 * Pluggable Redis store for rate limiting.
 * Can be activated by switching RATE_LIMIT_DRIVER=redis in .env when Redis is available.
 */
class RedisRateLimitStore implements RateLimitStoreInterface
{
    private mixed $redis;
    private string $prefix;

    public function __construct(mixed $redis = null, string $prefix = 'crm:ratelimit:')
    {
        $this->prefix = $prefix;

        if ($redis !== null) {
            $this->redis = $redis;
        } elseif (extension_loaded('redis')) {
            $client = new \Redis();
            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $timeout = (float)($_ENV['REDIS_TIMEOUT'] ?? 1.5);
            $client->connect($host, $port, $timeout);
            if (!empty($_ENV['REDIS_PASSWORD'])) {
                $client->auth($_ENV['REDIS_PASSWORD']);
            }
            $this->redis = $client;
        }
    }

    public function hit(string $key, int $decaySeconds): int
    {
        if ($this->redis === null) {
            throw new RuntimeException("Redis client not configured or php-redis extension missing.");
        }

        $redisKey = $this->prefix . $key;
        $hits = (int)$this->redis->incr($redisKey);

        if ($hits === 1) {
            $this->redis->expire($redisKey, $decaySeconds);
        }

        return $hits;
    }

    public function attempts(string $key): int
    {
        if ($this->redis === null) {
            return 0;
        }

        $redisKey = $this->prefix . $key;
        $val = $this->redis->get($redisKey);

        return $val !== false ? (int)$val : 0;
    }

    public function availableIn(string $key): int
    {
        if ($this->redis === null) {
            return 0;
        }

        $redisKey = $this->prefix . $key;
        $ttl = (int)$this->redis->ttl($redisKey);

        return max(0, $ttl);
    }

    public function reset(string $key): void
    {
        if ($this->redis !== null) {
            $this->redis->del($this->prefix . $key);
        }
    }
}
