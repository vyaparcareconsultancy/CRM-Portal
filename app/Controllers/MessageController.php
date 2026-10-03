<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Helpers\Csrf;
use App\Models\ChannelOptOut;
use App\Models\Document;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\Messaging\MessageService;
use App\Services\PermissionService;
use Throwable;

class MessageController
{
    private MessageService $messageService;
    private MessageTemplate $templateModel;
    private MessageLog $logModel;
    private ChannelOptOut $optOutModel;

    public function __construct(
        ?MessageService $messageService = null,
        ?MessageTemplate $templateModel = null,
        ?MessageLog $logModel = null,
        ?ChannelOptOut $optOutModel = null
    ) {
        $this->messageService = $messageService ?? new MessageService();
        $this->templateModel = $templateModel ?? new MessageTemplate();
        $this->logModel = $logModel ?? new MessageLog();
        $this->optOutModel = $optOutModel ?? new ChannelOptOut();
    }

    /**
     * Web Page: Messaging Center (/messages)
     */
    public function index(): void
    {
        Session::start();
        $this->ensureCanSend();

        $providerStatuses = $this->messageService->getProviderStatuses();
        $templates = $this->templateModel->getAllTemplates();
        $recentLogs = $this->logModel->getLogs([], 25, 0);
        $optOuts = $this->optOutModel->getOptOuts([]);

        View::render('messages/index', [
            'pageTitle' => 'Omnichannel Messaging Hub',
            'currentPath' => '/messages',
            'providerStatuses' => $providerStatuses,
            'templates' => $templates,
            'recentLogs' => $recentLogs,
            'optOuts' => $optOuts,
            'canManageTemplates' => PermissionService::can('template.manage'),
        ]);
    }

    /**
     * API: Get Provider Configuration Status (GET /api/messages/providers)
     */
    public function apiProviders(): void
    {
        Session::start();
        $this->ensureCanSend();

        Response::json([
            'success' => true,
            'providers' => $this->messageService->getProviderStatuses(),
        ]);
    }

