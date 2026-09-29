<?php

declare(strict_types=1);

namespace Tests;

use App\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testStaticRoutePreferredOverParameterizedRouteRegardlessOfRegistrationOrder(): void
    {
        $router = new Router();

        // Register parameterized route FIRST
        $router->get('/clients/{id}', static fn() => 'parameterized_show');
        // Register static route SECOND
        $router->get('/clients/create', static fn() => 'static_create');

        // /clients/create MUST match static_create
        $match = $router->match('GET', '/clients/create');
        $this->assertNotNull($match);
        $this->assertSame('/clients/create', $match['route']['path']);
        $this->assertEmpty($match['params']);
        $this->assertSame('static_create', ($match['route']['handler'])());

        // /clients/123 MUST match parameterized_show with numeric id
        $matchId = $router->match('GET', '/clients/123');
        $this->assertNotNull($matchId);
        $this->assertSame('/clients/{id}', $matchId['route']['path']);
        $this->assertSame('123', $matchId['params']['id']);
        $this->assertSame('parameterized_show', ($matchId['route']['handler'])());
    }

    public function testIdAndDocIdParametersMatchDigitsOnlyByDefault(): void
    {
        $router = new Router();
        $router->get('/clients/{id}', static fn() => 'client');
        $router->get('/clients/{id}/documents/{docId}', static fn() => 'document');

        // Numeric IDs match
        $match = $router->match('GET', '/clients/42');
        $this->assertNotNull($match);
        $this->assertSame('42', $match['params']['id']);

        $matchDoc = $router->match('GET', '/clients/42/documents/105');
        $this->assertNotNull($matchDoc);
        $this->assertSame('42', $matchDoc['params']['id']);
        $this->assertSame('105', $matchDoc['params']['docId']);

        // Non-numeric IDs must NOT match
        $this->assertNull($router->match('GET', '/clients/abc'));
        $this->assertNull($router->match('GET', '/clients/12a'));
        $this->assertNull($router->match('GET', '/clients/create'));
        $this->assertNull($router->match('GET', '/clients/42/documents/not-a-number'));
    }

    public function testPotentialRouteCollisionsResolveCorrectly(): void
    {
        $router = new Router();

        // 1. /api/clients/{id} vs /api/clients/export
        $router->get('/api/clients/{id}', static fn() => 'show');
        $router->get('/api/clients/export', static fn() => 'export');

        $exportMatch = $router->match('GET', '/api/clients/export');
        $this->assertNotNull($exportMatch);
        $this->assertSame('/api/clients/export', $exportMatch['route']['path']);

        $showMatch = $router->match('GET', '/api/clients/500');
        $this->assertNotNull($showMatch);
        $this->assertSame('/api/clients/{id}', $showMatch['route']['path']);
        $this->assertSame('500', $showMatch['params']['id']);

        // 2. /followups/{id} vs /followups/today
        $router->get('/followups/{id}', static fn() => 'followup_show');
        $router->get('/followups/today', static fn() => 'followup_today');

        $todayMatch = $router->match('GET', '/followups/today');
        $this->assertNotNull($todayMatch);
        $this->assertSame('/followups/today', $todayMatch['route']['path']);

        $fuMatch = $router->match('GET', '/followups/7');
        $this->assertNotNull($fuMatch);
        $this->assertSame('/followups/{id}', $fuMatch['route']['path']);
        $this->assertSame('7', $fuMatch['params']['id']);

        // 3. /users/{id} vs /users/create
        $router->get('/users/{id}', static fn() => 'user_show');
        $router->get('/users/create', static fn() => 'user_create');

        $userCreateMatch = $router->match('GET', '/users/create');
        $this->assertNotNull($userCreateMatch);
        $this->assertSame('/users/create', $userCreateMatch['route']['path']);

        $userShowMatch = $router->match('GET', '/users/99');
        $this->assertNotNull($userShowMatch);
        $this->assertSame('/users/{id}', $userShowMatch['route']['path']);
        $this->assertSame('99', $userShowMatch['params']['id']);
    }

    public function testCustomWhereConstraintSupport(): void
    {
        $router = new Router();

        // Route with single where constraint
        $router->get('/posts/{slug}', static fn() => 'post')
            ->where('slug', '[a-z0-9\-]+');

        $matchValid = $router->match('GET', '/posts/hello-world-2026');
        $this->assertNotNull($matchValid);
        $this->assertSame('hello-world-2026', $matchValid['params']['slug']);

        $matchInvalid = $router->match('GET', '/posts/INVALID_SLUG!');
        $this->assertNull($matchInvalid);

        // Route with array where constraints
        $router->get('/archive/{year}/{month}', static fn() => 'archive')
            ->where([
                'year' => '[0-9]{4}',
                'month' => '[0-9]{2}',
            ]);

        $matchArchive = $router->match('GET', '/archive/2026/09');
        $this->assertNotNull($matchArchive);
        $this->assertSame('2026', $matchArchive['params']['year']);
        $this->assertSame('09', $matchArchive['params']['month']);

        $this->assertNull($router->match('GET', '/archive/26/9'));
    }

    public function testUnmatchedRouteReturnsNull(): void
    {
        $router = new Router();
        $router->get('/health', static fn() => 'ok');

        $this->assertNull($router->match('GET', '/non-existent'));
        $this->assertNull($router->match('POST', '/health'));
    }
}
