<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(array $payload, int $statusCode = 200, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): void
    {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'errors' => new \stdClass(),
        ], $statusCode);
    }

    public static function error(
        string $message = 'Error',
        int $statusCode = 400,
        mixed $data = null,
        array|object|null $errors = null
    ): void {
        if ($statusCode >= 500) {
            $requestId = Request::requestId();
            $isDebug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

            Logger::error("API 500 Server Error: {$message}", [
                'request_id' => $requestId,
                'status_code' => $statusCode,
                'data' => $data,
            ]);

            if (!$isDebug) {
                $message = "Something went wrong, please try again (ref: {$requestId})";
                $data = null;
            }
        }

        self::json([
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'errors' => $errors ?? new \stdClass(),
        ], $statusCode);
    }

    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::json([
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'errors' => empty($errors) ? new \stdClass() : $errors,
        ], 422);
    }

    public static function view(string $template, array $data = [], ?string $layout = 'app'): void
    {
        $html = View::render($template, $data, $layout);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo $html;
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header("Location: {$url}");
        }
        exit;
    }
}
