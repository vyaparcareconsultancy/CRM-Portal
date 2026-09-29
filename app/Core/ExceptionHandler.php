<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

class ExceptionHandler
{
    public static function register(): void
    {
        // 1. Error handler (convert PHP notices/warnings into ErrorException when severe; log deprecations as warnings)
        set_error_handler(static function (int $level, string $message, string $file = '', int $line = 0): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            if ($level === E_DEPRECATED || $level === E_USER_DEPRECATED) {
                Logger::warning("PHP Deprecation: {$message}", [
                    'file' => $file,
                    'line' => $line,
                ]);
                return true;
            }
            throw new ErrorException($message, 0, $level, $file, $line);
        });

        // 2. Exception handler
        set_exception_handler(static function (Throwable $e): void {
            self::handle($e);
        });

        // 3. Fatal shutdown handler
        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $e = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                self::handle($e);
            }
        });
    }

    public static function handle(Throwable $e): void
    {
        // Always log full exception details with trace
        Logger::error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Report to Sentry if configured
        \App\Services\SentryService::captureException($e);

        $isDebug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $requestId = Request::requestId();

        if (!headers_sent()) {
            http_response_code(500);
            header('X-Request-Id: ' . $requestId);
        }

        if (self::wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            $message = $isDebug
                ? $e->getMessage()
                : "Something went wrong, please try again (ref: {$requestId})";

            $payload = [
                'status' => 'error',
                'message' => $message,
                'data' => $isDebug ? [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ] : null,
                'errors' => new \stdClass(),
            ];

            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        // Render HTML
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        if ($isDebug) {
            echo self::renderDebugHtml($e);
        } else {
            echo self::renderProductionHtml();
        }
        exit;
    }

    private static function wantsJson(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        return str_starts_with($uri, '/api')
            || str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json');
    }

    private static function renderDebugHtml(Throwable $e): string
    {
        $class = htmlspecialchars(get_class($e));
        $message = htmlspecialchars($e->getMessage());
        $file = htmlspecialchars($e->getFile());
        $line = $e->getLine();
        $trace = htmlspecialchars($e->getTraceAsString());

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Application Error: {$class}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; margin: 0; }
        .container { max-width: 960px; margin: 0 auto; background: #1e293b; border-radius: 8px; padding: 2rem; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        h1 { color: #f43f5e; font-size: 1.5rem; margin-top: 0; }
        .meta { color: #94a3b8; font-size: 0.95rem; margin-bottom: 1.5rem; border-bottom: 1px solid #334155; padding-bottom: 1rem; }
        pre { background: #0b1120; color: #38bdf8; padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$class}: {$message}</h1>
        <div class="meta">in <strong>{$file}</strong> on line <strong>{$line}</strong></div>
        <h3>Stack Trace:</h3>
        <pre>{$trace}</pre>
    </div>
</body>
</html>
HTML;
    }

    private static function renderProductionHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>500 — Server Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { text-align: center; max-width: 480px; padding: 2rem; }
        h1 { font-size: 3rem; margin: 0 0 0.5rem; color: #0284c7; }
        p { color: #64748b; font-size: 1.1rem; line-height: 1.5; }
        a { display: inline-block; margin-top: 1rem; color: #0284c7; text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
    <div class="box">
        <h1>500</h1>
        <h2>Something went wrong</h2>
        <p>An unexpected error occurred on our server. The issue has been logged and our team is notified.</p>
        <a href="/">← Return to Homepage</a>
    </div>
</body>
</html>
HTML;
    }
}
