<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Request;
use Throwable;

/**
 * Service for Sentry error tracking integration.
 * Activated only when SENTRY_DSN is populated in .env.
 */
class SentryService
{
    private static bool $initialized = false;

    public static function isEnabled(): bool
    {
        return !empty($_ENV['SENTRY_DSN']);
    }

    public static function getDsn(): ?string
    {
        return !empty($_ENV['SENTRY_DSN']) ? (string)$_ENV['SENTRY_DSN'] : null;
    }

    /**
     * Initialize PHP Sentry SDK if DSN is set.
     */
    public static function init(): void
    {
        if (self::$initialized || !self::isEnabled()) {
            return;
        }

        self::$initialized = true;
        $dsn = (string)$_ENV['SENTRY_DSN'];
        $env = (string)($_ENV['APP_ENV'] ?? 'production');

        // Check if official sentry/sentry package is installed
        if (function_exists('\Sentry\init')) {
            \Sentry\init([
                'dsn' => $dsn,
                'environment' => $env,
                'send_default_pii' => false,
                'before_send' => static function (mixed $event): mixed {
                    return self::scrubOfficialEvent($event);
                },
            ]);

            if (function_exists('\Sentry\configureScope')) {
                \Sentry\configureScope(static function (mixed $scope): void {
                    $scope->setTag('request_id', Request::requestId());
                    if (!empty($_SESSION['user_id'])) {
                        $scope->setUser(['id' => (string)$_SESSION['user_id']]);
                    }
                });
            }
        }
    }

    /**
     * Report an unhandled exception to Sentry.
     */
    public static function captureException(Throwable $e): void
    {
        if (!self::isEnabled()) {
            return;
        }

        // Official Sentry function
        if (function_exists('\Sentry\captureException')) {
            \Sentry\captureException($e);
            return;
        }

        // Native fallback: Post exception directly to Sentry HTTP store API
        self::sendNativeSentryStore($e);
    }

    /**
     * Native fallback HTTP sender to Sentry API if composer package is not installed.
     */
    private static function sendNativeSentryStore(Throwable $e): void
    {
        try {
            $dsn = (string)$_ENV['SENTRY_DSN'];
            $parsed = parse_url($dsn);
            if (!$parsed || !isset($parsed['scheme'], $parsed['host'], $parsed['user'], $parsed['path'])) {
                return;
            }

            $projectId = trim($parsed['path'], '/');
            $publicKey = $parsed['user'];
            $host = $parsed['host'];
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $endpoint = "{$parsed['scheme']}://{$host}{$port}/api/{$projectId}/store/";

            $event = [
                'event_id' => bin2hex(random_bytes(16)),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'platform' => 'php',
                'level' => 'error',
                'logger' => 'crm.error',
                'environment' => $_ENV['APP_ENV'] ?? 'production',
                'tags' => [
                    'request_id' => Request::requestId(),
                ],
                'extra' => [
                    'ip' => Logger::resolveClientIp(),
                    'url' => $_SERVER['REQUEST_URI'] ?? null,
                    'user_id' => $_SESSION['user_id'] ?? null,
                ],
                'exception' => [
                    'values' => [
                        [
                            'type' => get_class($e),
                            'value' => self::scrubText($e->getMessage()),
                            'stacktrace' => [
                                'frames' => array_slice(array_map(static fn($f) => [
                                    'filename' => $f['file'] ?? '[internal]',
                                    'lineno' => $f['line'] ?? 0,
                                    'function' => $f['function'],
                                ], $e->getTrace()), 0, 15),
                            ],
                        ],
                    ],
                ],
            ];

            $payload = json_encode($event, JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return;
            }

            $headers = [
                'Content-Type: application/json',
                "X-Sentry-Auth: Sentry sentry_version=7, sentry_client=crm-portal/1.0, sentry_key={$publicKey}",
            ];

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $payload,
                    'timeout' => 2.0, // Strict timeout to prevent blocking user requests
                    'ignore_errors' => true,
                ],
            ]);

            @file_get_contents($endpoint, false, $context);
        } catch (Throwable) {
            // Silently ignore reporting errors to prevent cascading failure
        }
    }

    /**
     * Recursively strip sensitive fields (password, token, pan_no) from data arrays.
     */
    public static function scrubData(array $data): array
    {
        return Logger::sanitizeContext($data);
    }

    /**
     * Scrub sensitive text such as PAN numbers and bearer tokens from raw strings.
     */
    public static function scrubText(string $text): string
    {
        // Redact Indian PAN patterns (e.g. ABCDE1234F)
        $text = preg_replace('/\b[A-Z]{5}[0-9]{4}[A-Z]\b/i', '[REDACTED_PAN]', $text) ?? $text;

        // Redact Bearer tokens and hashes
        $text = preg_replace('/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i', 'Bearer [REDACTED_TOKEN]', $text) ?? $text;

        return $text;
    }

    private static function scrubOfficialEvent(mixed $event): mixed
    {
        if (is_object($event) && method_exists($event, 'getRequest') && method_exists($event, 'setRequest')) {
            $req = $event->getRequest();
            if (is_array($req) && isset($req['data'])) {
                $req['data'] = self::scrubData((array)$req['data']);
                $event->setRequest($req);
            }
        }
        return $event;
    }

    /**
     * Generate HTML script snippet for Sentry Browser SDK with client-side scrubbing.
     */
    public static function renderBrowserScript(): string
    {
        if (!self::isEnabled()) {
            return '';
        }

        $dsn = htmlspecialchars(self::getDsn() ?? '', ENT_QUOTES, 'UTF-8');
        $env = htmlspecialchars((string)($_ENV['APP_ENV'] ?? 'production'), ENT_QUOTES, 'UTF-8');
        $requestId = htmlspecialchars(Request::requestId(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
    <!-- Sentry Browser SDK -->
    <script src="https://browser.sentry-cdn.com/7.100.0/bundle.min.js" crossorigin="anonymous"></script>
    <script>
        if (typeof Sentry !== 'undefined') {
            Sentry.init({
                dsn: "{$dsn}",
                environment: "{$env}",
                sendDefaultPii: false,
                beforeSend: function(event) {
                    // Scrub sensitive fields from client-side errors and network breadcrumbs
                    var sensitive = ['password', 'token', 'pan_no', 'pan', 'secret', 'credit_card'];
                    if (event.request && event.request.data && typeof event.request.data === 'object') {
                        sensitive.forEach(function(key) {
                            if (event.request.data[key]) event.request.data[key] = '[REDACTED]';
                        });
                    }
                    if (event.breadcrumbs && Array.isArray(event.breadcrumbs)) {
                        event.breadcrumbs.forEach(function(b) {
                            if (b.data && typeof b.data === 'object') {
                                sensitive.forEach(function(k) {
                                    if (b.data[k]) b.data[k] = '[REDACTED]';
                                });
                            }
                        });
                    }
                    return event;
                }
            });
            Sentry.setTag("request_id", "{$requestId}");
        }
    </script>
HTML;
    }
}
