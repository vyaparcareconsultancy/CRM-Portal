<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AuthMiddleware
{
    public function handle(array $params = []): bool
    {
        Session::start();
        $userId = Session::get('user_id');

        if ($userId !== null && (int)$userId > 0) {
            return true;
        }

        $request = Request::createFromGlobals();
        if ($request->isJson() || str_starts_with($request->path(), '/api/')) {
            Response::error('Unauthenticated', 401);
            return false;
        }

        Response::redirect('/login');
        return false;
    }
}
