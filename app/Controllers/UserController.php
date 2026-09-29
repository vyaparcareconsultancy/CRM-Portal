<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Helpers\Csrf;
use App\Services\UserService;
use Throwable;

class UserController
{
    private UserService $userService;

    public function __construct(?UserService $userService = null)
    {
        $this->userService = $userService ?? new UserService();
    }

    public function index(): void
    {
        $request = Request::createFromGlobals();
        $page = max(1, (int)$request->query('page', 1));
        $perPage = max(1, min(100, (int)$request->query('per_page', 25)));

        $data = $this->userService->listUsers($page, $perPage);
        Response::success($data);
    }

    public function store(): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $validator = Validator::make($request->body(), [
            'name' => 'required|min:2|max:150',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|password_policy',
            'role_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        try {
            $user = $this->userService->createUser($request->body());
            Response::success($user, 'User created successfully', 201);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function update(array $params): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('User not found', 404);
            return;
        }
        $id = (int)$rawId;

        $body = $request->body();
        $rules = [
            'name' => 'required|min:2|max:150',
            'email' => "required|email|unique:users,email,{$id}",
            'role_id' => 'required|numeric',
        ];

        if (!empty($body['password'])) {
            $rules['password'] = 'min:8|password_policy';
        }

        $validator = Validator::make($body, $rules);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
            return;
        }

        try {
            $updated = $this->userService->updateUser($id, $body);
            Response::success($updated, 'User updated successfully');
        } catch (Throwable $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 500 ? (int)$e->getCode() : 400;
            Response::error($e->getMessage(), $statusCode);
        }
    }
}
