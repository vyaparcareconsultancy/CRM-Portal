<?php

declare(strict_types=1);

namespace App\Services\Cache;

/**
 * Cache manager and static facade.
 * Reads CACHE_DRIVER from .env ('file' default, 'redis' optional).
 */
class Cache
{
    private static ?CacheInterface $store = null;

    public static function store(?CacheInterface $customStore = null): CacheInterface
    {
        if ($customStore !== null) {
            self::$store = $customStore;
            return self::$store;
        }

        if (self::$store === null) {
            $driver = strtolower((string)($_ENV['CACHE_DRIVER'] ?? 'file'));

            if ($driver === 'redis') {
                self::$store = new RedisCache();
            } else {
                self::$store = new FileCache();
            }
        }

        return self::$store;
    }

    public static function setStore(?CacheInterface $store): void
    {
        self::$store = $store;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::store()->get($key, $default);
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return self::store()->set($key, $value, $ttl);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function delete(string $key): bool
    {
        return self::store()->delete($key);
    }

    public static function has(string $key): bool
    {
        return self::store()->has($key);
    }

    public static function forgetByPrefix(string $prefix): bool
    {
        return self::store()->forgetByPrefix($prefix);
    }

    public static function clear(): bool
    {
        return self::store()->clear();
    }
}
