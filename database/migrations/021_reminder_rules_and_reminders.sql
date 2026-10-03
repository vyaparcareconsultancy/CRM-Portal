-- Migration 021: Reminder Rules & Reminders (Phase 7)
-- Managed reminder rules without hardcoded statutory dates, instance generator, and notifications

-- 1. Ensure date_of_birth on clients table for birthday reminders
SET @existDob := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'date_of_birth');
SET @sqlDob := IF(@existDob = 0, 'ALTER TABLE `clients` ADD COLUMN `date_of_birth` DATE NULL DEFAULT NULL AFTER `alt_mobile`', 'SELECT 1');
PREPARE stmtDob FROM @sqlDob;
EXECUTE stmtDob;
DEALLOCATE PREPARE stmtDob;

-- 2. Reminder Rules Table
CREATE TABLE IF NOT EXISTS `reminder_rules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `rule_code` VARCHAR(50) NULL UNIQUE,
    `category` VARCHAR(50) NOT NULL DEFAULT 'statutory_compliance',
    `applies_to` ENUM('client_service', 'student_fee', 'all_clients') NOT NULL DEFAULT 'client_service',
    `service_id` BIGINT UNSIGNED NULL,
    `due_day` TINYINT UNSIGNED NULL,
    `due_date` DATE NULL,
    `remind_days_before` INT UNSIGNED NOT NULL DEFAULT 3,
    `channels` JSON NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `description` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_reminder_rules_applies_to` (`applies_to`),
    INDEX `idx_reminder_rules_service_id` (`service_id`),
    INDEX `idx_reminder_rules_is_active` (`is_active`),
    INDEX `idx_reminder_rules_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_reminder_rules_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reminder_rules_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Reminders Table
CREATE TABLE IF NOT EXISTS `reminders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rule_id` BIGINT UNSIGNED NULL,
    `entity_type` ENUM('client', 'student') NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    `client_service_id` BIGINT UNSIGNED NULL,
    `invoice_id` BIGINT UNSIGNED NULL,
    `period` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `due_date` DATE NOT NULL,
    `remind_date` DATE NOT NULL,
    `status` ENUM('pending', 'notified', 'done', 'dismissed') NOT NULL DEFAULT 'pending',
    `assigned_user_id` BIGINT UNSIGNED NULL,
    `done_at` DATETIME NULL,
    `done_by` BIGINT UNSIGNED NULL,
    `done_note` TEXT NULL,
    `notified_at` DATETIME NULL,
    `channels_sent` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uq_reminders_rule_entity_period` (`rule_id`, `entity_type`, `entity_id`, `period`),
    INDEX `idx_reminders_status` (`status`),
    INDEX `idx_reminders_due_date` (`due_date`),
    INDEX `idx_reminders_remind_date` (`remind_date`),
    INDEX `idx_reminders_entity` (`entity_type`, `entity_id`),
    INDEX `idx_reminders_assigned_user` (`assigned_user_id`),
    INDEX `idx_reminders_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_reminders_rule` FOREIGN KEY (`rule_id`) REFERENCES `reminder_rules` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reminders_client_service` FOREIGN KEY (`client_service_id`) REFERENCES `client_services` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reminders_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reminders_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reminders_done_by` FOREIGN KEY (`done_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seed Default Configurable Reminder Rules (Dynamic, zero hardcoded statutory dates)
INSERT INTO `reminder_rules` (`name`, `rule_code`, `category`, `applies_to`, `service_id`, `due_day`, `due_date`, `remind_days_before`, `channels`, `is_active`, `description`)
VALUES
('GSTR-1 Return', 'RULE_GSTR1', 'statutory_compliance', 'client_service', (SELECT `id` FROM `services` WHERE `code` = 'SRV-GST-RET' LIMIT 1), 11, NULL, 3, JSON_ARRAY('in_app', 'email'), 1, 'Monthly GSTR-1 outbound supply return filing deadline (default 11th).'),
('GSTR-3B Return', 'RULE_GSTR3B', 'statutory_compliance', 'client_service', (SELECT `id` FROM `services` WHERE `code` = 'SRV-GST-RET' LIMIT 1), 20, NULL, 3, JSON_ARRAY('in_app', 'email'), 1, 'Monthly GSTR-3B summary return and tax payment deadline (default 20th).'),
('ITR Filing', 'RULE_ITR', 'statutory_compliance', 'client_service', (SELECT `id` FROM `services` WHERE `code` = 'SRV-ITR' LIMIT 1), NULL, '2026-07-31', 15, JSON_ARRAY('in_app', 'email'), 1, 'Annual Income Tax Return filing deadline (non-audit default July 31st).'),
('TDS Return', 'RULE_TDS', 'statutory_compliance', 'client_service', (SELECT `id` FROM `services` WHERE `code` = 'SRV-TDS' LIMIT 1), 7, NULL, 3, JSON_ARRAY('in_app', 'email'), 1, 'TDS monthly deposit by 7th / quarterly return filing deadline.'),
('ROC Filing', 'RULE_ROC', 'statutory_compliance', 'client_service', (SELECT `id` FROM `services` WHERE `code` = 'SRV-ROC' LIMIT 1), NULL, '2026-09-30', 10, JSON_ARRAY('in_app', 'email'), 1, 'Annual ROC Company Filing (AOC-4, MGT-7 default Sept 30th).'),
('PF/ESI Return', 'RULE_PFESI', 'statutory_compliance', 'client_service', (SELECT `id` FROM `services` WHERE `code` = 'SRV-PFESI' LIMIT 1), 15, NULL, 3, JSON_ARRAY('in_app', 'email'), 1, 'Monthly PF & ESIC contribution payment deadline (default 15th).'),
('Student Fee Due', 'RULE_FEE_DUE', 'fee_payment', 'student_fee', NULL, NULL, NULL, 3, JSON_ARRAY('in_app', 'email'), 1, 'Reminds student and counselor before tuition fee due date.'),
('Service Annual Renewal', 'RULE_RENEWAL', 'renewal', 'all_clients', NULL, NULL, NULL, 15, JSON_ARRAY('in_app', 'email'), 1, 'Reminds client and accountant 15 days before service subscription renewal.'),
('Client Birthday', 'RULE_BIRTHDAY', 'birthday', 'all_clients', NULL, NULL, NULL, 0, JSON_ARRAY('in_app', 'email'), 1, 'Birthday greetings and client relationship reminder on birthday.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 5. Seed Permissions for Reminders
INSERT IGNORE INTO `permissions` (`name`, `label`) VALUES
('reminder.manage', 'Configure & Manage Reminder Rules'),
('reminder.view', 'View Reminders & Deadlines');

-- Assign reminder.manage & reminder.view to Admin, Manager, Accountant
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE p.`name` IN ('reminder.manage', 'reminder.view')
  AND r.`name` IN ('admin', 'manager', 'accountant');

-- Assign reminder.view to Counselor and Trainer
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE p.`name` = 'reminder.view'
  AND r.`name` IN ('counselor', 'trainer');
