<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Core\Logger;
use Throwable;

class WhatsAppProvider implements MessageProviderInterface
{
    private string $phoneNumberId;
    private string $accessToken;
    private string $apiVersion;
    private ?string $businessAccountId;

    public function __construct()
    {
        $this->phoneNumberId = trim((string)($_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? ''));
        $this->accessToken = trim((string)($_ENV['WHATSAPP_ACCESS_TOKEN'] ?? ''));
        $this->apiVersion = trim((string)($_ENV['WHATSAPP_API_VERSION'] ?? 'v18.0'));
        $this->businessAccountId = !empty($_ENV['WHATSAPP_BUSINESS_ACCOUNT_ID'])
            ? trim((string)$_ENV['WHATSAPP_BUSINESS_ACCOUNT_ID'])
            : null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId)
            && $this->phoneNumberId !== 'null'
            && !empty($this->accessToken)
            && $this->accessToken !== 'null';
    }

    public function getName(): string
    {
        return 'WhatsApp (Meta Cloud API)';
    }

    public function getChannel(): string
    {
        return 'whatsapp';
    }

    public function getStatusDescription(): string
    {
        if (empty($this->phoneNumberId) || $this->phoneNumberId === 'null') {
            return 'Disabled: WHATSAPP_PHONE_NUMBER_ID not configured in .env';
        }
        if (empty($this->accessToken) || $this->accessToken === 'null') {
            return 'Disabled: WHATSAPP_ACCESS_TOKEN not configured in .env';
        }

        return 'Active: Meta WhatsApp Cloud API (' . htmlspecialchars($this->phoneNumberId) . ')';
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

        $recipientPhone = $this->formatPhoneNumber($to);
        $endpoint = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        // Build Payload: Template message or text message
        if (!empty($meta['whatsapp_template_name'])) {
            $templateName = (string)$meta['whatsapp_template_name'];
            $langCode = (string)($meta['language_code'] ?? 'en');
            $params = (array)($meta['template_params'] ?? []);

            $bodyComponents = [];
            foreach ($params as $paramValue) {
                $bodyComponents[] = [
                    'type' => 'text',
                    'text' => (string)$paramValue,
                ];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipientPhone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $langCode],
                    'components' => !empty($bodyComponents) ? [
                        [
                            'type' => 'body',
                            'parameters' => $bodyComponents,
                        ]
                    ] : [],
                ],
            ];
        } else {
            // Standard text message (useful for ongoing conversations / notifications)
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipientPhone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ];
        }

        try {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Content-Type: application/json',
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

            if ($httpCode >= 200 && $httpCode < 300 && !empty($json['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => (string)$json['messages'][0]['id'],
                    'error' => null,
                    'raw_response' => (string)$response,
                ];
            }

            $errorMessage = $json['error']['message'] ?? ("HTTP {$httpCode}: " . (string)$response);
            Logger::error("WhatsAppProvider delivery failure to {$to}: {$errorMessage}");

            return [
                'success' => false,
                'message_id' => null,
                'error' => $errorMessage,
                'raw_response' => (string)$response,
            ];
        } catch (Throwable $e) {
            Logger::error("WhatsAppProvider exception for {$to}: " . $e->getMessage());
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'raw_response' => null,
            ];
        }
    }

    /**
     * Normalize and format phone number for WhatsApp Meta Cloud API (E.164 without plus).
     */
    private function formatPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) === 10) {
            // Prepend India country code 91 by default for 10-digit mobile
            return '91' . $digits;
        }

        return $digits;
    }
}
