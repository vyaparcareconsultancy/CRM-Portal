<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Cache\Cache;
use App\Services\Cache\FileCache;
use App\Services\Cache\RedisCache;
use App\Services\ClientService;
use App\Services\DashboardService;
use App\Core\View;

echo "--- Testing Caching & Performance Subsystems ---\n";

$tempDir = __DIR__ . '/temp_cache_' . bin2hex(random_bytes(4));
$fileCache = new FileCache($tempDir);

// 1. Basic Set & Get
$fileCache->set('test_key', ['foo' => 'bar', 'count' => 42], 60);
assert($fileCache->has('test_key') === true, 'Cache has() should return true for valid key');
$data = $fileCache->get('test_key');
assert(is_array($data) && $data['foo'] === 'bar' && $data['count'] === 42, 'Cache get() should return stored data');
echo "✔ FileCache basic set, get, has passed\n";

// 2. Expiration (TTL = 1s)
$fileCache->set('quick_expire', 'ephemeral', 1);
assert($fileCache->get('quick_expire') === 'ephemeral', 'Key should be available immediately');
sleep(2);
assert($fileCache->get('quick_expire', 'default_val') === 'default_val', 'Expired key should return default');
assert($fileCache->has('quick_expire') === false, 'Expired key has() should return false');
echo "✔ FileCache expiration passed\n";

// 3. Delete
$fileCache->set('delete_me', 123, 60);
assert($fileCache->delete('delete_me') === true, 'delete() should return true');
assert($fileCache->get('delete_me') === null, 'Deleted key should return null');
echo "✔ FileCache delete passed\n";

// 4. Cache Facade & remember()
Cache::setStore($fileCache);
$calls = 0;
$result1 = Cache::remember('remember_test', 60, function () use (&$calls) {
    $calls++;
    return 'computed_value';
});
$result2 = Cache::remember('remember_test', 60, function () use (&$calls) {
    $calls++;
    return 'second_call';
});
assert($calls === 1, 'Callback should only be called once when cached');
assert($result1 === 'computed_value' && $result2 === 'computed_value', 'Cached value returned on 2nd call');
echo "✔ Cache::remember() passed\n";

// 5. Prefix Invalidation (forgetByPrefix)
Cache::set('dashboard:stats:user_1', ['total' => 10], 60);
Cache::set('dashboard:stats:user_2', ['total' => 20], 60);
Cache::set('dashboard:stats:view_all', ['total' => 30], 60);
Cache::set('crm:lookups:static', ['states' => ['Goa']], 86400);

assert(Cache::has('dashboard:stats:user_1') === true, 'Dashboard user 1 exists');
assert(Cache::has('crm:lookups:static') === true, 'Lookups exist');

Cache::forgetByPrefix('dashboard:stats');

assert(Cache::has('dashboard:stats:user_1') === false, 'Dashboard user 1 invalidated');
assert(Cache::has('dashboard:stats:user_2') === false, 'Dashboard user 2 invalidated');
assert(Cache::has('dashboard:stats:view_all') === false, 'Dashboard view_all invalidated');
assert(Cache::has('crm:lookups:static') === true, 'Lookups still cached after dashboard invalidation');
echo "✔ Cache::forgetByPrefix() namespace invalidation passed\n";

// 6. View asset versioning helper
$cssUrl = View::asset('/assets/css/app.css');
assert(str_starts_with($cssUrl, '/assets/css/app.css?v='), 'asset() should append ?v=hash query parameter');
$missingUrl = View::asset('/assets/nonexistent.css');
assert($missingUrl === '/assets/nonexistent.css', 'asset() on non-existent file returns clean path');
echo "✔ View asset versioning (?v=hash) passed\n";

// Cleanup temp directory
$fileCache->clear();
@rmdir($tempDir);
Cache::setStore(null);

echo "✔ All Caching & Performance tests completed successfully!\n";
