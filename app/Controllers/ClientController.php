<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Helpers\Csrf;
use App\Services\ClientService;
use Throwable;

class ClientController
{
    private ClientService $clientService;

    public function __construct(?ClientService $clientService = null)
    {
        $this->clientService = $clientService ?? new ClientService();
    }

    /**
     * List clients endpoint supporting both standard API and DataTables serverSide.
     * GET /api/clients
     */
    public function index(): void
    {
        $request = Request::createFromGlobals();

        $start = (int)$request->query('start', 0);
        $length = (int)$request->query('length', 15);
        $draw = $request->query('draw') !== null ? (int)$request->query('draw') : null;

        $page = $length > 0 ? (int)floor($start / $length) + 1 : max(1, (int)$request->query('page', 1));
        $perPage = $length > 0 ? $length : max(1, min(100, (int)$request->query('per_page', 15)));

        // Extract search term from DataTables search[value] or regular query
        $dtSearch = $request->query('search');
        $searchTerm = is_array($dtSearch)
            ? (string)($dtSearch['value'] ?? '')
            : (string)($dtSearch ?? $request->query('q', ''));

        // Extract sorting
        $order = $request->query('order');
        $columns = $request->query('columns');
        $sortBy = 'id';
        $sortDir = 'DESC';

        if (is_array($order) && isset($order[0]['column'], $order[0]['dir'])) {
            $colIdx = (int)$order[0]['column'];
            $colName = $columns[$colIdx]['data'] ?? $columns[$colIdx]['name'] ?? null;
            if (is_string($colName) && $colName !== '') {
                $sortBy = $colName;
            }
            $sortDir = (string)($order[0]['dir'] ?? 'DESC');
        } else {
            $sortBy = (string)$request->query('sort_by', 'id');
            $sortDir = (string)$request->query('sort_dir', 'DESC');
        }

        $filters = [
            'search' => $searchTerm,
            'status' => $request->query('status'),
            'city' => $request->query('city'),
            'state' => $request->query('state'),
            'lead_source' => $request->query('lead_source'),
            'assigned_to' => $request->query('assigned_to'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        try {
            $result = $this->clientService->listClients($filters, $page, $perPage, $sortBy, $sortDir);

            if ($draw !== null) {
                // Direct DataTables serverSide response format
                Response::json([
                    'draw' => $draw,
                    'recordsTotal' => $result['recordsTotal'],
                    'recordsFiltered' => $result['recordsFiltered'],
                    'data' => $result['items'],
                ]);
                return;
            }

            Response::success($result);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Get single client profile with documents and activities.
     * GET /api/clients/{id}
     *
     * @param array<string, string> $params
     */
    public function show(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Client not found', 404);
            return;
        }
        $id = (int)$rawId;

        try {
            $profile = $this->clientService->getClientProfile($id);
            Response::success($profile);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Create client endpoint (POST /api/clients).
     */
    public function store(): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        try {
            $data = $request->body();
            $files = $request->files();
            $ip = $request->ip();
            $userAgent = is_string($request->headers('user-agent')) ? $request->headers('user-agent') : null;

            $result = $this->clientService->createClient($data, $files, null, $ip, $userAgent);

            Response::success($result, 'Client created successfully', 201);
        } catch (ValidationException $e) {
            Response::validationError($e->getErrors(), $e->getMessage());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Update client endpoint (PUT /api/clients/{id}).
     *
     * @param array<string, string> $params
     */
    public function update(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Client not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        try {
            $data = $request->body();
            $ip = $request->ip();
            $userAgent = is_string($request->headers('user-agent')) ? $request->headers('user-agent') : null;

            $updated = $this->clientService->updateClient($id, $data, null, $ip, $userAgent);
            Response::success($updated, 'Client updated successfully');
        } catch (ValidationException $e) {
            Response::validationError($e->getErrors(), $e->getMessage());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Soft delete client endpoint (DELETE /api/clients/{id}).
     *
     * @param array<string, string> $params
     */
    public function destroy(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Client not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        try {
            $ip = $request->ip();
            $userAgent = is_string($request->headers('user-agent')) ? $request->headers('user-agent') : null;

            $this->clientService->deleteClient($id, null, $ip, $userAgent);
            Response::success(null, 'Client deleted successfully');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Upload document for client (POST /api/clients/{id}/documents).
     *
     * @param array<string, string> $params
     */
    public function uploadDocument(array $params): void
    {
        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Client not found', 404);
            return;
        }
        $id = (int)$rawId;

        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $file = $request->files('document') ?? $request->files('file');
        if (!$file || !is_array($file)) {
            Response::error('No document file was uploaded', 422);
            return;
        }

        try {
            $ip = $request->ip();
            $userAgent = is_string($request->headers('user-agent')) ? $request->headers('user-agent') : null;

            $doc = $this->clientService->addDocument($id, $file, null, $ip, $userAgent);
            Response::success($doc, 'Document uploaded successfully', 201);
        } catch (ValidationException $e) {
            Response::validationError($e->getErrors(), $e->getMessage());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Delete document (DELETE /api/clients/{id}/documents/{docId}).
     *
     * @param array<string, string> $params
     */
    public function destroyDocument(array $params): void
    {
        $rawId = $params['id'] ?? '';
        $rawDocId = $params['docId'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0 || !ctype_digit((string)$rawDocId) || (int)$rawDocId <= 0) {
            Response::error('Client or document not found', 404);
            return;
        }
        $clientId = (int)$rawId;
        $docId = (int)$rawDocId;

        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        try {
            $ip = $request->ip();
            $userAgent = is_string($request->headers('user-agent')) ? $request->headers('user-agent') : null;

            $this->clientService->deleteDocument($clientId, $docId, null, $ip, $userAgent);
            Response::success(null, 'Document deleted successfully');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Stream protected document file (GET /api/clients/{id}/documents/{docId}).
     *
     * @param array<string, string> $params
     */
    public function downloadDocument(array $params): void
    {
        $rawId = $params['id'] ?? '';
        $rawDocId = $params['docId'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0 || !ctype_digit((string)$rawDocId) || (int)$rawDocId <= 0) {
            Response::error('Client or document not found', 404);
            return;
        }
        $clientId = (int)$rawId;
        $docId = (int)$rawDocId;

        try {
            $doc = $this->clientService->getDocument($clientId, $docId);

            $filePath = $doc['file_path'];
            $originalName = $doc['original_name'];
            $mimeType = $doc['mime_type'];
            $fileSize = $doc['size_bytes'];

            if (!headers_sent()) {
                http_response_code(200);
                header("Content-Type: {$mimeType}");
                header('Content-Disposition: inline; filename="' . addcslashes($originalName, '"\\') . '"');
                header("Content-Length: {$fileSize}");
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
            }

            readfile($filePath);
            exit;
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Export clients as XLSX or CSV (GET /api/clients/export).
     */
    public function export(): void
    {
        $request = Request::createFromGlobals();
        $format = (string)$request->query('format', 'xlsx');

        $filters = [
            'search' => $request->query('search') ?? $request->query('q'),
            'status' => $request->query('status'),
            'city' => $request->query('city'),
            'state' => $request->query('state'),
            'lead_source' => $request->query('lead_source'),
            'assigned_to' => $request->query('assigned_to'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        try {
            $export = $this->clientService->exportClients($filters, $format);

            if (!headers_sent()) {
                http_response_code(200);
                header("Content-Type: {$export['mime_type']}");
                header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
                header('Content-Length: ' . filesize($export['file_path']));
                header('Cache-Control: max-age=0');
                header('Pragma: public');
            }

            readfile($export['file_path']);
            @unlink($export['file_path']);
            exit;
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Return lookups for dropdowns (GET /api/lookups).
     */
    public function lookups(): void
    {
        try {
            $data = $this->clientService->getLookups();
            Response::success($data, 'Lookups retrieved successfully');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Anonymize client personal data pursuant to DPDP Act (POST /api/clients/{id}/anonymize).
     *
     * @param array<string, string> $params
     */
    public function anonymize(array $params): void
    {
        $request = Request::createFromGlobals();

        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid CSRF token', 403);
            return;
        }

        $rawId = $params['id'] ?? '';
        if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
            Response::error('Client not found', 404);
            return;
        }
        $id = (int)$rawId;

        try {
            $this->clientService->anonymizeClient($id, null, $request->ip(), $request->userAgent());
            Response::success(null, 'Client personal data has been securely anonymized pursuant to DPDP Act.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
