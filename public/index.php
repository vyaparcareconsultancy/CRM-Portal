<?php

declare(strict_types=1);

// 1. Register Composer or fallback PSR-4 autoloader
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (str_starts_with($class, $prefix)) {
            $relativeClass = substr($class, strlen($prefix));
            $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    });
}

// 2. Load environment variables
$envPath = dirname(__DIR__);
if (class_exists(\Dotenv\Dotenv::class) && file_exists($envPath . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->safeLoad();
} elseif (file_exists($envPath . '/.env')) {
    $lines = file($envPath . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// 3. Configure error reporting and register global exception handler
$appDebug = filter_var($_ENV['APP_DEBUG'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
if ($appDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// Security hardening: hide PHP version from response headers
ini_set('expose_php', '0');
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

\App\Core\ExceptionHandler::register();

// Initialize error tracking if configured
\App\Services\SentryService::init();

// Set unique correlation ID header
if (!headers_sent()) {
    header('X-Request-Id: ' . \App\Core\Request::requestId());
}

// 4. Apply security headers (CSP, X-Frame-Options, X-Content-Type-Options, etc.)
\App\Middleware\SecurityHeadersMiddleware::apply();

// 5. Initialize secure session
\App\Core\Session::start();

// 5. Initialize Router & load routes
$router = new \App\Router();
$routesFile = dirname(__DIR__) . '/config/routes.php';
if (file_exists($routesFile)) {
    require_once $routesFile;
}

// 5. Dispatch request
try {
    $router->dispatch();
} catch (\Throwable $e) {
    \App\Core\ExceptionHandler::handle($e);
}
