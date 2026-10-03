<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Core\Database;
use App\Core\Logger;
use App\Helpers\PdfReceipt;
use App\Models\ActivityLog;
use App\Models\ChannelOptOut;
use App\Models\Client;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\Setting;
use App\Models\Student;
use App\Services\JobQueue;
use PDO;
use RuntimeException;
use Throwable;

class MessageService
{
    private MessageTemplate $templateModel;
    private MessageLog $logModel;
    private ChannelOptOut $optOutModel;
    private ActivityLog $activityLogModel;
    private PDO $pdo;

    /** @var array<string, MessageProviderInterface> */
    private array $providers = [];

    public function __construct(
        ?MessageTemplate $templateModel = null,
        ?MessageLog $logModel = null,
        ?ChannelOptOut $optOutModel = null,
        ?ActivityLog $activityLogModel = null,
        ?PDO $pdo = null
    ) {
        $this->templateModel = $templateModel ?? new MessageTemplate();
        $this->logModel = $logModel ?? new MessageLog();
        $this->optOutModel = $optOutModel ?? new ChannelOptOut();
        $this->activityLogModel = $activityLogModel ?? new ActivityLog();
        $this->pdo = $pdo ?? Database::getConnection();

        // Register default providers
        $this->providers['email'] = new EmailProvider();
        $this->providers['whatsapp'] = new WhatsAppProvider();
        $this->providers['sms'] = new SmsProvider();
    }

    /**
     * Get provider by channel.
     */
    public function getProvider(string $channel): MessageProviderInterface
    {
        $channel = strtolower(trim($channel));
        if (!isset($this->providers[$channel])) {
            throw new RuntimeException("Unsupported messaging channel: {$channel}", 422);
        }

        return $this->providers[$channel];
    }

    /**
     * Register a custom provider (pluggability).
     */
    public function registerProvider(string $channel, MessageProviderInterface $provider): void
    {
        $this->providers[strtolower(trim($channel))] = $provider;
    }

    /**
     * Get status of all configured providers for UI presentation.
     *
     * @return array<string, array{name: string, channel: string, is_configured: bool, status: string}>
     */
    public function getProviderStatuses(): array
    {
        $statuses = [];
        foreach ($this->providers as $channel => $provider) {
            $statuses[$channel] = [
                'name' => $provider->getName(),
                'channel' => $provider->getChannel(),
                'is_configured' => $provider->isConfigured(),
                'status' => $provider->getStatusDescription(),
            ];
        }

        return $statuses;
    }

    /**
     * Check if a contact has opted out of a channel.
     * Invariant: Never message opted-out contacts.
     */
    public function isOptedOut(string $channel, string $identifier): bool
    {
        return $this->optOutModel->isOptedOut($channel, $identifier);
    }

    /**
     * Opt-out a contact for a channel.
     */
    public function optOut(
        string $channel,
        string $identifier,
        string $entityType = 'other',
        ?int $entityId = null,
        ?string $reason = null
    ): bool {
        return $this->optOutModel->optOut($channel, $identifier, $entityType, $entityId, $reason);
    }

    /**
     * Opt-in a contact back.
     */
    public function optIn(string $channel, string $identifier): bool
    {
        return $this->optOutModel->optIn($channel, $identifier);
    }

    /**
     * Interpolate variables into template string.
     *
     * Supported variables: {name}, {course}, {amount}, {due_date}, {receipt_no}, {business_name}, etc.
     */
    public function interpolate(string $template, array $variables): string
    {
        // Add business name automatically if not supplied
        if (!isset($variables['business_name'])) {
            $variables['business_name'] = Setting::get('business_name', 'Vyapar Care Consultancy & Training');
        }

        $replace = [];
        foreach ($variables as $key => $val) {
            $valStr = (string)$val;
            $replace['{' . $key . '}'] = $valStr;
            $replace['{{' . $key . '}}'] = $valStr;
        }

        return strtr($template, $replace);
    }