    /**
     * API: Send Single Message (POST /api/messages/send)
     */
    public function apiSend(): void
    {
        Session::start();
        $this->ensureCanSend();

        $data = Request::json() ?: $_POST;
        $channel = strtolower(trim((string)($data['channel'] ?? 'email')));
        $to = trim((string)($data['to'] ?? ''));
        $subject = !empty($data['subject']) ? trim((string)$data['subject']) : null;
        $message = trim((string)($data['message'] ?? ''));
        $templateId = !empty($data['template_id']) ? (int)$data['template_id'] : null;
        $entityType = (string)($data['entity_type'] ?? 'custom');
        $entityId = !empty($data['entity_id']) ? (int)$data['entity_id'] : null;
        $userId = (int)Session::get('user_id');

        if (empty($to)) {
            Response::json(['success' => false, 'error' => 'Recipient address or mobile number is required.'], 422);
            return;
        }

        if (empty($message) && empty($templateId)) {
            Response::json(['success' => false, 'error' => 'Message body or template selection is required.'], 422);
            return;
        }

        // Attach documents if requested (for email channel)
        $attachments = [];
        if ($channel === 'email' && !empty($data['document_ids']) && is_array($data['document_ids'])) {
            $docModel = new Document();
            foreach ($data['document_ids'] as $docId) {
                $doc = $docModel->find((int)$docId);
                if ($doc && !empty($doc['file_path'])) {
                    $storagePath = dirname(__DIR__, 2) . '/' . ltrim((string)$doc['file_path'], '/');
                    if (file_exists($storagePath)) {
                        $attachments[] = [
                            'path' => $storagePath,
                            'name' => $doc['original_name'] ?: basename($storagePath),
                        ];
                    }
                }
            }
        }

        try {
            if ($templateId > 0) {
                $variables = (array)($data['variables'] ?? []);
                $result = $this->messageService->sendTemplate(
                    $templateId,
                    $to,
                    $variables,
                    $channel,
                    $attachments,
                    [
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                    ],
                    $userId
                );
            } else {
                $result = $this->messageService->send(
                    $channel,
                    $to,
                    $message,
                    $subject,
                    $attachments,
                    [
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                    ],
                    $userId
                );
            }

            Response::json($result, $result['success'] ? 200 : 400);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Bulk Broadcast (POST /api/messages/broadcast)
     */
    public function apiBroadcast(): void
    {
        Session::start();
        $this->ensureCanSend();

        $data = Request::json() ?: $_POST;
        $channel = strtolower(trim((string)($data['channel'] ?? 'email')));
        $targetGroup = strtolower(trim((string)($data['target_group'] ?? 'clients')));
        $groupFilters = (array)($data['filters'] ?? []);
        $templateId = !empty($data['template_id']) ? (int)$data['template_id'] : null;
        $subject = !empty($data['subject']) ? trim((string)$data['subject']) : null;
        $messageBody = trim((string)($data['message'] ?? ''));
        $rateLimit = max(1, (int)($data['rate_limit_per_minute'] ?? 60));
        $userId = (int)Session::get('user_id');

        if (empty($messageBody) && empty($templateId)) {
            Response::json(['success' => false, 'error' => 'Message body or template is required for broadcast.'], 422);
            return;
        }

        // If template selected and message body empty, extract body from template
        if ($templateId && empty($messageBody)) {
            $tpl = $this->templateModel->find($templateId);
            if ($tpl) {
                $messageBody = (string)$tpl['body_template'];
                if (!$subject && !empty($tpl['subject'])) {
                    $subject = (string)$tpl['subject'];
                }
            }
        }

        try {
            $result = $this->messageService->broadcast(
                $channel,
                $targetGroup,
                $groupFilters,
                $templateId,
                $subject,
                $messageBody,
                $rateLimit,
                $userId
            );

            Response::json(array_merge(['success' => true], $result));
        } catch (Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: List Message Logs (GET /api/message-logs)
     */
    public function apiLogs(): void
    {
        Session::start();
        $this->ensureCanSend();

        $filters = [
            'channel' => !empty($_GET['channel']) ? trim((string)$_GET['channel']) : null,
            'status' => !empty($_GET['status']) ? trim((string)$_GET['status']) : null,
            'search' => !empty($_GET['search']) ? trim((string)$_GET['search']) : null,
            'entity_type' => !empty($_GET['entity_type']) ? trim((string)$_GET['entity_type']) : null,
            'entity_id' => !empty($_GET['entity_id']) ? (int)$_GET['entity_id'] : null,
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $logs = $this->logModel->getLogs($filters, $limit, $offset);
        $total = $this->logModel->countLogs($filters);

        Response::json([
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int)ceil($total / $limit),
            ],
        ]);
    }

    /**
     * API: Manage Templates (GET /api/message-templates)
     */
    public function apiListTemplates(): void
    {
        Session::start();
        $this->ensureCanSend();

        $templates = $this->templateModel->getAllTemplates();
        Response::json(['success' => true, 'templates' => $templates]);
    }

    /**
     * API: Create Template (POST /api/message-templates)
     */
    public function apiCreateTemplate(): void
    {
        Session::start();
        $this->ensureCanManageTemplates();

        $data = Request::json() ?: $_POST;
        if (empty($data['code']) || empty($data['name']) || empty($data['body_template'])) {
            Response::json(['success' => false, 'error' => 'Code, Name, and Body Template are required.'], 422);
            return;
        }

        try {
            $id = $this->templateModel->createTemplate($data);
            Response::json(['success' => true, 'id' => $id, 'message' => 'Template created successfully.']);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * API: Update Template (POST /api/message-templates/{id})
     */
    public function apiUpdateTemplate(int $id): void
    {
        Session::start();
        $this->ensureCanManageTemplates();

        $data = Request::json() ?: $_POST;
        try {
            $updated = $this->templateModel->updateTemplate($id, $data);
            Response::json(['success' => $updated, 'message' => 'Template updated.']);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * API: Delete Template (POST /api/message-templates/{id}/delete)
     */
    public function apiDeleteTemplate(int $id): void
    {
        Session::start();
        $this->ensureCanManageTemplates();

        try {
            $deleted = $this->templateModel->delete($id);
            Response::json(['success' => $deleted, 'message' => 'Template deleted.']);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * API: List Opt-Outs (GET /api/messages/opt-outs)
     */
    public function apiListOptOuts(): void
    {
        Session::start();
        $this->ensureCanSend();

        $optOuts = $this->optOutModel->getOptOuts([
            'channel' => !empty($_GET['channel']) ? trim((string)$_GET['channel']) : null,
            'identifier' => !empty($_GET['identifier']) ? trim((string)$_GET['identifier']) : null,
        ]);

        Response::json(['success' => true, 'opt_outs' => $optOuts]);
    }

    /**
     * API: Toggle Opt-Out / Opt-In (POST /api/messages/opt-out)
     */
    public function apiToggleOptOut(): void
    {
        Session::start();
        $this->ensureCanSend();

        $data = Request::json() ?: $_POST;
        $channel = strtolower(trim((string)($data['channel'] ?? 'all')));
        $identifier = trim((string)($data['identifier'] ?? ''));
        $action = strtolower(trim((string)($data['action'] ?? 'opt_out'))); // 'opt_out' or 'opt_in'

        if (empty($identifier)) {
            Response::json(['success' => false, 'error' => 'Contact identifier (email or phone) is required.'], 422);
            return;
        }

        if ($action === 'opt_in') {
            $this->optOutModel->optIn($channel, $identifier);
            Response::json(['success' => true, 'message' => "Contact {$identifier} opted back into {$channel} messaging."]);
            return;
        }

        $reason = !empty($data['reason']) ? trim((string)$data['reason']) : 'Opted out via admin portal';
        $entityType = (string)($data['entity_type'] ?? 'other');
        $entityId = !empty($data['entity_id']) ? (int)$data['entity_id'] : null;

        $this->optOutModel->optOut($channel, $identifier, $entityType, $entityId, $reason);

        Response::json(['success' => true, 'message' => "Contact {$identifier} has been opted out from {$channel} messaging."]);
    }

    private function ensureCanSend(): void
    {
        if (!PermissionService::can('message.send') && !PermissionService::can('lead.manage') && !PermissionService::can('client.manage')) {
            Response::json(['success' => false, 'error' => 'Access denied: message.send permission required.'], 403);
            exit;
        }
    }

    private function ensureCanManageTemplates(): void
    {
        if (!PermissionService::can('template.manage')) {
            Response::json(['success' => false, 'error' => 'Access denied: template.manage permission required.'], 403);
            exit;
        }
    }
}
