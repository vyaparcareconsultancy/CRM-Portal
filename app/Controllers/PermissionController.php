<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Services\RolePermissionService;
use Throwable;

class PermissionController
{
    private RolePermissionService $service;

    public function __construct(?RolePermissionService $service = null)
    {
        $this->service = $service ?? new RolePermissionService();
    }

    /**
     * Render the Permission Matrix Admin Page.
     * GET /admin/permissions
     */
    public function index(): void
    {
        $data = $this->service->getMatrix();
        Response::view('admin/permissions/index', array_merge($data, [
            'title' => 'Roles & Permissions Matrix — CRM Portal',
            'pageHeading' => 'Roles & Permissions Matrix',
            'csrf_token' => Csrf::getToken(),
        ]));
    }

    /**
     * API: Get matrix data JSON.
     * GET /api/admin/permissions/matrix
     */
    public function getMatrix(): void
    {
        $data = $this->service->getMatrix();
        Response::success($data);
    }

    /**
     * API: Toggle a permission on/off for a role.
     * POST /api/admin/permissions/toggle
     */
    public function toggle(): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $body = $request->body();
        $roleId = isset($body['role_id']) ? (int)$body['role_id'] : 0;
        $permissionId = $body['permission_id'] ?? $body['permission'] ?? null;
        $enabled = filter_var($body['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($roleId <= 0 || empty($permissionId)) {
            Response::error('Role ID and Permission are required.', 422);
            return;
        }

        try {
            $result = $this->service->toggle($roleId, $permissionId, $enabled);
            Response::success($result, 'Permission updated successfully.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }
}
