<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Services\DashboardService;
use App\Services\PermissionService;
use Throwable;

class DashboardController
{
    private DashboardService $service;

    public function __construct(?DashboardService $service = null)
    {
        $this->service = $service ?? new DashboardService();
    }

    /**
     * Dashboard page view.
     * GET /dashboard
     */
    public function index(): void
    {
        Session::start();
        $isSales = !PermissionService::can('client.view_all');

        Response::view('dashboard', [
            'title' => 'Dashboard — CRM Portal',
            'pageHeading' => 'Dashboard',
            'isSales' => $isSales,
        ]);
    }

    /**
     * Dashboard aggregated metrics API.
     * GET /api/dashboard/stats
     */
    public function stats(): void
    {
        try {
            $data = $this->service->getStats();
            Response::success($data);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
