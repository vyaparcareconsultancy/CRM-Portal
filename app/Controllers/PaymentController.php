<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;
use Throwable;

class PaymentController
{
    private PaymentService $paymentService;

    public function __construct(?PaymentService $paymentService = null)
    {
        $this->paymentService = $paymentService ?? new PaymentService();
    }

    public function index(): void
    {
        $request = Request::createFromGlobals();
        $page = max(1, (int)$request->query('page', 1));
        $perPage = max(1, min(100, (int)$request->query('per_page', 25)));

        try {
            $data = $this->paymentService->listPayments([], $page, $perPage);
            Response::success($data);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 403;
            Response::error($e->getMessage(), $code);
        }
    }

    public function show(array $params): void
    {
        $id = $params['id'] ?? '';
        try {
            $data = $this->paymentService->getPayment($id);
            if (!$data) {
                Response::error('Payment not found', 404);
                return;
            }
            Response::success($data);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 403;
            Response::error($e->getMessage(), $code);
        }
    }

    public function store(): void
    {
        $request = Request::createFromGlobals();
        try {
            $data = $this->paymentService->recordPayment($request->body());
            Response::success($data, 'Payment recorded', 201);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 403;
            Response::error($e->getMessage(), $code);
        }
    }
}
