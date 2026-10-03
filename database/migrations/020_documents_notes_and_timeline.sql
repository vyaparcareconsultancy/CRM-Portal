-- Migration 020: Documents Vault, Notes, Notifications and Unified Timeline

-- 1. Upgrade client_documents table to support polymorphic attachments (leads, clients, students)
ALTER TABLE `client_documents`
    MODIFY `client_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `entity_type` ENUM('client', 'lead', 'student') NOT NULL DEFAULT 'client' AFTER `id`,
    ADD COLUMN `entity_id` BIGINT UNSIGNED NULL AFTER `entity_type`,
    ADD COLUMN `lead_id` BIGINT UNSIGNED NULL AFTER `client_id`,
    ADD COLUMN `student_id` BIGINT UNSIGNED NULL AFTER `lead_id`,
    ADD COLUMN `document_type` ENUM('pan', 'aadhaar', 'gst_certificate', 'bank_statement_cheque', 'itr', 'photo', 'other') NOT NULL DEFAULT 'other' AFTER `stored_name`,
    ADD COLUMN `title` VARCHAR(255) NULL AFTER `document_type`,
    ADD COLUMN `document_number` VARCHAR(100) NULL AFTER `title`,
    ADD COLUMN `financial_year` VARCHAR(20) NULL AFTER `document_number`,
    ADD COLUMN `expiry_date` DATE NULL AFTER `financial_year`,
    ADD COLUMN `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `expiry_date`;

-- Backfill entity_id for existing records
UPDATE `client_documents` SET `entity_id` = `client_id` WHERE `client_id` IS NOT NULL AND `entity_id` IS NULL;

-- Indexes and Constraints for client_documents
ALTER TABLE `client_documents`
    ADD INDEX `idx_documents_entity` (`entity_type`, `entity_id`),
    ADD INDEX `idx_documents_lead_id` (`lead_id`),
    ADD INDEX `idx_documents_student_id` (`student_id`),
    ADD INDEX `idx_documents_type` (`document_type`),
    ADD CONSTRAINT `fk_client_documents_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_client_documents_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 2. Notes Table
CREATE TABLE IF NOT EXISTS `notes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entity_type` ENUM('lead', 'client', 'student') NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `note` TEXT NOT NULL,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_notes_entity` (`entity_type`, `entity_id`),
    INDEX `idx_notes_user` (`user_id`),
    INDEX `idx_notes_pinned` (`is_pinned`),
    INDEX `idx_notes_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Notifications Table (For Staff Mentions and In-App Alerts)
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'mention',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_notifications_user` (`user_id`),
    INDEX `idx_notifications_read` (`is_read`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Permissions for Documents & Notes
INSERT IGNORE INTO `permissions` (`name`, `label`) VALUES
('document.manage', 'Upload & Manage Documents'),
('document.view', 'View & Download Documents'),
('note.manage', 'Create & Manage Notes');

-- Admin, Manager, Accountant, Counselor permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE p.`name` IN ('document.manage', 'document.view', 'note.manage')
  AND r.`name` IN ('admin', 'manager', 'accountant', 'counselor');

-- Trainer permissions (document.view, note.manage)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`name` IN ('document.view', 'note.manage')
WHERE r.`name` = 'trainer';
