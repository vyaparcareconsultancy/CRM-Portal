<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Core\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

class EmailProvider implements MessageProviderInterface
{
    private string $host;
    private int $port;
    private ?string $username;
    private ?string $password;
    private ?string $encryption;
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $this->host = trim((string)($_ENV['MAIL_HOST'] ?? ''));
        $this->port = (int)($_ENV['MAIL_PORT'] ?? 587);
        $this->username = isset($_ENV['MAIL_USERNAME']) && $_ENV['MAIL_USERNAME'] !== 'null' && trim((string)$_ENV['MAIL_USERNAME']) !== ''
            ? trim((string)$_ENV['MAIL_USERNAME'])
            : null;
        $this->password = isset($_ENV['MAIL_PASSWORD']) && $_ENV['MAIL_PASSWORD'] !== 'null'
            ? (string)$_ENV['MAIL_PASSWORD']
            : null;
        $this->encryption = isset($_ENV['MAIL_ENCRYPTION']) && $_ENV['MAIL_ENCRYPTION'] !== 'null'
            ? strtolower(trim((string)$_ENV['MAIL_ENCRYPTION']))
            : null;
        $this->fromAddress = trim((string)($_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@crm.local'));
        $this->fromName = trim((string)($_ENV['MAIL_FROM_NAME'] ?? 'CRM Portal'));
    }

    public function isConfigured(): bool
    {
        return !empty($this->host)
            && $this->host !== 'null'
            && !empty($this->username)
            && !empty($this->fromAddress)
            && class_exists(PHPMailer::class);
    }

    public function getName(): string
    {
        return 'Email (PHPMailer SMTP)';
    }

    public function getChannel(): string
    {
        return 'email';
    }

    public function getStatusDescription(): string
    {
        if (!class_exists(PHPMailer::class)) {
            return 'Disabled: PHPMailer library not installed';
        }
        if (empty($this->host) || $this->host === 'null') {
            return 'Disabled: MAIL_HOST not configured in .env';
        }
        if (empty($this->username)) {
            return 'Disabled: MAIL_USERNAME not configured in .env';
        }

        return 'Active: SMTP connected (' . htmlspecialchars($this->host) . ':' . $this->port . ')';
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

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;

            if ($this->username !== null) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = $this->password ?? '';
            } else {
                $mail->SMTPAuth = false;
            }

            if ($this->encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->setFrom($this->fromAddress, $this->fromName);
            $recipientName = (string)($meta['recipient_name'] ?? '');
            $mail->addAddress($to, $recipientName);

            $mail->isHTML(true);
            $mail->Subject = $subject ?: 'Notification from ' . $this->fromName;

            // Format HTML body if plain text is provided
            if (!str_contains($message, '<html') && !str_contains($message, '<div') && !str_contains($message, '<p>')) {
                $mail->Body = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
            } else {
                $mail->Body = $message;
            }
            $mail->AltBody = strip_tags($message);

            // Handle Attachments (receipts, documents)
            foreach ($attachments as $att) {
                if (!empty($att['content'])) {
                    // In-memory string attachment (e.g. PDF receipt generated in memory)
                    $attName = $att['name'] ?? 'attachment.pdf';
                    $mime = $att['mime'] ?? 'application/pdf';
                    $mail->addStringAttachment((string)$att['content'], $attName, 'base64', $mime);
                } elseif (!empty($att['path']) && file_exists($att['path'])) {
                    // File path attachment (e.g. uploaded document)
                    $attName = $att['name'] ?? basename($att['path']);
                    $mail->addAttachment($att['path'], $attName);
                }
            }

            $mail->send();
            $msgId = $mail->getLastMessageID() ?: ('smtp_' . bin2hex(random_bytes(8)));

            return [
                'success' => true,
                'message_id' => $msgId,
                'error' => null,
                'raw_response' => 'Sent via SMTP to ' . $to,
            ];
        } catch (Throwable $e) {
            Logger::error("EmailProvider error sending to {$to}: " . $e->getMessage());
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'raw_response' => null,
            ];
        }
    }
}
