<?php

declare(strict_types=1);

namespace Tests;

use App\Core\View;
use App\Services\Cache\Cache;
use App\Services\Cache\FileCache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private string $tempDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/crm_cache_test_' . bin2hex(random_bytes(4));
        $this->cache = new FileCache($this->tempDir);
        Cache::setStore($this->cache);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        @rmdir($this->tempDir);
        Cache::setStore(null);
    }

    public function testSetGetAndHas(): void
    {
        $this->assertFalse($this->cache->has('non_existent'));
        $this->assertNull($this->cache->get('non_existent'));

        $this->assertTrue($this->cache->set('sample', ['a' => 1, 'b' => 2], 60));
        $this->assertTrue($this->cache->has('sample'));
        $this->assertSame(['a' => 1, 'b' => 2], $this->cache->get('sample'));
    }

    public function testDelete(): void
    {
        $this->cache->set('del', 'value', 60);
        $this->assertTrue($this->cache->delete('del'));
        $this->assertNull($this->cache->get('del'));
        $this->assertFalse($this->cache->has('del'));
    }

    public function testRemember(): void
    {
        $calls = 0;
        $val1 = Cache::remember('rem', 60, function () use (&$calls) {
            $calls++;
            return 'result';
        });

        $val2 = Cache::remember('rem', 60, function () use (&$calls) {
            $calls++;
            return 'second';
        });

        $this->assertSame(1, $calls);
        $this->assertSame('result', $val1);
        $this->assertSame('result', $val2);
    }

    public function testForgetByPrefix(): void
    {
        Cache::set('dashboard:stats:view_all', 'all_data', 60);
        Cache::set('dashboard:stats:user_1', 'user_data', 60);
        Cache::set('crm:lookups:static', 'lookups', 86400);

        Cache::forgetByPrefix('dashboard:stats');

        $this->assertFalse(Cache::has('dashboard:stats:view_all'));
        $this->assertFalse(Cache::has('dashboard:stats:user_1'));
        $this->assertTrue(Cache::has('crm:lookups:static'));
    }

    public function testAssetVersioning(): void
    {
        $versioned = View::asset('/assets/css/app.css');
        $this->assertStringContainsString('?v=', $versioned);
    }
}
