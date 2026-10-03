<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Helpers\Csrf;
use App\Services\PaymentService;
use Throwable;

class PaymentController
{
    private PaymentService $paymentService;

    public function __construct(?PaymentService $paymentService = null)
    {
        $this->paymentService = $paymentService ?? new PaymentService();
    }

    /**
     * Web page: /payments
     */
    public function index(): void
    {
        Response::view('payments/index', [
            'pageTitle' => 'Payments & Invoicing Ledger',
        ]);
    }

    /**
     * API: List payments with filters and totals.
     */
    public function apiList(): void
    {
        $request = Request::createFromGlobals();
        $page = max(1, (int)$request->query('page', 1));
        $perPage = min(100, max(5, (int)$request->query('per_page', 25)));

        $filters = [
            'client_id' => $request->query('client_id'),
            'student_id' => $request->query('student_id'),
            'invoice_id' => $request->query('invoice_id'),
            'payment_mode' => $request->query('payment_mode'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
        ];

        try {
            $data = $this->paymentService->listPayments($filters, $page, $perPage);
            Response::success($data);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Get single payment.
     */
    public function apiShow(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        try {
            $payment = $this->paymentService->getPayment($id);
            if (!$payment) {
                Response::error('Payment not found.', 404);
                return;
            }
            Response::success($payment);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Record payment.
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
            $payment = $this->paymentService->recordPayment($data);
            Response::success($payment, 'Payment recorded successfully.', 201);
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Update payment.
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
            $payment = $this->paymentService->updatePayment($id, $data);
            Response::success($payment, 'Payment record updated successfully.');
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Soft delete payment.
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
            $this->paymentService->deletePayment($id);
            Response::success(null, 'Payment deleted successfully.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Download or view PDF receipt.
     */
    public function downloadReceipt(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        try {
            $pdf = $this->paymentService->generateReceiptPdf($id);
            $payment = $this->paymentService->getPayment($id);
            $receiptNo = $payment['receipt_no'] ?? "REC-{$id}";

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="receipt-' . $receiptNo . '.pdf"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $pdf;
            exit;
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Send receipt by email.
     */
    public function emailReceipt(array $params = []): void
    {
        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $id = (int)($params['id'] ?? 0);
        try {
            $body = $request->body();
            $email = !empty($body['email']) ? trim((string)$body['email']) : null;
            $sent = $this->paymentService->emailReceipt($id, $email);
            if ($sent) {
                Response::success(null, 'Receipt emailed successfully.');
            } else {
                Response::error('Failed to send receipt email. Please check mail settings.', 500);
            }
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 422, $e->getErrors());
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Client Ledger.
     */
    public function apiClientLedger(array $params = []): void
    {
        $clientId = (int)($params['id'] ?? 0);
        try {
            $ledger = $this->paymentService->getClientLedger($clientId);
            Response::success($ledger);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * API: Student Ledger.
     */
    public function apiStudentLedger(array $params = []): void
    {
        $studentId = (int)($params['id'] ?? 0);
        try {
            $ledger = $this->paymentService->getStudentLedger($studentId);
            Response::success($ledger);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
