<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Helpers\Csrf;
use App\Services\ServiceCatalogService;
use Throwable;

class ServiceCatalogController
{
    private ServiceCatalogService $service;

    public function __construct(?ServiceCatalogService $service = null)
    {
        $this->service = $service ?? new ServiceCatalogService();
    }

    /**
     * Web: Render Services Master Page.
     * GET /services
     */
    public function index(): void
    {
        $services = $this->service->listServices();
        Response::view('services/index', [
            'title' => 'Services Master Catalog — CRM Portal',
            'pageHeading' => 'Services Master Catalog',
            'services' => $services,
            'csrf_token' => Csrf::getToken(),
        ]);
    }

    /**
     * API: List services.
     * GET /api/services
     */
    public function apiList(): void
    {
        $request = Request::createFromGlobals();
        $activeOnly = $request->query('active_only') === '1';
        $services = $this->service->listServices($activeOnly ?: null);
        Response::success($services);
    }

    /**
     * API: Create service.
     * POST /api/services
     */
    public function apiStore(): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        try {
            $data = $request->body();
            $created = $this->service->createService($data);
            Response::success($created, 'Service created successfully.', 201);
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Update service.
     * POST /api/services/{id} or PUT
     */
    public function apiUpdate(array $params = []): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $id = (int)($params['id'] ?? 0);
        try {
            $data = $request->body();
            $updated = $this->service->updateService($id, $data);
            Response::success($updated, 'Service updated successfully.');
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Delete service.
     * DELETE /api/services/{id} or POST /api/services/{id}/delete
     */
    public function apiDelete(array $params = []): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $id = (int)($params['id'] ?? 0);
        try {
            $this->service->deleteService($id);
            Response::success(null, 'Service removed from catalog.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
