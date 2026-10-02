<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\LeadSourceService;
use Throwable;

class LeadSourceController
{
    private LeadSourceService $service;

    public function __construct(?LeadSourceService $service = null)
    {
        $this->service = $service ?? new LeadSourceService();
    }

    /**
     * List all lead sources.
     * GET /api/lead-sources
     */
    public function index(): void
    {
        try {
            $sources = $this->service->all();
            Response::success($sources);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Create new lead source.
     * POST /api/lead-sources
     */
    public function store(): void
    {
        $request = Request::createFromGlobals();
        $data = $request->all();

        try {
            $source = $this->service->create($data);
            Response::success($source, 201);
        } catch (ValidationException $e) {
            Response::json(['success' => false, 'errors' => $e->getErrors(), 'message' => 'Validation failed.'], 422);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Update lead source.
     * PUT /api/lead-sources/{id}
     */
    public function update(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Lead source not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();
        $data = $request->all();

        try {
            $source = $this->service->update($id, $data);
            Response::success($source);
        } catch (ValidationException $e) {
            Response::json(['success' => false, 'errors' => $e->getErrors(), 'message' => 'Validation failed.'], 422);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
