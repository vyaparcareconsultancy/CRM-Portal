<?php

declare(strict_types=1);

namespace App;

final class Router
{
    /**
     * @var array<string, array<int, array{
     *     path: string,
     *     pattern: string,
     *     handler: callable|array<int, string>|string,
     *     middleware: array<int, callable|string>,
     *     constraints: array<string, string>
     * }>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    public function get(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array|string $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private ?string $lastMethod = null;

    public function addRoute(string $method, string $path, callable|array|string $handler, array $middleware = []): self
    {
        $method = strtoupper($method);
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $this->routes[$method][] = [
            'path' => $path,
            'pattern' => $this->convertToRegex($path),
            'handler' => $handler,
            'middleware' => $middleware,
            'constraints' => [],
        ];

        $this->lastMethod = $method;

        return $this;
    }

    public function middleware(string|array $middleware): self
    {
        if ($this->lastMethod !== null && !empty($this->routes[$this->lastMethod])) {
            $lastIdx = count($this->routes[$this->lastMethod]) - 1;
            $items = is_array($middleware) ? $middleware : [$middleware];
            $this->routes[$this->lastMethod][$lastIdx]['middleware'] = array_merge(
                $this->routes[$this->lastMethod][$lastIdx]['middleware'],
                $items
            );
        }

        return $this;
    }

    /**
     * Set parameter regex constraint(s) on the last registered route.
     *
     * @param string|array<string, string> $name
     * @param string|null $pattern
     */
    public function where(string|array $name, ?string $pattern = null): self
    {
        if ($this->lastMethod !== null && !empty($this->routes[$this->lastMethod])) {
            $lastIdx = count($this->routes[$this->lastMethod]) - 1;
            $constraints = $this->routes[$this->lastMethod][$lastIdx]['constraints'] ?? [];

            if (is_array($name)) {
                $constraints = array_merge($constraints, $name);
            } elseif ($pattern !== null) {
                $constraints[$name] = $pattern;
            }

            $this->routes[$this->lastMethod][$lastIdx]['constraints'] = $constraints;
            $this->routes[$this->lastMethod][$lastIdx]['pattern'] = $this->convertToRegex(
                $this->routes[$this->lastMethod][$lastIdx]['path'],
                $constraints
            );
        }

        return $this;
    }