    /**
     * Core message sending method.
     *
     * Invariant:
     * 1. Never message opted-out contacts.
     * 2. If provider not configured, mark failed and indicate reason.
     * 3. Log all attempts in message_logs.
     *
     * @param string $channel 'email', 'whatsapp', or 'sms'
     * @param string $to Recipient email or phone number
     * @param string $message Text content / message body
     * @param string|null $subject Optional subject line
     * @param array<int, array<string, mixed>> $attachments Files or raw strings
     * @param array<string, mixed> $meta Additional metadata (template_id, entity_type, entity_id, etc.)
     * @param int|null $sentBy User ID who initiated the message
     * @return array{success: bool, status: string, message_id: ?string, error: ?string, log_id: int}
     */
    public function send(
        string $channel,
        string $to,
        string $message,
        ?string $subject = null,
        array $attachments = [],
        array $meta = [],
        ?int $sentBy = null
    ): array {
        $channel = strtolower(trim($channel));
        $to = trim($to);

        $entityType = (string)($meta['entity_type'] ?? 'custom');
        $entityId = !empty($meta['entity_id']) ? (int)$meta['entity_id'] : null;
        $templateId = !empty($meta['template_id']) ? (int)$meta['template_id'] : null;

        // 1. Opt-out check (CONSENT INVARIANT: NEVER MESSAGE OPTED-OUT CONTACTS)
        if ($this->isOptedOut($channel, $to)) {
            $logId = $this->logModel->logMessage([
                'channel' => $channel,
                'template_id' => $templateId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'recipient' => $to,
                'subject' => $subject,
                'message_body' => $message,
                'status' => 'opted_out',
                'error_message' => "Recipient {$to} has opted out of {$channel} messaging.",
                'sent_by' => $sentBy,
            ]);

            return [
                'success' => false,
                'status' => 'opted_out',
                'message_id' => null,
                'error' => "Recipient has opted out of {$channel} communication.",
                'log_id' => $logId,
            ];
        }

        // 2. Validate and retrieve provider
        $provider = $this->getProvider($channel);

        // 3. Provider Configuration check
        if (!$provider->isConfigured()) {
            $statusDesc = $provider->getStatusDescription();
            $logId = $this->logModel->logMessage([
                'channel' => $channel,
                'template_id' => $templateId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'recipient' => $to,
                'subject' => $subject,
                'message_body' => $message,
                'status' => 'failed',
                'error_message' => $statusDesc,
                'sent_by' => $sentBy,
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message_id' => null,
                'error' => $statusDesc,
                'log_id' => $logId,
            ];
        }

        // 4. Initial Log Record in 'queued' state
        $logId = $this->logModel->logMessage([
            'channel' => $channel,
            'template_id' => $templateId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'recipient' => $to,
            'subject' => $subject,
            'message_body' => $message,
            'status' => 'queued',
            'sent_by' => $sentBy,
        ]);

        // 5. Dispatch via Provider
        $result = $provider->send($to, $message, $subject, $attachments, $meta);

        if ($result['success']) {
            $this->logModel->updateDeliveryStatus($logId, 'sent', $result['message_id'], null);

            // Record to Activity Log / Timeline if entity is specified
            if ($entityId && in_array($entityType, ['client', 'student', 'lead'], true)) {
                $this->activityLogModel->create([
                    'user_id' => $sentBy,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'action' => "{$channel}_sent",
                    'new_values' => json_encode([
                        'channel' => $channel,
                        'recipient' => $to,
                        'message_id' => $result['message_id'],
                        'log_id' => $logId,
                        'message' => ($subject ?: substr($message, 0, 40) . '...'),
                    ]),
                ]);
            }

            return [
                'success' => true,
                'status' => 'sent',
                'message_id' => $result['message_id'],
                'error' => null,
                'log_id' => $logId,
            ];
        }

        // Dispatch failed
        $this->logModel->updateDeliveryStatus($logId, 'failed', null, $result['error']);

        return [
            'success' => false,
            'status' => 'failed',
            'message_id' => null,
            'error' => $result['error'],
            'log_id' => $logId,
        ];
    }

    /**
     * Send message by resolving and interpolating a MessageTemplate.
     *
     * @param string|int $templateIdentifier Code (e.g. 'TPL_ADMISSION_CONFIRM') or Template ID
     * @param string $to Recipient
     * @param array<string, mixed> $variables Variables for interpolation ({name}, {amount}, etc.)
     * @param string|null $overrideChannel Channel to use (if template channel is 'all' or overridden)
     */
    public function sendTemplate(
        string|int $templateIdentifier,
        string $to,
        array $variables = [],
        ?string $overrideChannel = null,
        array $attachments = [],
        array $meta = [],
        ?int $sentBy = null
    ): array {
        if (is_numeric($templateIdentifier)) {
            $template = $this->templateModel->find((int)$templateIdentifier);
        } else {
            $template = $this->templateModel->findByCode((string)$templateIdentifier);
        }

        if (!$template || empty($template['is_active'])) {
            throw new RuntimeException("Message template '{$templateIdentifier}' not found or inactive.", 404);
        }

        $channel = $overrideChannel ?: ($template['channel'] !== 'all' ? $template['channel'] : 'email');

        $interpolatedBody = $this->interpolate((string)$template['body_template'], $variables);
        $interpolatedSubject = !empty($template['subject'])
            ? $this->interpolate((string)$template['subject'], $variables)
            : null;

        $meta['template_id'] = (int)$template['id'];
        $meta['template_code'] = $template['code'];
        if (!empty($template['dlt_template_id'])) {
            $meta['dlt_template_id'] = $template['dlt_template_id'];
        }
        if (!empty($template['whatsapp_template_name'])) {
            $meta['whatsapp_template_name'] = $template['whatsapp_template_name'];
            $meta['template_params'] = array_values($variables);
        }

        return $this->send(
            $channel,
            $to,
            $interpolatedBody,
            $interpolatedSubject,
            $attachments,
            $meta,
            $sentBy
        );
    }

    /**
     * Automated Event: Admission Confirmed
     * Triggered on new enrollment admission in Training Institute.
     */
    public function onAdmissionConfirmed(int $enrollmentId): array
    {
        $enrollmentModel = new Enrollment();
        $enrollment = $enrollmentModel->findWithDetails($enrollmentId);
        if (!$enrollment) {
            return ['success' => false, 'error' => 'Enrollment not found'];
        }

        $studentName = (string)($enrollment['student_name'] ?? 'Student');
        $courseTitle = (string)($enrollment['course_title'] ?? $enrollment['course_name'] ?? 'Course');
        $amount = number_format((float)($enrollment['agreed_fee'] ?? 0), 2);
        $email = $enrollment['student_email'] ?? null;
        $mobile = $enrollment['student_mobile'] ?? null;
        $whatsapp = $enrollment['student_whatsapp'] ?? $mobile;

        $variables = [
            'name' => $studentName,
            'course' => $courseTitle,
            'amount' => $amount,
            'due_date' => $enrollment['admission_date'] ?? date('Y-m-d'),
            'receipt_no' => $enrollment['enrollment_no'] ?? 'ENR-' . $enrollmentId,
        ];

        $results = [];

        // 1. Send Email if available
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $results['email'] = $this->sendTemplate(
                'TPL_ADMISSION_CONFIRM',
                $email,
                $variables,
                'email',
                [],
                ['entity_type' => 'student', 'entity_id' => (int)$enrollment['student_id']]
            );
        }

        // 2. Send WhatsApp if available
        if (!empty($whatsapp) && $this->getProvider('whatsapp')->isConfigured()) {
            $results['whatsapp'] = $this->sendTemplate(
                'TPL_ADMISSION_CONFIRM',
                $whatsapp,
                $variables,
                'whatsapp',
                [],
                ['entity_type' => 'student', 'entity_id' => (int)$enrollment['student_id']]
            );
        }

        // 3. Send SMS if available
        if (!empty($mobile) && $this->getProvider('sms')->isConfigured()) {
            $results['sms'] = $this->sendTemplate(
                'TPL_ADMISSION_CONFIRM',
                $mobile,
                $variables,
                'sms',
                [],
                ['entity_type' => 'student', 'entity_id' => (int)$enrollment['student_id']]
            );
        }

        return $results;
    }

