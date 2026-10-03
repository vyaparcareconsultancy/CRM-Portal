<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Models\ChannelOptOut;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\JobQueue;
use App\Services\Messaging\EmailProvider;
use App\Services\Messaging\MessageProviderInterface;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\SmsProvider;
use App\Services\Messaging\WhatsAppProvider;
use PDO;
use PHPUnit\Framework\TestCase;

class MessagingTest extends TestCase
{
    private PDO $pdo;
    private MessageService $service;
    private MessageTemplate $templateModel;
    private MessageLog $logModel;
    private ChannelOptOut $optOutModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Database::getConnection();
        $this->templateModel = new MessageTemplate($this->pdo);
        $this->logModel = new MessageLog($this->pdo);
        $this->optOutModel = new ChannelOptOut($this->pdo);
        $this->service = new MessageService($this->templateModel, $this->logModel, $this->optOutModel, null, $this->pdo);
    }

    public function testProvidersImplementInterface(): void
    {
        $email = new EmailProvider();
        $wa = new WhatsAppProvider();
        $sms = new SmsProvider();

        $this->assertInstanceOf(MessageProviderInterface::class, $email);
        $this->assertInstanceOf(MessageProviderInterface::class, $wa);
        $this->assertInstanceOf(MessageProviderInterface::class, $sms);

        $statuses = $this->service->getProviderStatuses();
        $this->assertArrayHasKey('email', $statuses);
        $this->assertArrayHasKey('whatsapp', $statuses);
        $this->assertArrayHasKey('sms', $statuses);
    }

    public function testUnconfiguredProviderFailsWithDescriptiveError(): void
    {
        // Unconfigured SMS provider
        $sms = new SmsProvider();
        if (!$sms->isConfigured()) {
            $desc = $sms->getStatusDescription();
            $this->assertStringContainsString('Disabled', $desc);

            $res = $sms->send('919876543210', 'Test');
            $this->assertFalse($res['success']);
            $this->assertStringContainsString('Disabled', $res['error']);
        }
    }

    public function testSeededMessageTemplatesAndVariableInterpolation(): void
    {
        $templates = ['TPL_ADMISSION_CONFIRM', 'TPL_PAYMENT_RECEIVED', 'TPL_PAYMENT_REMINDER', 'TPL_FOLLOW_UP', 'TPL_BIRTHDAY_WISH', 'TPL_BROADCAST_NOTICE'];
        foreach ($templates as $code) {
            $tpl = $this->templateModel->findByCode($code);
            $this->assertNotNull($tpl, "Template {$code} should exist");
        }

        $interpolated = $this->service->interpolate(
            "Dear {name}, fee for {course} is INR {amount}. Due: {due_date}. Receipt: {receipt_no}",
            [
                'name' => 'Amit Kumar',
                'course' => 'Tally Prime Expert',
                'amount' => '10,000.00',
                'due_date' => '2026-11-01',
                'receipt_no' => 'REC-1002',
            ]
        );

        $this->assertStringContainsString('Amit Kumar', $interpolated);
        $this->assertStringContainsString('Tally Prime Expert', $interpolated);
        $this->assertStringContainsString('10,000.00', $interpolated);
        $this->assertStringContainsString('2026-11-01', $interpolated);
        $this->assertStringContainsString('REC-1002', $interpolated);
    }

    public function testOptOutEnforcementNeverMessagesOptedOutContacts(): void
    {
        $testMobile = '919111223344';
        $this->optOutModel->optIn('all', $testMobile);

        $this->assertFalse($this->optOutModel->isOptedOut('whatsapp', $testMobile));

        // Opt out
        $this->optOutModel->optOut('whatsapp', $testMobile, 'student', null, 'Unsubscribed');
        $this->assertTrue($this->optOutModel->isOptedOut('whatsapp', $testMobile));

        // Attempt dispatch
        $result = $this->service->send('whatsapp', $testMobile, 'Test opt out body');
        $this->assertFalse($result['success']);
        $this->assertEquals('opted_out', $result['status']);
        $this->assertStringContainsString('opted out', strtolower($result['error']));

        // Verify audit log
        $log = $this->logModel->find($result['log_id']);
        $this->assertEquals('opted_out', $log['status']);

        // Clean up
        $this->optOutModel->optIn('all', $testMobile);
    }

    public function testMessageLogAuditTrail(): void
    {
        $recipient = 'audit_phpunit_' . bin2hex(random_bytes(3)) . '@example.com';
        $logId = $this->logModel->logMessage([
            'channel' => 'email',
            'recipient' => $recipient,
            'subject' => 'PHPUnit Test Subject',
            'message_body' => 'Testing logs',
            'status' => 'queued',
        ]);

        $this->assertGreaterThan(0, $logId);

        $this->logModel->updateDeliveryStatus($logId, 'sent', 'test_gw_123', null);
        $log = $this->logModel->find($logId);
        $this->assertEquals('sent', $log['status']);
        $this->assertEquals('test_gw_123', $log['gateway_message_id']);
        $this->assertNotNull($log['sent_at']);
    }

    public function testBulkBroadcastRateLimitingQueue(): void
    {
        $suffix = bin2hex(random_bytes(3));
        $phone = '919' . random_int(100000000, 999999999);
        $this->pdo->exec("INSERT INTO clients (client_code, name, email, mobile, whatsapp_number, status) VALUES ('CLI-BR-{$suffix}', 'Broadcast Client', 'broadcast_{$suffix}@test.local', '{$phone}', '{$phone}', 'active')");
        $clientId = (int)$this->pdo->lastInsertId();

        $broadcastRes = $this->service->broadcast(
            'email',
            'clients',
            ['status' => 'active'],
            null,
            'Broadcast Announcement',
            'Dear {name}, this is a test broadcast.',
            60,
            1
        );

        $this->assertGreaterThanOrEqual(1, $broadcastRes['queued_count']);

        $jq = new JobQueue($this->pdo);
        $job = $jq->pop('broadcast');
        $this->assertNotNull($job);
        $this->assertEquals('App\Services\Messaging\MessageService::processQueuedMessage', $job['handler']);

        $jq->complete((int)$job['id']);
        $this->pdo->exec("DELETE FROM clients WHERE id = {$clientId}");
    }
}
