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

    /**
     * Send payment receipt email with PDF attachment.
     */
    public static function sendPaymentReceipt(
        string $toEmail,
        string $recipientName,
        array $payment,
        array $invoice,
        string $pdfData
    ): bool {
        $appDebug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $mailHost = $_ENV['MAIL_HOST'] ?? '';
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'accounts@crm.local';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Accounts Department';

        $isConfigured = !empty($mailHost)
            && $mailHost !== 'null'
            && !empty($_ENV['MAIL_USERNAME'])
            && $_ENV['MAIL_USERNAME'] !== 'null';

        $receiptNo = $payment['receipt_no'] ?? 'REC-XXXX';
        $amount = number_format((float)($payment['amount'] ?? 0), 2);
        $subject = "Payment Receipt: {$receiptNo} [INR {$amount}]";

        $htmlBody = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #0f2c59; margin-top: 0;'>Payment Receipt Confirmation</h2>
                <p>Dear <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                <p>Thank you for your payment. We have received your payment with the following details:</p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #edf2f7; color: #718096;'>Receipt Number:</td><td style='padding: 8px; border-bottom: 1px solid #edf2f7; font-weight: bold;'>{$receiptNo}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #edf2f7; color: #718096;'>Payment Date:</td><td style='padding: 8px; border-bottom: 1px solid #edf2f7;'>" . htmlspecialchars($payment['payment_date'] ?? date('Y-m-d')) . "</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #edf2f7; color: #718096;'>Payment Mode:</td><td style='padding: 8px; border-bottom: 1px solid #edf2f7; text-transform: uppercase;'>" . htmlspecialchars($payment['payment_mode'] ?? 'cash') . "</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #edf2f7; color: #718096;'>Amount Paid:</td><td style='padding: 8px; border-bottom: 1px solid #edf2f7; font-weight: bold; color: #276749;'>INR {$amount}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #edf2f7; color: #718096;'>Invoice Reference:</td><td style='padding: 8px; border-bottom: 1px solid #edf2f7;'>" . htmlspecialchars($invoice['invoice_no'] ?? 'N/A') . " (" . htmlspecialchars($invoice['title'] ?? '') . ")</td></tr>
                </table>
                <p>Please find your official PDF payment receipt attached to this email.</p>
                <p style='color: #718096; font-size: 13px; margin-top: 30px;'>Best Regards,<br>Accounts & Finance Team</p>
            </div>
        ";

        $altBody = "Dear {$recipientName},\n\nThank you for your payment of INR {$amount} (Receipt: {$receiptNo}) against invoice " . ($invoice['invoice_no'] ?? '') . ".\nYour official PDF receipt is attached.\n\nBest Regards,\nAccounts Team";

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
                $mail->addAddress($toEmail, $recipientName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $htmlBody;
                $mail->AltBody = $altBody;

                if (!empty($pdfData)) {
                    $mail->addStringAttachment($pdfData, "Receipt-{$receiptNo}.pdf", 'base64', 'application/pdf');
                }

                $mail->send();
                return true;
            } catch (Throwable $e) {
                Logger::error("Failed to send payment receipt email to {$toEmail}: " . $e->getMessage());
            }
        }

        if ($appDebug || !$isConfigured) {
            Logger::info("[DEV EMAIL] Payment receipt sent to {$toEmail} (Receipt: {$receiptNo}, Amount: INR {$amount})", [
                'recipient' => $toEmail,
                'receipt_no' => $receiptNo,
                'amount' => $amount,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Send automated reminder notification email.
     */
    public static function sendReminder(
        string $toEmail,
        string $recipientName,
        string $title,
        string $description,
        ?string $dueDate = null
    ): bool {
        $appDebug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $mailHost = $_ENV['MAIL_HOST'] ?? '';
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'notifications@crm.local';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'CRM Reminder Alert';

        $isConfigured = !empty($mailHost)
            && $mailHost !== 'null'
            && !empty($_ENV['MAIL_USERNAME'])
            && $_ENV['MAIL_USERNAME'] !== 'null';

        $subject = "Reminder: {$title}" . ($dueDate ? " [Due: {$dueDate}]" : "");

        $htmlBody = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #0f2c59; margin-top: 0;'>Important Reminder Notice</h2>
                <p>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                <div style='background-color: #f7fafc; padding: 15px; border-left: 4px solid #3182ce; margin: 15px 0;'>
                    <h3 style='margin: 0 0 10px 0; color: #2d3748;'>" . htmlspecialchars($title) . "</h3>
                    <p style='margin: 0; color: #4a5568;'>" . nl2br(htmlspecialchars($description)) . "</p>
                    " . ($dueDate ? "<p style='margin: 10px 0 0 0; font-weight: bold; color: #c53030;'>Due Date: " . htmlspecialchars($dueDate) . "</p>" : "") . "
                </div>
                <p style='color: #718096; font-size: 13px; margin-top: 25px;'>Best Regards,<br>Compliance & Support Desk</p>
            </div>
        ";

        $altBody = "Hello {$recipientName},\n\nReminder: {$title}\n{$description}\n" . ($dueDate ? "Due Date: {$dueDate}\n\n" : "\n") . "Best Regards,\nSupport Desk";

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
                $mail->addAddress($toEmail, $recipientName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $htmlBody;
                $mail->AltBody = $altBody;

                $mail->send();
                return true;
            } catch (Throwable $e) {
                Logger::error("Failed to send reminder email to {$toEmail}: " . $e->getMessage());
            }
        }

        if ($appDebug || !$isConfigured) {
            Logger::info("[DEV EMAIL] Reminder sent to {$toEmail}: {$title}", [
                'recipient' => $toEmail,
                'title' => $title,
                'due_date' => $dueDate,
            ]);
            return true;
        }

        return false;
    }
}
