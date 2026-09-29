<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Helpers\Csrf;
use App\Services\FollowUpService;
use Throwable;

class FollowUpController
{
    private FollowUpService $service;

    public function __construct(?FollowUpService $service = null)
    {
        $this->service = $service ?? new FollowUpService();
    }

    /**
     * List follow-ups with tab, client, type filters and pagination.
     * GET /api/followups
     */
    public function index(): void
    {
        $request = Request::createFromGlobals();

        $filters = [
            'tab' => (string)$request->query('tab', 'today'),
            'client_id' => $request->query('client_id'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'search' => (string)($request->query('search') ?? $request->query('q', '')),
            'page' => max(1, (int)$request->query('page', 1)),
            'per_page' => max(1, min(100, (int)$request->query('per_page', 20))),
            'sort_by' => (string)$request->query('sort_by', 'due_at'),
            'sort_dir' => (string)$request->query('sort_dir', 'ASC'),
        ];

        try {
            $data = $this->service->list($filters);
            Response::success($data);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * List follow-ups for a specific client.
     * GET /api/clients/{id}/followups
     */
    public function clientFollowups(array $params): void
    {
        $clientId = (int)($params['id'] ?? 0);
        if ($clientId <= 0) {
            Response::error('Invalid client ID', 400);
            return;
        }

        try {
            $items = $this->service->getByClient($clientId);
            Response::success($items);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Get single follow-up.
     * GET /api/followups/{id}
     */
    public function show(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Follow-up not found', 404);
            return;
        }
        $id = (int)$rawId;

        try {
            $item = $this->service->get($id);
            Response::success($item);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Create a follow-up.
     * POST /api/followups or POST /api/clients/{id}/followups
     */
    public function store(array $params = []): void
    {
        if (!Csrf::validateRequest()) {
            Response::error('Invalid or expired CSRF token.', 403);
            return;
        }

        $request = Request::createFromGlobals();
        $body = $request->body();
        if (empty($body)) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $body = $json;
            }
        }

        $clientId = !empty($params['id']) ? (int)$params['id'] : null;

        try {
            $created = $this->service->create($body, $clientId, $request->ip(), $request->userAgent());
            Response::success($created, 'Follow-up scheduled successfully', 201);
        } catch (ValidationException $e) {
            Response::validationError($e->getErrors(), 'Validation failed');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Update follow-up or mark as done with outcome note.
     * PUT /api/followups/{id}
     */
    public function update(array $params): void
    {
        if (!Csrf::validateRequest()) {
            Response::error('Invalid or expired CSRF token.', 403);
            return;
        }

        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Follow-up not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();
        $body = $request->body();
        if (empty($body)) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $body = $json;
            }
        }

        try {
            $updated = $this->service->update($id, $body, $request->ip(), $request->userAgent());
            Response::success($updated, 'Follow-up updated successfully');
        } catch (ValidationException $e) {
            Response::validationError($e->getErrors(), 'Validation failed');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Soft delete follow-up.
     * DELETE /api/followups/{id}
     */
    public function destroy(array $params): void
    {
        if (!Csrf::validateRequest()) {
            Response::error('Invalid or expired CSRF token.', 403);
            return;
        }

        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Follow-up not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();
        try {
            $this->service->delete($id, $request->ip(), $request->userAgent());
            Response::success(null, 'Follow-up deleted successfully');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
