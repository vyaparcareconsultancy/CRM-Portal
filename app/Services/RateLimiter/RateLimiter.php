<?php

declare(strict_types=1);

namespace App\Services\RateLimiter;

class RateLimiter
{
    private RateLimitStoreInterface $store;

    public function __construct(?RateLimitStoreInterface $store = null)
    {
        if ($store !== null) {
            $this->store = $store;
        } else {
            $driver = strtolower((string)($_ENV['RATE_LIMIT_DRIVER'] ?? 'database'));
            if ($driver === 'redis' && extension_loaded('redis')) {
                $this->store = new RedisRateLimitStore();
            } else {
                $this->store = new DatabaseRateLimitStore();
            }
        }
    }

    /**
     * Check if a given key has exceeded its maximum attempts.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /**
     * Increment the counter for a given key for a decay duration in seconds.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        return $this->store->hit($key, $decaySeconds);
    }

    /**
     * Get the current attempts for a given key.
     */
    public function attempts(string $key): int
    {
        return $this->store->attempts($key);
    }

    /**
     * Get the remaining attempts for a given key.
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Get the number of seconds until the key is available again.
     */
    public function availableIn(string $key): int
    {
        return $this->store->availableIn($key);
    }

    /**
     * Clear / reset the counter for a given key.
     */
    public function clear(string $key): void
    {
        $this->store->reset($key);
    }

    /**
     * Alias for clear.
     */
    public function reset(string $key): void
    {
        $this->clear($key);
    }

    public function getStore(): RateLimitStoreInterface
    {
        return $this->store;
    }
}