    /**
     * Automated Event: Payment Received
     * Generates PDF receipt attachment and sends confirmation.
     */
    public function onPaymentReceived(int $paymentId): array
    {
        $paymentModel = new Payment();
        $payment = $paymentModel->findWithDetails($paymentId);
        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found'];
        }

        $invoiceModel = new Invoice();
        $invoice = $invoiceModel->find((int)$payment['invoice_id']);

        $customerName = 'Customer';
        $email = null;
        $phone = null;
        $entityType = 'custom';
        $entityId = null;

        if (!empty($payment['client_id'])) {
            $clientModel = new Client();
            $client = $clientModel->find((int)$payment['client_id']);
            $customerName = $client['name'] ?? 'Client';
            $email = $client['email'] ?? null;
            $phone = $client['whatsapp_number'] ?? $client['mobile'] ?? null;
            $entityType = 'client';
            $entityId = (int)$payment['client_id'];
        } elseif (!empty($payment['student_id'])) {
            $studentModel = new Student();
            $student = $studentModel->find((int)$payment['student_id']);
            $customerName = $student['name'] ?? 'Student';
            $email = $student['email'] ?? null;
            $phone = $student['whatsapp_number'] ?? $student['mobile'] ?? null;
            $entityType = 'student';
            $entityId = (int)$payment['student_id'];
        }

