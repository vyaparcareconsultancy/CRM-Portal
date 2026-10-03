<?php

declare(strict_types=1);

namespace App\Services\Messaging;

interface MessageProviderInterface
{
    /**
     * Check if this provider has credentials configured in .env.
     */
    public function isConfigured(): bool;

    /**
     * Get the human-readable display name of the provider.
     */
    public function getName(): string;

    /**
     * Get the channel code: 'email', 'whatsapp', or 'sms'.
     */
    public function getChannel(): string;

    /**
     * Get status description (e.g. "Active", "Disabled: Missing WHATSAPP_ACCESS_TOKEN in .env").
     */
    public function getStatusDescription(): string;

    /**
     * Send a message through this provider.
     *
     * @param string $to Recipient (email address or phone number)
     * @param string $message Text content or template body
     * @param string|null $subject Subject line (used primarily for email)
     * @param array<int, array<string, mixed>> $attachments List of attachments: ['path' => string, 'name' => string] or ['content' => string, 'name' => string, 'mime' => string]
     * @param array<string, mixed> $meta Additional parameters (e.g. template_name, dlt_template_id, params)
     * @return array{success: bool, message_id: ?string, error: ?string, raw_response: ?string}
     */
    public function send(
        string $to,
        string $message,
        ?string $subject = null,
        array $attachments = [],
        array $meta = []
    ): array;
}
