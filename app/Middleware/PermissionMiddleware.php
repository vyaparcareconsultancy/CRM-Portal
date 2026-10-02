<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\PermissionService;

class PermissionMiddleware
{
    private ?string $permission = null;

    public function __construct(?string $permission = null)
    {
        $this->permission = $permission;
    }

    public function handle(array $params = [], ?string $permission = null): bool
    {
        Session::start();
        $userId = Session::get('user_id');

        $requiredPermission = $permission ?? $this->permission;
        $request = Request::createFromGlobals();

        // 1. Must be authenticated
        if (!$userId) {
            if ($request->isJson() || str_starts_with($request->path(), '/api/')) {
                Response::error('Unauthenticated', 401);
                return false;
            }
            Response::redirect('/login');
            return false;
        }

        // 2. Check permission if specified (supports multiple alternatives separated by |)
        if ($requiredPermission !== null) {
            $alternatives = explode('|', $requiredPermission);
            $hasPermission = false;
            foreach ($alternatives as $perm) {
                if (PermissionService::can(trim($perm))) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                if ($request->isJson() || str_starts_with($request->path(), '/api/')) {
                    Response::error('Forbidden: insufficient permissions', 403);
                    return false;
                }

                // Render 403 page for browser requests
                if (!headers_sent()) {
                    http_response_code(403);
                    header('Content-Type: text/html; charset=utf-8');
                }
                echo View::render('errors/403', [
                    'title' => '403 Forbidden — CRM Portal',
                    'message' => 'You do not have permission to perform this action.',
                ], null);
                exit;
            }
        }

        return true;
    }
}