        $receiptNo = (string)($payment['receipt_no'] ?? 'REC-' . $paymentId);
        $amount = number_format((float)($payment['amount'] ?? 0), 2);
        $dueDate = !empty($invoice['due_date']) ? (string)$invoice['due_date'] : date('Y-m-d');

        $variables = [
            'name' => $customerName,
            'amount' => $amount,
            'receipt_no' => $receiptNo,
            'due_date' => $dueDate,
            'course' => $invoice['title'] ?? 'Services',
        ];

        // Generate PDF Receipt Attachment
        $pdfData = '';
        try {
            $company = Setting::getAll();
            $pdfData = PdfReceipt::generate($payment, $invoice ?: [], $company);
        } catch (Throwable $e) {
            Logger::error("Failed to generate PDF receipt for payment #{$paymentId}: " . $e->getMessage());
        }

        $attachments = [];
        if (!empty($pdfData)) {
            $attachments[] = [
                'content' => $pdfData,
                'name' => "Receipt-{$receiptNo}.pdf",
                'mime' => 'application/pdf',
            ];
        }

        $results = [];

        // 1. Email with PDF Receipt Attached
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $results['email'] = $this->sendTemplate(
                'TPL_PAYMENT_RECEIVED',
                $email,
                $variables,
                'email',
                $attachments,
                [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'recipient_name' => $customerName,
                ]
            );
        }

        // 2. WhatsApp Notification
        if (!empty($phone) && $this->getProvider('whatsapp')->isConfigured()) {
            $results['whatsapp'] = $this->sendTemplate(
                'TPL_PAYMENT_RECEIVED',
                $phone,
                $variables,
                'whatsapp',
                [],
                ['entity_type' => $entityType, 'entity_id' => $entityId]
            );
        }

        // 3. SMS Notification
        if (!empty($phone) && $this->getProvider('sms')->isConfigured()) {
            $results['sms'] = $this->sendTemplate(
                'TPL_PAYMENT_RECEIVED',
                $phone,
                $variables,
                'sms',
                [],
                ['entity_type' => $entityType, 'entity_id' => $entityId]
            );
        }

        return $results;
    }

    /**
     * Automated Event: Reminder Triggered (Dispatches to WhatsApp, SMS, Email).
     */
    public function onReminderTriggered(int $reminderId, array $channels = ['email', 'whatsapp', 'sms']): array
    {
        $reminderModel = new Reminder();
        $reminder = $reminderModel->find($reminderId);
        if (!$reminder) {
            return ['success' => false, 'error' => 'Reminder not found'];
        }

        $recipientEmail = null;
        $recipientPhone = null;
        $recipientName = 'Customer';
        $entityType = (string)($reminder['entity_type'] ?? 'custom');
        $entityId = !empty($reminder['entity_id']) ? (int)$reminder['entity_id'] : null;

        if ($entityType === 'client' && $entityId) {
            $clientModel = new Client();
            $client = $clientModel->find($entityId);
            $recipientName = $client['name'] ?? 'Client';
            $recipientEmail = $client['email'] ?? null;
            $recipientPhone = $client['whatsapp_number'] ?? $client['mobile'] ?? null;
        } elseif ($entityType === 'student' && $entityId) {
            $studentModel = new Student();
            $student = $studentModel->find($entityId);
            $recipientName = $student['name'] ?? 'Student';
            $recipientEmail = $student['email'] ?? null;
            $recipientPhone = $student['whatsapp_number'] ?? $student['mobile'] ?? null;
        }

        $variables = [
            'name' => $recipientName,
            'amount' => 'Due',
            'due_date' => $reminder['due_date'] ?? date('Y-m-d'),
            'course' => $reminder['title'] ?? 'Compliance / Service',
            'receipt_no' => 'REM-' . $reminderId,
            'notice_text' => $reminder['description'] ?? $reminder['title'],
        ];

        $results = [];

        foreach ($channels as $ch) {
            $ch = strtolower(trim($ch));
            if ($ch === 'email' && !empty($recipientEmail)) {
                $results['email'] = $this->sendTemplate(
                    'TPL_PAYMENT_REMINDER',
                    $recipientEmail,
                    $variables,
                    'email',
                    [],
                    ['entity_type' => $entityType, 'entity_id' => $entityId]
                );
            } elseif ($ch === 'whatsapp' && !empty($recipientPhone)) {
                $results['whatsapp'] = $this->sendTemplate(
                    'TPL_PAYMENT_REMINDER',
                    $recipientPhone,
                    $variables,
                    'whatsapp',
                    [],
                    ['entity_type' => $entityType, 'entity_id' => $entityId]
                );
            } elseif ($ch === 'sms' && !empty($recipientPhone)) {
                $results['sms'] = $this->sendTemplate(
                    'TPL_PAYMENT_REMINDER',
                    $recipientPhone,
                    $variables,
                    'sms',
                    [],
                    ['entity_type' => $entityType, 'entity_id' => $entityId]
                );
            }
        }

        return $results;
    }

    /**
     * Bulk Broadcast to filtered list via database queue with rate-limiting.
     *
     * @param string $channel 'email', 'whatsapp', or 'sms'
     * @param string $targetGroup 'clients', 'students', or 'leads'
     * @param array<string, mixed> $groupFilters Filters (e.g. status, course_id, batch_id)
     * @param int|null $templateId Optional template ID
     * @param string|null $subject Broadcast subject
     * @param string $messageBody Broadcast body
     * @param int $rateLimitPerMinute Rate limit (default: 60/min = 1 per second)
     * @param int|null $sentBy User ID
     * @return array{queued_count: int, opted_out_count: int, total_targets: int}
     */
    public function broadcast(
        string $channel,
        string $targetGroup,
        array $groupFilters,
        ?int $templateId,
        ?string $subject,
        string $messageBody,
        int $rateLimitPerMinute = 60,
        ?int $sentBy = null
    ): array {
        $targets = $this->resolveBroadcastTargets($targetGroup, $groupFilters, $channel);

        $jobQueue = new JobQueue($this->pdo);
        $delayInterval = max(1, (int)floor(60 / max(1, $rateLimitPerMinute)));
        $currentDelay = 0;

        $queuedCount = 0;
        $optedOutCount = 0;

        foreach ($targets as $target) {
            $to = $target['recipient'];
            if (empty($to)) {
                continue;
            }

            // Check opt-out
            if ($this->isOptedOut($channel, $to)) {
                $optedOutCount++;
                $this->logModel->logMessage([
                    'channel' => $channel,
                    'template_id' => $templateId,
                    'entity_type' => $target['entity_type'],
                    'entity_id' => $target['entity_id'],
                    'recipient' => $to,
                    'subject' => $subject,
                    'message_body' => $this->interpolate($messageBody, ['name' => $target['name']]),
                    'status' => 'opted_out',
                    'error_message' => "Opted out from {$channel}",
                    'sent_by' => $sentBy,
                ]);
                continue;
            }

            // Interpolate target name
            $finalBody = $this->interpolate($messageBody, array_merge($target['variables'] ?? [], [
                'name' => $target['name'],
            ]));
            $finalSubject = $subject ? $this->interpolate($subject, ['name' => $target['name']]) : null;

            // Log as 'queued'
            $logId = $this->logModel->logMessage([
                'channel' => $channel,
                'template_id' => $templateId,
                'entity_type' => $target['entity_type'],
                'entity_id' => $target['entity_id'],
                'recipient' => $to,
                'subject' => $finalSubject,
                'message_body' => $finalBody,
                'status' => 'queued',
                'sent_by' => $sentBy,
            ]);

            // Queue job with rate-limiting delay
            $jobQueue->dispatch(
                'App\Services\Messaging\MessageService::processQueuedMessage',
                ['logId' => $logId],
                'broadcast',
                $currentDelay
            );

            $queuedCount++;
            $currentDelay += $delayInterval;
        }

        return [
            'queued_count' => $queuedCount,
            'opted_out_count' => $optedOutCount,
            'total_targets' => count($targets),
        ];
    }

    /**
     * Process a single queued message from the queue worker.
     */
    public static function processQueuedMessage(int $logId): bool
    {
        $pdo = Database::getConnection();
        $logModel = new MessageLog($pdo);
        $log = $logModel->find($logId);
        if (!$log || $log['status'] !== 'queued') {
            return false;
        }

        $service = new self(null, $logModel, null, null, $pdo);
        $provider = $service->getProvider($log['channel']);

        if (!$provider->isConfigured()) {
            $logModel->updateDeliveryStatus($logId, 'failed', null, $provider->getStatusDescription());
            return false;
        }

        $result = $provider->send(
            $log['recipient'],
            $log['message_body'],
            $log['subject'],
            [],
            [
                'template_id' => $log['template_id'],
                'entity_type' => $log['entity_type'],
                'entity_id' => $log['entity_id'],
            ]
        );

        if ($result['success']) {
            $logModel->updateDeliveryStatus($logId, 'sent', $result['message_id'], null);
            return true;
        }

        $logModel->updateDeliveryStatus($logId, 'failed', null, $result['error']);
        throw new RuntimeException("Broadcast dispatch failed for log #{$logId}: " . $result['error']);
    }

    /**
     * Resolve eligible broadcast target contacts.
     */
    private function resolveBroadcastTargets(string $group, array $filters, string $channel): array
    {
        $group = strtolower(trim($group));
        $targets = [];

        if ($group === 'clients') {
            $sql = "SELECT id, name, email, mobile, whatsapp_number FROM clients WHERE deleted_at IS NULL";
            if (!empty($filters['status'])) {
                $sql .= " AND status = " . $this->pdo->quote($filters['status']);
            }
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recipient = ($channel === 'email')
                    ? $row['email']
                    : ($row['whatsapp_number'] ?: $row['mobile']);
                if (!empty($recipient)) {
                    $targets[] = [
                        'entity_type' => 'client',
                        'entity_id' => (int)$row['id'],
                        'name' => $row['name'],
                        'recipient' => $recipient,
                        'variables' => ['name' => $row['name']],
                    ];
                }
            }
        } elseif ($group === 'students') {
            $sql = "SELECT s.id, s.name, s.email, s.mobile, s.whatsapp_number, e.course_id, e.batch_id, c.name AS course_name
                    FROM students s
                    LEFT JOIN enrollments e ON s.id = e.student_id
                    LEFT JOIN courses c ON e.course_id = c.id
                    WHERE s.deleted_at IS NULL";
            if (!empty($filters['course_id'])) {
                $sql .= " AND e.course_id = " . (int)$filters['course_id'];
            }
            if (!empty($filters['batch_id'])) {
                $sql .= " AND e.batch_id = " . (int)$filters['batch_id'];
            }
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recipient = ($channel === 'email')
                    ? $row['email']
                    : ($row['whatsapp_number'] ?: $row['mobile']);
                if (!empty($recipient)) {
                    $targets[] = [
                        'entity_type' => 'student',
                        'entity_id' => (int)$row['id'],
                        'name' => $row['name'],
                        'recipient' => $recipient,
                        'variables' => [
                            'name' => $row['name'],
                            'course' => $row['course_name'] ?? 'Training Course',
                        ],
                    ];
                }
            }
        } elseif ($group === 'leads') {
            $sql = "SELECT id, name, email, mobile, whatsapp_number FROM leads WHERE deleted_at IS NULL";
            if (!empty($filters['status'])) {
                $sql .= " AND status = " . $this->pdo->quote($filters['status']);
            }
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recipient = ($channel === 'email')
                    ? $row['email']
                    : ($row['whatsapp_number'] ?: $row['mobile']);
                if (!empty($recipient)) {
                    $targets[] = [
                        'entity_type' => 'lead',
                        'entity_id' => (int)$row['id'],
                        'name' => $row['name'],
                        'recipient' => $recipient,
                        'variables' => ['name' => $row['name']],
                    ];
                }
            }
        }

        return $targets;
    }
}
