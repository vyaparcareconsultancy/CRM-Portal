<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Models\Note;
use App\Services\DocumentService;
use App\Services\PermissionService;
use App\Services\TimelineService;
use Throwable;

class DocumentTimelineController
{
    private DocumentService $documentService;
    private TimelineService $timelineService;

    public function __construct(
        ?DocumentService $documentService = null,
        ?TimelineService $timelineService = null
    ) {
        $this->documentService = $documentService ?? new DocumentService();
        $this->timelineService = $timelineService ?? new TimelineService();
    }

    // =========================================================================
    // DOCUMENT ENDPOINTS
    // =========================================================================

    /**
     * Upload document (POST /api/documents/upload)
     */
    public function apiUploadDocument(): void
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

        $file = $_FILES['document'] ?? $_FILES['file'] ?? null;
        if (!$file || empty($file['tmp_name'])) {
            Response::error('No document file was uploaded.', 422);
            return;
        }

        $meta = [
            'entity_type' => $_POST['entity_type'] ?? 'client',
            'entity_id' => (int)($_POST['entity_id'] ?? 0),
            'document_type' => $_POST['document_type'] ?? 'other',
            'title' => $_POST['title'] ?? null,
            'document_number' => $_POST['document_number'] ?? null,
            'financial_year' => $_POST['financial_year'] ?? null,
            'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        ];

        try {
            $doc = $this->documentService->uploadDocument($meta, $file, $userId);
            Response::success($doc, 'Document uploaded successfully.', 201);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Download or view document (GET /api/documents/{id}/download)
     * Strictly verifies role: Aadhaar only Admin/Accountant; Trainer only assigned students.
     *
     * @param array<string, string> $params
     */
    public function downloadDocument(array $params = []): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = PermissionService::getRole();

        if ($userId <= 0) {
            Response::error('Unauthenticated', 401);
            return;
        }

        $docId = (int)($params['id'] ?? $_GET['id'] ?? 0);
        if ($docId <= 0) {
            Response::error('Document ID is required.', 404);
            return;
        }

        try {
            $result = $this->documentService->downloadDocument($docId, $userId, $userRole);

            $mimeType = $result['mime_type'] ?: 'application/octet-stream';
            $fileName = $result['original_name'];
            $content = $result['content'];

            if (!headers_sent()) {
                http_response_code(200);
                header("Content-Type: {$mimeType}");
                header('Content-Disposition: inline; filename="' . addcslashes($fileName, '"\\') . '"');
                header('Content-Length: ' . strlen($content));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
            }

            echo $content;
            exit;
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * List documents for entity (GET /api/documents)
     */
    public function apiListDocuments(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = PermissionService::getRole();

        $entityType = $_GET['entity_type'] ?? 'client';
        $entityId = (int)($_GET['entity_id'] ?? 0);

        try {
            $docs = $this->documentService->listDocuments($entityType, $entityId, $userId, $userRole);
            Response::success($docs);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Delete document (DELETE /api/documents/{id})
     *
     * @param array<string, string> $params
     */
    public function apiDeleteDocument(array $params = []): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $userRole = PermissionService::getRole();

        $docId = (int)($params['id'] ?? 0);
        try {
            $this->documentService->deleteDocument($docId, $userId, $userRole);
            Response::success(null, 'Document deleted successfully.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    // =========================================================================
    // UNIFIED TIMELINE & NOTES ENDPOINTS
    // =========================================================================

    /**
     * Unified timeline per lead/client/student (GET /api/timeline)
     */
    public function apiGetTimeline(): void
    {
        $entityType = $_GET['entity_type'] ?? 'client';
        $entityId = (int)($_GET['entity_id'] ?? 0);
        $filterType = $_GET['filter'] ?? $_GET['type'] ?? null;

        try {
            $timeline = $this->timelineService->getTimeline($entityType, $entityId, $filterType);
            Response::success($timeline);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Quick Add Note with @mentions (POST /api/timeline/notes)
     */
    public function apiAddNote(): void
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

        $input = $request->body();
        $entityType = $input['entity_type'] ?? $_POST['entity_type'] ?? 'client';
        $entityId = (int)($input['entity_id'] ?? $_POST['entity_id'] ?? 0);
        $note = (string)($input['note'] ?? $_POST['note'] ?? '');
        $isPinned = !empty($input['is_pinned']) || !empty($_POST['is_pinned']);

        try {
            $created = $this->timelineService->addNote($entityType, $entityId, $userId, $note, $isPinned);
            Response::success($created, 'Note added successfully.', 201);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 422;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * Toggle note pin state (POST /api/timeline/notes/{id}/pin)
     *
     * @param array<string, string> $params
     */
    public function apiTogglePinNote(array $params = []): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $id = (int)($params['id'] ?? 0);

        try {
            $this->timelineService->togglePinNote($id, $userId);
            Response::success(null, 'Note pin status updated.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Delete note (DELETE /api/timeline/notes/{id})
     *
     * @param array<string, string> $params
     */
    public function apiDeleteNote(array $params = []): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $id = (int)($params['id'] ?? 0);

        $noteModel = new Note();
        $note = $noteModel->find($id);
        if (!$note) {
            Response::error('Note not found.', 404);
            return;
        }

        $userRole = PermissionService::getRole();
        if ($userRole !== 'admin' && (int)$note['user_id'] !== $userId) {
            Response::error('Forbidden: you can only delete your own notes.', 403);
            return;
        }

        $noteModel->delete($id);
        Response::success(null, 'Note deleted successfully.');
    }

    // =========================================================================
    // IN-APP NOTIFICATIONS ENDPOINTS
    // =========================================================================

    /**
     * Get unread notifications for logged in user (GET /api/notifications)
     */
    public function apiGetNotifications(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        if ($userId <= 0) {
            Response::error('Unauthenticated', 401);
            return;
        }

        $notifications = $this->timelineService->getUnreadNotifications($userId);
        Response::success($notifications);
    }

    /**
     * Mark single notification as read (POST /api/notifications/{id}/read)
     *
     * @param array<string, string> $params
     */
    public function apiMarkNotificationRead(array $params = []): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $id = (int)($params['id'] ?? 0);

        $this->timelineService->markNotificationAsRead($id, $userId);
        Response::success(null, 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read (POST /api/notifications/read-all)
     */
    public function apiMarkAllNotificationsRead(): void
    {
        Session::start();
        $userId = (int)Session::get('user_id');
        $this->timelineService->markAllNotificationsAsRead($userId);
        Response::success(null, 'All notifications marked as read.');
    }
}
