<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Core\Logger;
use Throwable;

class SmsProvider implements MessageProviderInterface
{
    private string $gateway;
    private string $authKey;
    private string $senderId;
    private ?string $defaultDltTeId;
    private string $route;

    public function __construct()
    {
        $this->gateway = strtolower(trim((string)($_ENV['SMS_GATEWAY'] ?? 'msg91')));
        $this->authKey = trim((string)($_ENV['SMS_AUTH_KEY'] ?? ''));
        $this->senderId = trim((string)($_ENV['SMS_SENDER_ID'] ?? ''));
        $this->defaultDltTeId = !empty($_ENV['SMS_DLT_TE_ID']) ? trim((string)$_ENV['SMS_DLT_TE_ID']) : null;
        $this->route = trim((string)($_ENV['SMS_ROUTE'] ?? '4')); // '4' for transactional in MSG91
    }

    public function isConfigured(): bool
    {
        return !empty($this->authKey)
            && $this->authKey !== 'null'
            && !empty($this->senderId)
            && $this->senderId !== 'null';
    }

    public function getName(): string
    {
        return 'SMS Gateway (MSG91 DLT)';
    }

    public function getChannel(): string
    {
        return 'sms';
    }

    public function getStatusDescription(): string
    {
        if (empty($this->authKey) || $this->authKey === 'null') {
            return 'Disabled: SMS_AUTH_KEY not configured in .env';
        }
        if (empty($this->senderId) || $this->senderId === 'null') {
            return 'Disabled: SMS_SENDER_ID (DLT Header) not configured in .env';
        }

        return 'Active: ' . strtoupper($this->gateway) . ' DLT Gateway (Header: ' . htmlspecialchars($this->senderId) . ')';
    }

    public function send(
        string $to,
        string $message,
        ?string $subject = null,
        array $attachments = [],
        array $meta = []
    ): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => $this->getStatusDescription(),
                'raw_response' => null,
            ];
        }

        $recipientMobile = $this->formatPhoneNumber($to);
        $dltTemplateId = (string)($meta['dlt_template_id'] ?? $this->defaultDltTeId ?? '');

        // Support MSG91 Gateway via API v5 Flow or Send API
        if ($this->gateway === 'msg91') {
            return $this->sendViaMsg91($recipientMobile, $message, $dltTemplateId, $meta);
        }

        // Generic Indian DLT Gateway fallback using HTTP POST
        return $this->sendViaGenericDltGateway($recipientMobile, $message, $dltTemplateId, $meta);
    }

    /**
     * Send via MSG91 API (supports Flow template if DLT template ID is supplied, or transactional SMS).
     */
    private function sendViaMsg91(string $mobile, string $message, string $dltTemplateId, array $meta): array
    {
        $endpoint = "https://api.msg91.com/api/v5/flow/";

        $flowTemplateId = (string)($meta['flow_id'] ?? $meta['dlt_template_id'] ?? $this->defaultDltTeId ?? '');

        if (!empty($flowTemplateId)) {
            // MSG91 Flow API format
            $payload = [
                'template_id' => $flowTemplateId,
                'sender' => $this->senderId,
                'short_url' => '0',
                'recipients' => [
                    array_merge([
                        'mobiles' => $mobile,
                    ], (array)($meta['template_params'] ?? []))
                ],
            ];

            try {
                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'authkey: ' . $this->authKey,
                        'content-type: application/json',
                    ],
                    CURLOPT_TIMEOUT => 15,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($response === false) {
                    throw new \RuntimeException('cURL error: ' . $curlError);
                }

                $json = json_decode((string)$response, true);
                if ($httpCode >= 200 && $httpCode < 300 && (isset($json['type']) && $json['type'] === 'success')) {
                    $msgId = $json['message'] ?? ('msg91_' . bin2hex(random_bytes(6)));
                    return [
                        'success' => true,
                        'message_id' => (string)$msgId,
                        'error' => null,
                        'raw_response' => (string)$response,
                    ];
                }

                $errMsg = $json['message'] ?? ("HTTP {$httpCode}: " . (string)$response);
                Logger::error("SmsProvider (MSG91 Flow) failed for {$mobile}: {$errMsg}");
                return [
                    'success' => false,
                    'message_id' => null,
                    'error' => $errMsg,
                    'raw_response' => (string)$response,
                ];
            } catch (Throwable $e) {
                Logger::error("SmsProvider (MSG91 Flow) exception for {$mobile}: " . $e->getMessage());
                return [
                    'success' => false,
                    'message_id' => null,
                    'error' => $e->getMessage(),
                    'raw_response' => null,
                ];
            }
        }

        // Fallback to MSG91 classic Send SMS API with DLT_TE_ID
        $classicUrl = "https://api.msg91.com/api/sendhttp.php";
        $params = [
            'authkey' => $this->authKey,
            'mobiles' => $mobile,
            'message' => $message,
            'sender' => $this->senderId,
            'route' => $this->route,
            'country' => '91',
        ];
        if (!empty($dltTemplateId)) {
            $params['DLT_TE_ID'] = $dltTemplateId;
        }

        try {
            $ch = curl_init($classicUrl . '?' . http_build_query($params));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new \RuntimeException('cURL error: ' . $curlError);
            }

            // MSG91 returns 24-character alphanumeric request ID on success
            $trimmed = trim((string)$response);
            if ($httpCode === 200 && preg_match('/^[a-f0-9]{24}$/i', $trimmed)) {
                return [
                    'success' => true,
                    'message_id' => $trimmed,
                    'error' => null,
                    'raw_response' => $trimmed,
                ];
            }

            // If MSG91 returns error message string
            return [
                'success' => false,
                'message_id' => null,
                'error' => "MSG91 Gateway returned: {$trimmed}",
                'raw_response' => $trimmed,
            ];
        } catch (Throwable $e) {
            Logger::error("SmsProvider (MSG91 Classic) exception for {$mobile}: " . $e->getMessage());
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'raw_response' => null,
            ];
        }
    }

    /**
     * Generic Indian DLT SMS Gateway HTTP integration.
     */
    private function sendViaGenericDltGateway(string $mobile, string $message, string $dltTemplateId, array $meta): array
    {
        $customUrl = trim((string)($_ENV['SMS_GATEWAY_URL'] ?? ''));
        if (empty($customUrl)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'No custom SMS_GATEWAY_URL configured in .env',
                'raw_response' => null,
            ];
        }

        try {
            $ch = curl_init($customUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'apiKey' => $this->authKey,
                    'sender' => $this->senderId,
                    'mobile' => $mobile,
                    'message' => $message,
                    'dlt_template_id' => $dltTemplateId,
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new \RuntimeException('cURL error: ' . $curlError);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'message_id' => 'dlt_' . bin2hex(random_bytes(6)),
                    'error' => null,
                    'raw_response' => (string)$response,
                ];
            }

            return [
                'success' => false,
                'message_id' => null,
                'error' => "HTTP {$httpCode}: " . (string)$response,
                'raw_response' => (string)$response,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'raw_response' => null,
            ];
        }
    }

    /**
     * Format phone number to 10 digits or 12 digits (with 91 prefix).
     */
    private function formatPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        return $digits;
    }
}
