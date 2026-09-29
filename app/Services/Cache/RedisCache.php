<?php

declare(strict_types=1);

namespace App\Services\Cache;

use RuntimeException;

/**
 * Optional Redis cache driver.
 * Activated by setting CACHE_DRIVER=redis in .env when Redis is available.
 */
class RedisCache implements CacheInterface
{
    private mixed $redis = null;
    private string $prefix;

    public function __construct(mixed $redis = null, string $prefix = 'crm:cache:')
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

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->redis === null) {
            return $default;
        }

        $raw = $this->redis->get($this->prefix . $key);
        if ($raw === false || $raw === null) {
            return $default;
        }

        return @unserialize($raw) ?: $default;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if ($this->redis === null) {
            throw new RuntimeException("Redis cache client not configured or php-redis extension missing.");
        }

        $serialized = serialize($value);
        return (bool)$this->redis->setex($this->prefix . $key, max(1, $ttl), $serialized);
    }

    public function delete(string $key): bool
    {
        if ($this->redis === null) {
            return true;
        }

        return (bool)$this->redis->del($this->prefix . $key);
    }

    public function has(string $key): bool
    {
        if ($this->redis === null) {
            return false;
        }

        return (int)$this->redis->exists($this->prefix . $key) > 0;
    }

    public function forgetByPrefix(string $prefix): bool
    {
        if ($this->redis === null) {
            return true;
        }

        $pattern = $this->prefix . $prefix . '*';
        $keys = $this->redis->keys($pattern);

        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        return true;
    }

    public function clear(): bool
    {
        return $this->forgetByPrefix('');
    }
}
