<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Helpers\Csrf;
use App\Services\PermissionService;
use App\Services\ReminderService;
use Throwable;

class ReminderController
{
    private ReminderService $reminderService;

    public function __construct(?ReminderService $reminderService = null)
    {
        $this->reminderService = $reminderService ?? new ReminderService();
    }

    /**
     * Web Page: Reminders & Deadlines (/reminders)
     */
    public function index(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('role', 'counselor');

        View::render('reminders/index', [
            'pageTitle' => 'Reminders & Compliance Deadlines',
            'currentPath' => '/reminders',
            'canManage' => PermissionService::can('reminder.manage'),
            'userRole' => $userRole,
            'userId' => $userId,
        ]);
    }

    /**
     * API: List Reminders by Tab (GET /api/reminders)
     */
    public function apiListReminders(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = (string)Session::get('role', 'counselor');

        $tab = strtolower(trim((string)($_GET['tab'] ?? 'today')));
        if (!in_array($tab, ['today', 'upcoming', 'overdue', 'done'], true)) {
            $tab = 'today';
        }

        try {
            $reminders = $this->reminderService->getReminders($tab, $userId, $userRole);
            $counts = $this->reminderService->getCounts($userId, $userRole);

            Response::success([
                'tab' => $tab,
                'reminders' => $reminders,
                'counts' => $counts,
            ]);
        } catch (Throwable $e) {
            Response::error('Failed to load reminders: ' . $e->getMessage(), 500);
        }
    }

    /**
     * API: Mark Reminder as Done (POST /api/reminders/{id}/done)
     */
    public function apiMarkDone(int|string $id): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        if ($userId <= 0) {
            Response::error('Unauthenticated', 401);
            return;
        }

        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true) ?: [];
        $note = !empty($jsonData['note']) ? trim((string)$jsonData['note']) : (!empty($_POST['note']) ? trim((string)$_POST['note']) : null);

        try {
            $this->reminderService->markDone((int)$id, $userId, $note);
            Response::success(null, 'Reminder marked as completed.');
        } catch (Throwable $e) {
            Response::error('Failed to update reminder: ' . $e->getMessage(), 400);
        }
    }

    /**
     * API: List Reminder Rules (GET /api/reminder-rules)
     */
    public function apiListRules(): void
    {
        Session::start();
        if (!PermissionService::can('reminder.manage') && !PermissionService::can('reminder.view')) {
            Response::error('Forbidden: insufficient permissions', 403);
            return;
        }

        try {
            $rules = $this->reminderService->getRules();
            Response::success($rules);
        } catch (Throwable $e) {
            Response::error('Failed to load reminder rules: ' . $e->getMessage(), 500);
        }
    }

    /**
     * API: Create Reminder Rule (POST /api/reminder-rules)
     */
    public function apiCreateRule(): void
    {
        Session::start();
        if (!PermissionService::can('reminder.manage')) {
            Response::error('Forbidden: admin permissions required to manage reminder rules', 403);
            return;
        }

        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $userId = (int)Session::get('user_id');
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?: $_POST;

        try {
            $ruleId = $this->reminderService->createRule($data, $userId);
            Response::success(['id' => $ruleId], 'Reminder rule created successfully.', 201);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 422);
        }
    }

    /**
     * API: Update Reminder Rule (PUT /api/reminder-rules/{id})
     */
    public function apiUpdateRule(int|string $id): void
    {
        Session::start();
        if (!PermissionService::can('reminder.manage')) {
            Response::error('Forbidden: admin permissions required to manage reminder rules', 403);
            return;
        }

        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?: $_POST;

        try {
            $this->reminderService->updateRule((int)$id, $data);
            Response::success(null, 'Reminder rule updated successfully.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 422);
        }
    }

    /**
     * API: Toggle Reminder Rule Active State (POST /api/reminder-rules/{id}/toggle)
     */
    public function apiToggleRule(int|string $id): void
    {
        Session::start();
        if (!PermissionService::can('reminder.manage')) {
            Response::error('Forbidden', 403);
            return;
        }

        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?: $_POST;
        $isActive = !empty($data['is_active']);

        try {
            $this->reminderService->toggleRule((int)$id, $isActive);
            Response::success(null, 'Reminder rule status updated.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * API: Delete Reminder Rule (DELETE /api/reminder-rules/{id})
     */
    public function apiDeleteRule(int|string $id): void
    {
        Session::start();
        if (!PermissionService::can('reminder.manage')) {
            Response::error('Forbidden', 403);
            return;
        }

        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        try {
            $this->reminderService->deleteRule((int)$id);
            Response::success(null, 'Reminder rule deleted.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * API: Generate Reminders On-Demand (POST /api/reminders/generate-now)
     */
    public function apiGenerateNow(): void
    {
        Session::start();
        if (!PermissionService::can('reminder.manage')) {
            Response::error('Forbidden', 403);
            return;
        }

        $request = Request::createFromGlobals();
        if (!Csrf::validateRequest($request)) {
            Response::error('Invalid or expired CSRF token', 403);
            return;
        }

        try {
            $gen = $this->reminderService->generateReminders();
            $disp = $this->reminderService->dispatchPendingReminders();

            Response::success([
                'generated' => $gen['generated'],
                'dispatched' => $disp['dispatched'],
                'in_app' => $disp['in_app'],
                'emails' => $disp['emails'],
            ], "Generator ran: {$gen['generated']} reminders generated, {$disp['dispatched']} dispatched.");
        } catch (Throwable $e) {
            Response::error('Failed to run generator: ' . $e->getMessage(), 500);
        }
    }
}
