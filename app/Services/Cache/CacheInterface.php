<?php

declare(strict_types=1);

namespace App\Services\Cache;

interface CacheInterface
{
    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache with a time-to-live in seconds.
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool;

    /**
     * Remove an item from the cache.
     */
    public function delete(string $key): bool;

    /**
     * Determine if an item exists in the cache and has not expired.
     */
    public function has(string $key): bool;

    /**
     * Invalidate all cache items matching a given key prefix.
     */
    public function forgetByPrefix(string $prefix): bool;

    /**
     * Invalidate all cached items.
     */
    public function clear(): bool;
}
