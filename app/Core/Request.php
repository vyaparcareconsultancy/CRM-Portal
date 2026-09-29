<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $post;
    private ?array $json = null;
    private array $files;
    private array $server;
    private ?array $headers = null;

    public function __construct(
        ?array $query = null,
        ?array $post = null,
        ?array $files = null,
        ?array $server = null
    ) {
        $this->query = $query ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->server = $server ?? $_SERVER;

        $this->method = $this->resolveMethod();
        $this->path = $this->resolvePath();
    }

    private static ?string $currentRequestId = null;

    public static function createFromGlobals(): self
    {
        return new self();
    }

    /**
     * Get or generate the unique correlation request ID for this execution.
     */
    public static function requestId(): string
    {
        if (self::$currentRequestId === null) {
            $inbound = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
            if (is_string($inbound) && preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $inbound)) {
                self::$currentRequestId = $inbound;
            } else {
                self::$currentRequestId = 'req_' . bin2hex(random_bytes(12));
            }
        }
        return self::$currentRequestId;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                $this->json = is_array($decoded) ? $decoded : [];
            } else {
                $this->json = [];
            }
        }

        if ($key === null) {
            return $this->json;
        }

        return $this->json[$key] ?? $default;
    }

    public function body(?string $key = null, mixed $default = null): mixed
    {
        $body = array_merge($this->post, $this->json());

        if ($key === null) {
            return $body;
        }

        return $body[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body($key) ?? $this->query($key, $default);
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body());
    }

    public function files(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->files;
        }

        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        $file = $this->files($key);
        return is_array($file)
            && isset($file['error'])
            && $file['error'] !== UPLOAD_ERR_NO_FILE
            && ($file['size'] ?? 0) > 0;
    }

    public function ip(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($this->server[$header])) {
                $ips = explode(',', (string)$this->server[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    public function userAgent(): string
    {
        $ua = (string)($this->server['HTTP_USER_AGENT'] ?? '');
        return substr(trim($ua), 0, 255);
    }

    public function headers(?string $key = null, mixed $default = null): mixed
    {
        if ($this->headers === null) {
            $this->headers = [];
            foreach ($this->server as $k => $v) {
                if (str_starts_with($k, 'HTTP_')) {
                    $headerName = strtolower(str_replace('_', '-', substr($k, 5)));
                    $this->headers[$headerName] = $v;
                } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                    $headerName = strtolower(str_replace('_', '-', $k));
                    $this->headers[$headerName] = $v;
                }
            }
        }

        if ($key === null) {
            return $this->headers;
        }

        return $this->headers[strtolower($key)] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers($key, $default);
    }

    public function isJson(): bool
    {
        $contentType = (string)$this->header('content-type', '');
        return str_contains($contentType, 'application/json');
    }

    public function isAjax(): bool
    {
        return strtolower((string)$this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    private function resolveMethod(): string
    {
        $method = strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'POST') {
            if (isset($this->post['_method'])) {
                return strtoupper((string)$this->post['_method']);
            }
            if (isset($this->server['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                return strtoupper((string)$this->server['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        return $method;
    }

    private function resolvePath(): string
    {
        $rawUri = parse_url((string)($this->server['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
        $uri = rawurldecode($rawUri);

        // Normalize subfolder paths (e.g. /CRM/public/api -> /api)
        $scriptName = str_replace('\\', '/', (string)($this->server['SCRIPT_NAME'] ?? ''));
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
}
