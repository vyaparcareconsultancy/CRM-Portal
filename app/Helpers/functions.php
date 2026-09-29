<?php

declare(strict_types=1);

/**
 * Global helper functions for CRM views and templates.
 */

if (!function_exists('e')) {
    /**
     * Escape HTML special characters for safe output.
     */
    function e(mixed $value): string
    {
        return \App\Core\View::e($value);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate versioned asset URL with cache-busting query parameter.
     */
    function asset(string $path): string
    {
        return \App\Core\View::asset($path);
    }
}

if (!function_exists('can')) {
    /**
     * Check if currently authenticated user has a specific permission.
     */
    function can(string $permission): bool
    {
        return \App\Services\PermissionService::can($permission);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Retrieve the current CSRF token.
     */
    function csrf_token(): string
    {
        return \App\Helpers\Csrf::getToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a hidden HTML input field containing the CSRF token.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . \App\Core\View::e(\App\Helpers\Csrf::getToken()) . '">';
    }
}

if (!function_exists('url')) {
    /**
     * Generate relative application URL.
     */
    function url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve previously submitted form input or default fallback value.
     */
    function old(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? \App\Core\Session::get('_old_' . $key, $default);
    }
}

if (!function_exists('auth')) {
    /**
     * Get authenticated user session data or a specific user property.
     */
    function auth(?string $key = null): mixed
    {
        \App\Core\Session::start();
        $user = [
            'id' => \App\Core\Session::get('user_id'),
            'name' => \App\Core\Session::get('user_name'),
            'email' => \App\Core\Session::get('user_email'),
            'role' => \App\Core\Session::get('user_role'),
        ];

        if ($key !== null) {
            return $user[$key] ?? \App\Core\Session::get($key);
        }

        return $user['id'] !== null ? $user : null;
    }
}
