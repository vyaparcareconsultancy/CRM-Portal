<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Helpers\Csrf;
use App\Services\ClientComplianceService;
use Throwable;

class ClientComplianceController
{
    private ClientComplianceService $service;

    public function __construct(?ClientComplianceService $service = null)
    {
        $this->service = $service ?? new ClientComplianceService();
    }

    // ==========================================
    // Client Services (Subscriptions)
    // ==========================================

    public function listServices(array $params = []): void
    {
        $clientId = (int)($params['id'] ?? 0);
        try {
            $services = $this->service->listClientServices($clientId);
            Response::success($services);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function storeService(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $clientId = (int)($params['id'] ?? 0);
        try {
            $data = $request->body();
            $created = $this->service->addClientService($clientId, $data);
            Response::success($created, 'Service added to client profile.', 201);
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function updateService(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $serviceId = (int)($params['serviceId'] ?? 0);
        try {
            $data = $request->body();
            $updated = $this->service->updateClientService($serviceId, $data);
            Response::success($updated, 'Client service updated successfully.');
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function deleteService(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $serviceId = (int)($params['serviceId'] ?? 0);
        try {
            $this->service->deleteClientService($serviceId);
            Response::success(null, 'Client service subscription removed.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    // ==========================================
    // Compliance Details & Encrypted Portal Passwords
    // ==========================================

    public function getCompliance(array $params = []): void
    {
        $clientId = (int)($params['id'] ?? 0);
        try {
            $details = $this->service->getComplianceDetails($clientId, false);
            Response::success($details);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function saveCompliance(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $clientId = (int)($params['id'] ?? 0);
        try {
            $data = $request->body();
            $saved = $this->service->saveComplianceDetails($clientId, $data);
            Response::success($saved, 'Compliance details saved successfully.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function revealCredentials(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $clientId = (int)($params['id'] ?? 0);
        try {
            $details = $this->service->getComplianceDetails($clientId, true);
            Response::success($details, 'Portal credentials decrypted successfully.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    // ==========================================
    // Work Tracker per Service Period
    // ==========================================

    public function listWorkTracker(array $params = []): void
    {
        $request = Request::createFromGlobals();
        $clientId = (int)($params['id'] ?? 0);
        $clientServiceId = $request->query('client_service_id') ? (int)$request->query('client_service_id') : null;

        try {
            $items = $this->service->listWorkTracker($clientId, $clientServiceId);
            Response::success($items);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function storeWorkTracker(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $clientId = (int)($params['id'] ?? 0);
        try {
            $data = $request->body();
            $clientServiceId = (int)($data['client_service_id'] ?? 0);
            if (!$clientServiceId) {
                Response::error('Please select an active client service for this work item.', 422, ['client_service_id' => 'Service is required.']);
                return;
            }

            $created = $this->service->addWorkTrackerItem($clientId, $clientServiceId, $data);
            Response::success($created, 'Work tracker entry logged.', 201);
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function updateWorkTracker(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $trackerId = (int)($params['trackerId'] ?? 0);
        try {
            $data = $request->body();
            $updated = $this->service->updateWorkTrackerItem($trackerId, $data);
            Response::success($updated, 'Work tracker item updated.');
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function deleteWorkTracker(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $trackerId = (int)($params['trackerId'] ?? 0);
        try {
            $this->service->deleteWorkTrackerItem($trackerId);
            Response::success(null, 'Work tracker item deleted.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
