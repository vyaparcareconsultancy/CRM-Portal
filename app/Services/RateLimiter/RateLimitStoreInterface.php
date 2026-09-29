<?php

declare(strict_types=1);

namespace App\Services\RateLimiter;

/**
 * Interface for pluggable rate limit storage (Database, Redis, Memcached, Memory, etc.).
 */
interface RateLimitStoreInterface
{
    /**
     * Increment the hit count for a given key with a decay period in seconds.
     * If the key does not exist or has expired, initializes it with 1 hit.
     *
     * @return int Current hit count after increment
     */
    public function hit(string $key, int $decaySeconds): int;

    /**
     * Get the current hit count for a given key.
     * Returns 0 if key has expired or does not exist.
     */
    public function attempts(string $key): int;

    /**
     * Get the number of seconds until the key is available again.
     * Returns 0 if key is already available or not found.
     */
    public function availableIn(string $key): int;

    /**
     * Reset/clear the hit counter for the given key.
     */
    public function reset(string $key): void;
}
