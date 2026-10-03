-- Migration 022: Omnichannel Messaging Layer (Phase 9)
-- Message Templates, Message Delivery Logs, Channel Opt-Outs, and Permissions

-- 1. Message Templates Table
CREATE TABLE IF NOT EXISTS `message_templates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `channel` ENUM('email', 'whatsapp', 'sms', 'all') NOT NULL DEFAULT 'all',
    `subject` VARCHAR(255) NULL,
    `body_template` TEXT NOT NULL,
    `dlt_template_id` VARCHAR(100) NULL,
    `whatsapp_template_name` VARCHAR(100) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_templates_channel` (`channel`),
    INDEX `idx_templates_active` (`is_active`),
    INDEX `idx_templates_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Message Logs Table (Audit trail of every outbound message)
CREATE TABLE IF NOT EXISTS `message_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `channel` ENUM('email', 'whatsapp', 'sms') NOT NULL,
    `template_id` BIGINT UNSIGNED NULL,
    `entity_type` ENUM('client', 'student', 'lead', 'custom') NOT NULL DEFAULT 'custom',
    `entity_id` BIGINT UNSIGNED NULL,
    `recipient` VARCHAR(191) NOT NULL,
    `subject` VARCHAR(255) NULL,
    `message_body` TEXT NOT NULL,
    `status` ENUM('queued', 'sent', 'failed', 'opted_out') NOT NULL DEFAULT 'queued',
    `gateway_message_id` VARCHAR(100) NULL,
    `error_message` TEXT NULL,
    `sent_by` BIGINT UNSIGNED NULL,
    `sent_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_msg_logs_channel` (`channel`),
    INDEX `idx_msg_logs_status` (`status`),
    INDEX `idx_msg_logs_recipient` (`recipient`),
    INDEX `idx_msg_logs_entity` (`entity_type`, `entity_id`),
    INDEX `idx_msg_logs_sent_by` (`sent_by`),
    CONSTRAINT `fk_msg_logs_template` FOREIGN KEY (`template_id`) REFERENCES `message_templates` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_msg_logs_user` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Channel Opt-Outs Table (Consent management: never message opted-out contacts)
CREATE TABLE IF NOT EXISTS `channel_opt_outs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `channel` ENUM('email', 'whatsapp', 'sms', 'all') NOT NULL,
    `identifier` VARCHAR(191) NOT NULL,
    `entity_type` ENUM('client', 'student', 'lead', 'other') NOT NULL DEFAULT 'other',
    `entity_id` BIGINT UNSIGNED NULL,
    `reason` VARCHAR(255) NULL,
    `opted_out_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_opt_out_channel_identifier` (`channel`, `identifier`),
    INDEX `idx_opt_out_identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seed Standard Message Templates with Variables
INSERT INTO `message_templates` (`code`, `name`, `channel`, `subject`, `body_template`, `dlt_template_id`, `whatsapp_template_name`, `is_active`)
VALUES
('TPL_ADMISSION_CONFIRM', 'Admission Confirmation', 'all', 'Admission Confirmed: {course}', 'Dear {name}, congratulations! Your enrollment for {course} is confirmed. Total fee: INR {amount}. Welcome to {business_name}!', 'DLT_ADM_101', 'admission_confirmed', 1),
('TPL_PAYMENT_RECEIVED', 'Payment Received', 'all', 'Payment Receipt {receipt_no}', 'Dear {name}, thank you for your payment of INR {amount} (Receipt: {receipt_no}). Best regards, {business_name}.', 'DLT_PAY_201', 'payment_received', 1),
('TPL_PAYMENT_REMINDER', 'Payment Due Reminder', 'all', 'Payment Due Reminder: INR {amount}', 'Dear {name}, this is a gentle reminder that your payment of INR {amount} is due on {due_date}. Please clear your dues on time. Best regards, {business_name}.', 'DLT_REM_301', 'payment_reminder', 1),
('TPL_FOLLOW_UP', 'Lead Follow-up', 'all', 'Following up on your inquiry — {business_name}', 'Hello {name}, thank you for inquiring about our services and training courses. Please let us know how we can assist you today! Best regards, {business_name}.', 'DLT_FLW_401', 'lead_followup', 1),
('TPL_BIRTHDAY_WISH', 'Birthday Wish', 'all', 'Happy Birthday from {business_name}!', 'Dear {name}, wishing you a very Happy Birthday! May your day be filled with happiness and the coming year with success. Warm regards, {business_name}.', 'DLT_BDY_501', 'birthday_wishes', 1),
('TPL_BROADCAST_NOTICE', 'Notice / Broadcast', 'all', 'Important Notice from {business_name}', 'Dear {name}, please note this important update: {notice_text}. Thank you for your association with {business_name}.', 'DLT_NOT_601', 'general_announcement', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `body_template` = VALUES(`body_template`);

-- 5. Seed Permissions for Messaging
INSERT IGNORE INTO `permissions` (`name`, `label`) VALUES
('message.send', 'Send Outbound Messages & Broadcasts'),
('template.manage', 'Create & Manage Message Templates');

-- Admin & Manager get all
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE p.`name` IN ('message.send', 'template.manage')
  AND r.`name` IN ('admin', 'manager');

-- Counselor & Accountant get message.send
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE p.`name` = 'message.send'
  AND r.`name` IN ('counselor', 'accountant');