    /**
     * Match a method and URI against registered routes.
     * Prefers static routes over parameterized routes regardless of registration order.
     *
     * @return array{
     *     route: array{
     *         path: string,
     *         pattern: string,
     *         handler: callable|array<int, string>|string,
     *         middleware: array<int, callable|string>,
     *         constraints: array<string, string>
     *     },
     *     params: array<string, string>
     * }|null
     */
    public function match(string $method, string $uri): ?array
    {
        $httpMethod = strtoupper($method);
        $routesForMethod = $this->getSortedRoutes($httpMethod);

        foreach ($routesForMethod as $route) {
            if (preg_match($route['pattern'], $uri, $matches) === 1) {
                $params = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $params[$key] = $val;
                    }
                }
                return ['route' => $route, 'params' => $params];
            }
        }

        return null;
    }

    public function dispatch(?string $method = null, ?string $uri = null): void
    {
        $httpMethod = $method !== null ? strtoupper($method) : $this->resolveMethod();
        $requestUri = $uri !== null ? $uri : $this->resolveUri();

        $match = $this->match($httpMethod, $requestUri);
        if ($match !== null) {
            if (!$this->executeMiddleware($match['route']['middleware'], $match['params'])) {
                return;
            }

            $this->executeHandler($match['route']['handler'], $match['params']);
            return;
        }

        // Check if URI matches under a different HTTP method (405 Method Not Allowed)
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $httpMethod) {
                continue;
            }
            $sortedOther = $this->getSortedRoutes($otherMethod);
            foreach ($sortedOther as $route) {
                if (preg_match($route['pattern'], $requestUri) === 1) {
                    self::json([
                        'status' => 'error',
                        'message' => 'Method not allowed',
                        'data' => null,
                        'errors' => new \stdClass(),
                    ], 405);
                    return;
                }
            }
        }

        // Route not found (404)
        self::json([
            'status' => 'error',
            'message' => "Route not found: {$httpMethod} {$requestUri}",
            'data' => null,
            'errors' => new \stdClass(),
        ], 404);
    }

    /**
     * @return array<int, array{
     *     path: string,
     *     pattern: string,
     *     handler: callable|array<int, string>|string,
     *     middleware: array<int, callable|string>,
     *     constraints: array<string, string>
     * }>
     */
    private function getSortedRoutes(string $method): array
    {
        $routes = $this->routes[$method] ?? [];
        if (count($routes) <= 1) {
            return $routes;
        }

        // Stable sort: static routes (0 params) first, then fewer params, preserving registration order
        usort($routes, static function (array $a, array $b): int {
            $countA = substr_count($a['path'], '{');
            $countB = substr_count($b['path'], '{');
            return $countA <=> $countB;
        });

        return $routes;
    }

    public static function json(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string, string> $constraints
     */
    private function convertToRegex(string $path, array $constraints = []): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '#^/$#';
        }

        // Converts {param} to named capture group (?P<param>pattern)
        $pattern = preg_replace_callback('#\{([a-zA-Z0-9_]+)\}#', static function (array $matches) use ($constraints): string {
            $param = $matches[1];
            if (isset($constraints[$param])) {
                return '(?P<' . $param . '>' . $constraints[$param] . ')';
            }

            // Default constraint: {id}, {docId} or any parameter ending with 'id' or 'Id' matches digits only
            if (strtolower($param) === 'id' || str_ends_with(strtolower($param), 'id')) {
                return '(?P<' . $param . '>[0-9]+)';
            }

            return '(?P<' . $param . '>[^/]+)';
        }, $path);

        return '#^' . $pattern . '$#';
    }

    private function resolveUri(): string
    {
        $rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = rawurldecode($rawUri);

        // Strip script base path for subfolder deployments (e.g. XAMPP htdocs/CRM/public)
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = str_replace('\\', '/', dirname($scriptName));

        if ($scriptDir !== '/' && $scriptDir !== '.' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        } else {
            $parentDir = str_replace('\\', '/', dirname($scriptDir));
            if ($parentDir !== '/' && $parentDir !== '.' && str_starts_with($uri, $parentDir)) {
                $uri = substr($uri, strlen($parentDir));
            }
        }

        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    private function resolveMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            if (isset($_POST['_method'])) {
                $method = strtoupper((string) $_POST['_method']);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $method = strtoupper((string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        return $method;
    }

    /**
     * @param array<int, callable|string> $middlewares
     * @param array<string, string> $params
     */
    private function executeMiddleware(array $middlewares, array $params): bool
    {
        foreach ($middlewares as $middleware) {
            $result = true;
            $mwClass = $middleware;
            $arg = null;

            if (is_string($middleware) && str_starts_with($middleware, 'perm:')) {
                $mwClass = \App\Middleware\PermissionMiddleware::class;
                $arg = substr($middleware, 5);
            } elseif (is_string($middleware) && str_starts_with($middleware, 'throttle:')) {
                $mwClass = \App\Middleware\RateLimitMiddleware::class;
                $arg = substr($middleware, 9);
            }

            if (is_callable($middleware)) {
                $result = $middleware($params);
            } elseif (is_string($mwClass) && class_exists($mwClass)) {
                $instance = new $mwClass();
                if (method_exists($instance, 'handle')) {
                    $result = $arg !== null ? $instance->handle($params, $arg) : $instance->handle($params);
                } elseif (is_callable($instance)) {
                    $result = $instance($params);
                }
            }

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param callable|array<int, string>|string $handler
     * @param array<string, string> $params
     */
    private function executeHandler(callable|array|string $handler, array $params): void
    {
        $response = null;

        if (is_callable($handler)) {
            $response = $handler($params);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = is_object($class) ? $class : new $class();
            $response = $instance->$method($params);
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $instance = new $class();
            $response = $instance->$method($params);
        }

        if (is_array($response)) {
            self::json($response);
        } elseif (is_string($response)) {
            echo $response;
        }
    }
}
