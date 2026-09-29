<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private const SESSION_NAME = 'CRM_SESSION';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::checkTimeout();
            return;
        }

        $lifetime = ((int)($_ENV['SESSION_LIFETIME'] ?? 120)) * 60;
        $isProduction = (strtolower((string)($_ENV['APP_ENV'] ?? 'local')) === 'production');
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name(self::SESSION_NAME);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps || $isProduction,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.gc_maxlifetime', (string)$lifetime);
        ini_set('session.use_strict_mode', '1');

        // Configurable session handler: files (default), database, redis
        $driver = strtolower((string)($_ENV['SESSION_DRIVER'] ?? 'files'));
        if ($driver === 'database') {
            $handler = new DatabaseSessionHandler();
            session_set_save_handler($handler, true);
        } elseif ($driver === 'redis') {
            $redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $redisPort = $_ENV['REDIS_PORT'] ?? '6379';
            $redisPass = $_ENV['REDIS_PASSWORD'] ?? '';
            $authPart = ($redisPass !== '' && $redisPass !== 'null') ? "?auth={$redisPass}" : '';
            ini_set('session.save_handler', 'redis');
            ini_set('session.save_path', "tcp://{$redisHost}:{$redisPort}{$authPart}");
        }
        // else: default PHP file-based sessions

        if (!headers_sent()) {
            session_start();
        }

        self::checkTimeout();
    }

    private static function checkTimeout(): void
    {
        $timeoutSeconds = ((int)($_ENV['SESSION_LIFETIME'] ?? 120)) * 60;
        $now = time();

        if (isset($_SESSION['_last_activity'])) {
            if ($now - (int)$_SESSION['_last_activity'] > $timeoutSeconds) {
                self::destroy();
                return;
            }
        }

        $_SESSION['_last_activity'] = $now;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }
}
