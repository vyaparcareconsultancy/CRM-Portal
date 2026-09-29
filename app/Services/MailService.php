<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use Throwable;

class MailService
{
    public static function sendPasswordReset(string $toEmail, string $resetLink): bool
    {
        $appDebug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $mailHost = $_ENV['MAIL_HOST'] ?? '';
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@crm.local';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'CRM Portal';

        $isConfigured = !empty($mailHost)
            && $mailHost !== 'null'
            && !empty($_ENV['MAIL_USERNAME'])
            && $_ENV['MAIL_USERNAME'] !== 'null';

        // 1. Try sending via PHPMailer if configured and available
        if ($isConfigured && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                /** @var \PHPMailer\PHPMailer\PHPMailer $mail */
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = (string)$mailHost;
                $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 587);

                if (!empty($_ENV['MAIL_USERNAME']) && $_ENV['MAIL_USERNAME'] !== 'null') {
                    $mail->SMTPAuth = true;
                    $mail->Username = (string)$_ENV['MAIL_USERNAME'];
                    $mail->Password = (string)($_ENV['MAIL_PASSWORD'] ?? '');
                } else {
                    $mail->SMTPAuth = false;
                }

                $encryption = strtolower((string)($_ENV['MAIL_ENCRYPTION'] ?? ''));
                if ($encryption === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($encryption === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }

                $mail->setFrom($fromAddress, $fromName);
                $mail->addAddress($toEmail);
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your CRM Portal Password';
                $mail->Body = "<p>Hello,</p>
                    <p>You requested a password reset. Click the link below to set a new password (valid for 15 minutes):</p>
                    <p><a href=\"{$resetLink}\">{$resetLink}</a></p>
                    <p>If you did not request this, you can safely ignore this email.</p>";
                $mail->AltBody = "Hello,\n\nYou requested a password reset. Visit the following link (valid for 15 minutes):\n{$resetLink}\n\nIf you did not request this, please ignore this email.";

                $mail->send();
                return true;
            } catch (Throwable $e) {
                Logger::error("Failed to send password reset email via PHPMailer: " . $e->getMessage(), [
                    'to' => $toEmail,
                ]);
            }
        }

        // 2. Fallback in local/debug environment: log the reset link
        if ($appDebug || !$isConfigured) {
            Logger::info("[DEV EMAIL] Password reset link for {$toEmail}: {$resetLink}", [
                'recipient' => $toEmail,
                'reset_link' => $resetLink,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Send daily follow-ups summary email to a representative.
     *
     * @param array<int, array> $followUps
     */
    public static function sendDailyFollowUpsSummary(string $toEmail, string $userName, array $followUps): bool
    {
        $appDebug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $mailHost = $_ENV['MAIL_HOST'] ?? '';
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@crm.local';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'CRM Portal';

        $isConfigured = !empty($mailHost)
            && $mailHost !== 'null'
            && !empty($_ENV['MAIL_USERNAME'])
            && $_ENV['MAIL_USERNAME'] !== 'null';

        $count = count($followUps);
        $subject = "Daily Reminder: {$count} Follow-up" . ($count === 1 ? '' : 's') . " Scheduled for Today";

        $rowsHtml = '';
        $rowsText = '';
        foreach ($followUps as $f) {
            $time = date('h:i A', strtotime((string)$f['due_at']));
            $type = strtoupper((string)($f['type'] ?? 'CALL'));
            $client = htmlspecialchars((string)($f['client_name'] ?? 'Client'), ENT_QUOTES, 'UTF-8');
            $mobile = htmlspecialchars((string)($f['client_mobile'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $notes = htmlspecialchars((string)($f['notes'] ?? 'No notes provided'), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= "<tr>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$time}</td>
                <td style='padding: 8px; border: 1px solid #ddd;'><strong>{$type}</strong></td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$client} ({$mobile})</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$notes}</td>
            </tr>";

            $rowsText .= "- [{$time}] {$type} with {$client} ({$mobile}): {$notes}\n";
        }

        $htmlBody = "<p>Hello <strong>" . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>You have <strong>{$count}</strong> follow-up" . ($count === 1 ? '' : 's') . " scheduled for today (" . date('D, d M Y') . "):</p>
            <table style='width: 100%; border-collapse: collapse; font-family: sans-serif; font-size: 14px;'>
                <thead>
                    <tr style='background: #f4f6f9; text-align: left;'>
                        <th style='padding: 8px; border: 1px solid #ddd;'>Time</th>
                        <th style='padding: 8px; border: 1px solid #ddd;'>Type</th>
                        <th style='padding: 8px; border: 1px solid #ddd;'>Client</th>
                        <th style='padding: 8px; border: 1px solid #ddd;'>Notes</th>
                    </tr>
                </thead>
                <tbody>{$rowsHtml}</tbody>
            </table>
            <p style='margin-top: 20px;'>Please log in to the CRM Portal to record outcomes and manage your schedule.</p>";

        $altBody = "Hello {$userName},\n\nYou have {$count} follow-up(s) scheduled for today (" . date('D, d M Y') . "):\n\n{$rowsText}\nPlease log in to the CRM Portal to update their statuses.\n";

        if ($isConfigured && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = (string)$mailHost;
                $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 587);

                if (!empty($_ENV['MAIL_USERNAME']) && $_ENV['MAIL_USERNAME'] !== 'null') {
                    $mail->SMTPAuth = true;
                    $mail->Username = (string)$_ENV['MAIL_USERNAME'];
                    $mail->Password = (string)($_ENV['MAIL_PASSWORD'] ?? '');
                } else {
                    $mail->SMTPAuth = false;
                }

                $encryption = strtolower((string)($_ENV['MAIL_ENCRYPTION'] ?? ''));
                if ($encryption === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($encryption === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }

                $mail->setFrom($fromAddress, $fromName);
                $mail->addAddress($toEmail, $userName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $htmlBody;
                $mail->AltBody = $altBody;

                $mail->send();
                return true;
            } catch (Throwable $e) {
                Logger::error("Failed to send daily follow-up email to {$toEmail}: " . $e->getMessage());
            }
        }

        if ($appDebug || !$isConfigured) {
            Logger::info("[DEV EMAIL] Daily follow-ups sent to {$toEmail} ({$count} items)", [
                'recipient' => $toEmail,
                'count' => $count,
                'summary' => $altBody,
            ]);
            return true;
        }

        return false;
    }
}
