<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

class Logger
{
    /** @var array<string, mixed> */
    private static array $loggers = [];

    /**
     * Get or create a Monolog logger instance for a given channel.
     * Channels: 'app', 'error', 'security', 'audit'
     */
    public static function channel(string $channel = 'app'): mixed
    {
        $channel = strtolower($channel);
        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        }

        $logsDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }

        if (class_exists(\Monolog\Logger::class) && class_exists(\Monolog\Handler\RotatingFileHandler::class)) {
            $logger = new \Monolog\Logger($channel);

            // Output structured single-line JSON format
            $formatter = new \Monolog\Formatter\JsonFormatter();

            // Set appropriate default log level
            $minLevel = match ($channel) {
                'error' => class_exists(\Monolog\Level::class) ? \Monolog\Level::Error : 400,
                'security' => class_exists(\Monolog\Level::class) ? \Monolog\Level::Notice : 250,
                'audit' => class_exists(\Monolog\Level::class) ? \Monolog\Level::Info : 200,
                default => class_exists(\Monolog\Level::class) ? \Monolog\Level::Debug : 100,
            };

            // RotatingFileHandler with daily rotation and 30-day retention
            $handler = new \Monolog\Handler\RotatingFileHandler(
                $logsDir . '/' . $channel . '.log',
                30, // Keep 30 days
                $minLevel
            );
            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            // Context processor: automatically inject request_id, user_id, ip, url, method
            $logger->pushProcessor(static function (\Monolog\LogRecord $record): \Monolog\LogRecord {
                $contextExtra = [
                    'request_id' => Request::requestId(),
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'ip' => self::resolveClientIp(),
                    'url' => $_SERVER['REQUEST_URI'] ?? null,
                    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                ];

                return $record->with(extra: array_merge($record->extra, $contextExtra));
            });

            self::$loggers[$channel] = $logger;
            return self::$loggers[$channel];
        }

        return null;
    }

    public static function app(string $message, array $context = [], string $level = 'info'): void
    {
        self::logToChannel('app', $level, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::logToChannel('error', 'error', $message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        self::logToChannel('security', 'notice', $message, $context);
    }

    public static function audit(string $message, array $context = []): void
    {
        self::logToChannel('audit', 'info', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::logToChannel('app', 'debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::logToChannel('app', 'info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::logToChannel('app', 'warning', $message, $context);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $channel = in_array(strtolower($level), ['error', 'critical', 'alert', 'emergency'], true)
            ? 'error'
            : 'app';

        self::logToChannel($channel, $level, $message, $context);
    }

    /**
     * Dispatch log record to the specified channel with sensitive context sanitization.
     */
    private static function logToChannel(string $channel, string $level, string $message, array $context = []): void
    {
        $sanitized = self::sanitizeContext($context);
        $logger = self::channel($channel);

        if ($logger !== null) {
            $levelMethod = strtolower($level);
            if (method_exists($logger, $levelMethod)) {
                $logger->$levelMethod($message, $sanitized);
                return;
            }
            if (method_exists($logger, 'log')) {
                $logger->log($level, $message, $sanitized);
                return;
            }
        }

        // Native fallback handler with rotation and 30-day retention
        self::nativeFallbackLog($channel, $level, $message, $sanitized);
    }

    /**
     * Recursively strip sensitive credentials, tokens, and PII from log context.
     */
    public static function sanitizeContext(array $context): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'new_password',
            'token',
            'token_hash',
            'secret',
            'password_hash',
            'pan_no',
            'pan',
            'credit_card',
            'cvv',
            'authorization',
            'bearer',
            'api_key',
        ];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $context[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $context[$key] = self::sanitizeContext($value);
            }
        }
        return $context;
    }

    /**
     * Standalone native JSON rotating logger (no Composer Monolog required).
     */
    private static function nativeFallbackLog(string $channel, string $level, string $message, array $context = []): void
    {
        $logsDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }

        $date = date('Y-m-d');
        $record = [
            'datetime' => date('c'),
            'channel' => $channel,
            'level' => strtoupper($level),
            'message' => $message,
            'request_id' => Request::requestId(),
            'context' => $context,
            'extra' => [
                'request_id' => Request::requestId(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip' => self::resolveClientIp(),
                'url' => $_SERVER['REQUEST_URI'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            ],
        ];

        $jsonLine = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $targetFile = "{$logsDir}/{$channel}-{$date}.log";
        @file_put_contents($targetFile, $jsonLine, FILE_APPEND | LOCK_EX);

        // Prune log files older than 30 days
        self::pruneOldLogs($logsDir, $channel, 30);
    }

    /**
     * Clean up files older than $retentionDays.
     */
    public static function pruneOldLogs(string $logsDir, string $channel, int $retentionDays = 30): void
    {
        $files = glob("{$logsDir}/{$channel}-*.log") ?: [];
        $cutoff = time() - ($retentionDays * 86400);

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    public static function resolveClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', (string)$_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }
}
