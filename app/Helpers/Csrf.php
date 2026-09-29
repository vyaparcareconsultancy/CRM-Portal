<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Request;

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function initSession(): void
    {
        \App\Core\Session::start();
    }

    public static function generateToken(): string
    {
        self::initSession();

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    public static function getToken(): string
    {
        self::initSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            return self::generateToken();
        }

        return (string)$_SESSION[self::SESSION_KEY];
    }

    public static function verifyToken(?string $token): bool
    {
        self::initSession();

        if ($token === null || $token === '' || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals((string)$_SESSION[self::SESSION_KEY], $token);
    }

    public static function validateRequest(?Request $request = null): bool
    {
        self::initSession();

        $token = null;
        if ($request !== null) {
            $token = $request->input('_csrf_token')
                ?? $request->header('x-csrf-token')
                ?? $request->header('x-xsrf-token');
        } else {
            $token = $_POST['_csrf_token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $_SERVER['HTTP_X_XSRF_TOKEN']
                ?? null;
        }

        $isValid = self::verifyToken((string)$token);
        if (!$isValid) {
            \App\Core\Logger::security("CSRF validation failed", [
                'token_present' => !empty($token),
                'ip' => \App\Core\Logger::resolveClientIp(),
            ]);
        }

        return $isValid;
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }
}
