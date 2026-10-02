<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Models\Course;
use App\Models\LeadSource;
use App\Models\Service;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\LeadService;
use App\Services\PermissionService;
use Throwable;

class LeadController
{
    private LeadService $service;
    private FollowUpService $followUpService;

    public function __construct(?LeadService $service = null, ?FollowUpService $followUpService = null)
    {
        $this->service = $service ?? new LeadService();
        $this->followUpService = $followUpService ?? new FollowUpService();
    }

    /**
     * List leads (table view or Kanban view).
     * GET /api/leads
     */
    public function index(): void
    {
        $request = Request::createFromGlobals();

        $filters = [
            'search' => (string)($request->query('search') ?? $request->query('q', '')),
            'status' => $request->query('status'),
            'lead_source_id' => $request->query('lead_source_id') ?? $request->query('source'),
            'source' => $request->query('source'),
            'assigned_to' => $request->query('assigned_to'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'page' => max(1, (int)$request->query('page', 1)),
            'per_page' => max(1, min(100, (int)$request->query('per_page', 25))),
            'sort_by' => (string)$request->query('sort_by', 'created_at'),
            'sort_dir' => (string)$request->query('sort_dir', 'DESC'),
        ];

        $view = (string)$request->query('view', 'list');

        try {
            if ($view === 'kanban') {
                $data = $this->service->getKanban($filters);
            } else {
                $data = $this->service->list($filters);
            }
            Response::success($data);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Get single lead details.
     * GET /api/leads/{id}
     */
    public function show(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Lead not found', 404);
            return;
        }
        $id = (int)$rawId;

        try {
            $lead = $this->service->get($id);
            $followUps = $this->followUpService->getByLead($id);
            $lead['follow_ups'] = $followUps;
            Response::success($lead);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Create a new lead.
     * POST /api/leads
     */
    public function store(): void
    {
        $request = Request::createFromGlobals();
        $data = $request->all();

        try {
            $lead = $this->service->create($data, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
            Response::success($lead, 201);
        } catch (ValidationException $e) {
            Response::json(['success' => false, 'errors' => $e->getErrors(), 'message' => 'Validation failed.'], 422);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Update an existing lead.
     * PUT /api/leads/{id}
     */
    public function update(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Lead not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();
        $data = $request->all();

        try {
            $lead = $this->service->update($id, $data, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
            Response::success($lead);
        } catch (ValidationException $e) {
            Response::json(['success' => false, 'errors' => $e->getErrors(), 'message' => 'Validation failed.'], 422);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Update lead status (Kanban drag & drop).
     * PATCH /api/leads/{id}/status or POST /api/leads/{id}/status
     */
    public function updateStatus(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Lead not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();
        $status = (string)$request->input('status', '');
        $lostReason = $request->input('lost_reason');

        try {
            $lead = $this->service->updateStatus($id, $status, $lostReason, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
            Response::success($lead);
        } catch (ValidationException $e) {
            Response::json(['success' => false, 'errors' => $e->getErrors(), 'message' => 'Validation failed.'], 422);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Convert lead to client and/or student.
     * POST /api/leads/{id}/convert
     */
    public function convert(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Lead not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();
        $options = $request->all();

        try {
            $result = $this->service->convert($id, $options, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
            Response::success($result);
        } catch (ValidationException $e) {
            Response::json(['success' => false, 'errors' => $e->getErrors(), 'message' => 'Validation failed.'], 422);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Import leads from CSV file.
     * POST /api/leads/import
     */
    public function importCsv(): void
    {
        $content = '';
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $content = (string)file_get_contents($_FILES['file']['tmp_name']);
        } else {
            $request = Request::createFromGlobals();
            $content = (string)$request->input('csv_content', '');
        }

        if (trim($content) === '') {
            Response::error('Please upload a valid CSV file.', 400);
            return;
        }

        $request = Request::createFromGlobals();
        $sourceId = !empty($request->input('default_source_id')) ? (int)$request->input('default_source_id') : null;
        $assignedTo = !empty($request->input('default_assigned_to')) ? (int)$request->input('default_assigned_to') : null;

        try {
            $summary = $this->service->importCsv($content, $sourceId, $assignedTo);
            Response::success($summary);
        } catch (ValidationException $e) {
            Response::json(['success' => false, 'errors' => $e->getErrors(), 'message' => 'Import validation failed.'], 422);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Get lookup options (sources, services, courses, staff).
     * GET /api/leads/lookups
     */
    public function lookups(): void
    {
        $sourceModel = new LeadSource();
        $serviceModel = new Service();
        $courseModel = new Course();
        $userModel = new User();

        $sources = $sourceModel->getActive();
        $services = $serviceModel->getActive();
        $courses = $courseModel->getActive();
        $staff = $userModel->where(['is_active' => 1]);

        $staffList = array_map(static fn($u) => [
            'id' => (int)$u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
        ], $staff);

        Response::success([
            'sources' => $sources,
            'services' => $services,
            'courses' => $courses,
            'staff' => $staffList,
        ]);
    }
}
