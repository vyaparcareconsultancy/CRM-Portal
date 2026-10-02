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

    public function roles(): void
    {
        Response::success($this->userService->getRoles());
    }

    public function store(): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $body = $request->body();
        if (empty($body['role_id']) && !empty($body['role'])) {
            foreach ($this->userService->getRoles() as $r) {
                if (strtolower($r['name']) === strtolower((string)$body['role'])) {
                    $body['role_id'] = $r['id'];
                    break;
                }
            }
        }

        $validator = Validator::make($body, [
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
            $user = $this->userService->createUser($body);
            Response::success($user, 'User created successfully', 201);
        } catch (Throwable $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 500 ? (int)$e->getCode() : 400;
            Response::error($e->getMessage(), $statusCode);
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
        if (empty($body['role_id']) && !empty($body['role'])) {
            foreach ($this->userService->getRoles() as $r) {
                if (strtolower($r['name']) === strtolower((string)$body['role'])) {
                    $body['role_id'] = $r['id'];
                    break;
                }
            }
        }

        if (array_key_exists('is_active', $body)) {
            $body['is_active'] = $body['is_active'] ? 1 : 0;
        }

        $rules = [];
        if (array_key_exists('name', $body)) {
            $rules['name'] = 'required|min:2|max:150';
        }
        if (array_key_exists('email', $body)) {
            $rules['email'] = "required|email|unique:users,email,{$id}";
        }
        if (array_key_exists('role_id', $body)) {
            $rules['role_id'] = 'required|numeric';
        }
        if (!empty($body['password'])) {
            $rules['password'] = 'min:8|password_policy';
        }
        if (array_key_exists('is_active', $body)) {
            $rules['is_active'] = 'in:0,1';
        }

        if (empty($rules)) {
            Response::error('No update data provided', 422);
            return;
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

    public function destroy(array $params): void
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

        try {
            $this->userService->deleteUser($id);
            Response::success(null, 'User deleted successfully');
        } catch (Throwable $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 500 ? (int)$e->getCode() : 400;
            Response::error($e->getMessage(), $statusCode);
        }
    }
}
