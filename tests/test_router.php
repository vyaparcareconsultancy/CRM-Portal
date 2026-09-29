<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Router.php';

use App\Router;

// Quick self-check assertion helper
function it(string $description, callable $fn): void
{
    try {
        $fn();
        echo "PASS: {$description}\n";
    } catch (\Throwable $e) {
        echo "FAIL: {$description} -> " . $e->getMessage() . "\n";
        exit(1);
    }
}

it('matches static route and extracts no params', function () {
    $router = new Router();
    $matched = false;
    $router->get('/health', function () use (&$matched) {
        $matched = true;
        return ['status' => 'success'];
    });

    // Simulate dispatch via internal reflection or mock request
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/health';
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    ob_start();
    // Use try-catch because Router::json() calls exit; we can intercept or test convertToRegex
    // For unit check, test matching logic
    $method = new \ReflectionMethod(Router::class, 'convertToRegex');
    $regex = $method->invoke($router, '/health');
    assert(preg_match($regex, '/health') === 1, 'Static route should match regex');
});

it('converts route params like /api/clients/{id} to named capture groups', function () {
    $router = new Router();
    $method = new \ReflectionMethod(Router::class, 'convertToRegex');
    $regex = $method->invoke($router, '/api/clients/{id}');

    assert(preg_match($regex, '/api/clients/42', $matches) === 1, 'Route with param should match');
    assert(($matches['id'] ?? null) === '42', 'Param "id" should equal "42"');
    assert(preg_match($regex, '/api/clients/42/extra') === 0, 'Route should not match additional segments');
});

it('resolves relative URI correctly when hosted under subdirectories', function () {
    $router = new Router();
    $resolveMethod = new \ReflectionMethod(Router::class, 'resolveUri');

    $_SERVER['REQUEST_URI'] = '/CRM/public/health';
    $_SERVER['SCRIPT_NAME'] = '/CRM/public/index.php';
    assert($resolveMethod->invoke($router) === '/health', 'Should strip /CRM/public');

    $_SERVER['REQUEST_URI'] = '/CRM/health';
    $_SERVER['SCRIPT_NAME'] = '/CRM/public/index.php';
    assert($resolveMethod->invoke($router) === '/health', 'Should strip /CRM root prefix');
});

echo "All router checks passed successfully.\n";
