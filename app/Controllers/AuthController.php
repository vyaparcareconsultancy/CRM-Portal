<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Helpers\Csrf;
use App\Services\AuthService;

class AuthController
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function showLogin(): void
    {
        Session::start();
        if (Session::get('user_id')) {
            Response::redirect('/dashboard');
            return;
        }

        $turnstile = new \App\Services\TurnstileService();

        Response::view('auth/login', [
            'title' => 'Sign In — CRM Portal',
            'csrf_token' => Csrf::getToken(),
            'turnstile_enabled' => $turnstile->isEnabled(),
            'turnstile_site_key' => $turnstile->getSiteKey(),
        ], null); // render without app layout (clean full-screen login)
    }

    public function showResetPassword(): void
    {
        $request = Request::createFromGlobals();
        $token = (string)$request->query('token', '');
        $email = (string)$request->query('email', '');

        Response::view('auth/reset', [
            'title' => 'Reset Password — CRM Portal',
            'token' => $token,
            'email' => $email,
            'csrf_token' => Csrf::getToken(),
        ], null);
    }

    public function login(): void
    {
        $request = Request::createFromGlobals();

        // 1. Verify CSRF for state-changing requests
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        // 2. Validate input
        $validator = Validator::make($request->body(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        // 3. Verify Turnstile if enabled
        $turnstile = new \App\Services\TurnstileService();
        if ($turnstile->isEnabled()) {
            $turnstileToken = $request->body('cf-turnstile-response')
                ?? $request->body('turnstile_token');
            if (!$turnstile->verify($turnstileToken, $request->ip())) {
                Response::error('Security verification failed. Please try again.', 422, null, [
                    'turnstile' => 'Captcha verification failed. Please complete the security check.',
                ]);
                return;
            }
        }

        $email = (string)$request->input('email');
        $password = (string)$request->input('password');
        $ip = $request->ip();

        // 4. Authenticate
        $result = $this->authService->login($email, $password, $ip);

        if (!$result['success']) {
            Response::error($result['message'], $result['status_code']);
            return;
        }

        Response::success($result['data'], $result['message']);
    }

    public function logout(): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $this->authService->logout();
        Response::success(null, 'Logged out successfully');
    }

    public function me(): void
    {
        $currentUser = $this->authService->getCurrentUser();

        if ($currentUser === null) {
            Response::error('Unauthenticated', 401);
            return;
        }

        Response::success($currentUser);
    }

    public function forgotPassword(): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $validator = Validator::make($request->body(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        $email = (string)$request->input('email');
        $this->authService->sendResetLink($email);

        // Always return generic success message to prevent user enumeration
        Response::success(
            null,
            'If your email address is registered, you will receive a password reset link shortly.'
        );
    }

    public function resetPassword(): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $validator = Validator::make($request->body(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|password_policy',
            'password_confirmation' => 'required',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        $password = (string)$request->input('password');
        $confirmation = (string)$request->input('password_confirmation');

        if ($password !== $confirmation) {
            Response::validationError(['password_confirmation' => 'Password confirmation does not match.']);
            return;
        }

        $email = (string)$request->input('email');
        $token = (string)$request->input('token');

        $result = $this->authService->resetPassword($email, $token, $password);

        if (!$result['success']) {
            Response::error($result['message'], $result['status_code']);
            return;
        }

        Response::success(null, $result['message']);
    }
}
