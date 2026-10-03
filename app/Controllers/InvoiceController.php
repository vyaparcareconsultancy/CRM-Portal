<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Helpers\Csrf;
use App\Services\InvoiceService;
use Throwable;

class InvoiceController
{
    private InvoiceService $invoiceService;

    public function __construct(?InvoiceService $invoiceService = null)
    {
        $this->invoiceService = $invoiceService ?? new InvoiceService();
    }

    public function apiList(): void
    {
        $request = Request::createFromGlobals();
        $page = max(1, (int)$request->query('page', 1));
        $perPage = min(100, max(5, (int)$request->query('per_page', 25)));

        $filters = [
            'client_id' => $request->query('client_id'),
            'student_id' => $request->query('student_id'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
        ];

        try {
            $data = $this->invoiceService->listInvoices($filters, $page, $perPage);
            Response::success($data);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function apiShow(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        try {
            $invoice = $this->invoiceService->getInvoice($id);
            Response::success($invoice);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function apiStore(): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        try {
            $data = $request->body();
            $invoice = $this->invoiceService->createInvoice($data);
            Response::success($invoice, 'Invoice created successfully.', 201);
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

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
            $invoice = $this->invoiceService->updateInvoice($id, $data);
            Response::success($invoice, 'Invoice updated successfully.');
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function apiDelete(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $id = (int)($params['id'] ?? 0);
        try {
            $this->invoiceService->deleteInvoice($id);
            Response::success(null, 'Invoice cancelled/deleted successfully.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
