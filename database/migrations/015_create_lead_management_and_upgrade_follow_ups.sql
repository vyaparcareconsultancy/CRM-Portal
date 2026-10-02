-- Phase 1: Lead Management, Shared Contacts, Lead Sources, and Follow-ups Upgrade

-- 1. Master Contacts Table
CREATE TABLE IF NOT EXISTS `contacts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contact_type` ENUM('individual', 'company') NOT NULL DEFAULT 'individual',
    `name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NULL DEFAULT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `whatsapp_number` VARCHAR(20) NULL DEFAULT NULL,
    `alt_mobile` VARCHAR(20) NULL DEFAULT NULL,
    `pan_no` VARCHAR(255) NULL DEFAULT NULL,
    `address_line1` VARCHAR(255) NULL DEFAULT NULL,
    `city` VARCHAR(100) NULL DEFAULT NULL,
    `state` VARCHAR(100) NULL DEFAULT NULL,
    `pincode` VARCHAR(20) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_contacts_mobile` (`mobile`),
    INDEX `idx_contacts_email` (`email`),
    INDEX `idx_contacts_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Lead Sources Table
CREATE TABLE IF NOT EXISTS `lead_sources` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lead_sources` (`name`, `is_active`) VALUES
('Instagram', 1),
('Facebook', 1),
('Google', 1),
('Website', 1),
('WhatsApp', 1),
('Referral', 1),
('Walk-in', 1)
ON DUPLICATE KEY UPDATE `is_active` = VALUES(`is_active`);

-- 3. Managed Services Catalog
CREATE TABLE IF NOT EXISTS `services` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'other',
    `default_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `billing_frequency` VARCHAR(50) NOT NULL DEFAULT 'one_time',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_services_active` (`is_active`),
    INDEX `idx_services_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `services` (`code`, `name`, `category`, `default_fee`) VALUES
('SRV-GST', 'GST Registration & Return Filing', 'gst', 1500.00),
('SRV-ITR', 'Income Tax Return (ITR)', 'itr', 1000.00),
('SRV-ACC', 'Bookkeeping & Accounting', 'accounting', 3000.00),
('SRV-AUD', 'Tax & Statutory Audit', 'audit', 10000.00),
('SRV-REG', 'Company / Business Registration', 'registration', 5000.00),
('SRV-ROC', 'ROC Compliance & Annual Filing', 'roc', 4000.00),
('SRV-TDS', 'TDS Return Filing', 'tds', 1500.00),
('SRV-PF', 'PF & ESI Compliance', 'pf_esi', 2000.00)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Managed Courses Catalog
CREATE TABLE IF NOT EXISTS `courses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `duration_weeks` INT UNSIGNED NOT NULL DEFAULT 4,
    `total_hours` INT UNSIGNED NOT NULL DEFAULT 40,
    `fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `syllabus_summary` TEXT NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_courses_active` (`is_active`),
    INDEX `idx_courses_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `courses` (`course_code`, `name`, `duration_weeks`, `fee`) VALUES
('CRS-GST', 'GST Practitioner Certification', 6, 8000.00),
('CRS-TALLY', 'Tally Prime with Advanced Accounting', 8, 10000.00),
('CRS-ITR', 'Income Tax & E-Filing Mastery', 4, 6000.00),
('CRS-ROC', 'Corporate Compliance & ROC Specialist', 6, 12000.00)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 5. Students Table (For Training conversions)
CREATE TABLE IF NOT EXISTS `students` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_code` VARCHAR(50) NOT NULL UNIQUE,
    `contact_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NULL DEFAULT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `whatsapp_number` VARCHAR(20) NULL DEFAULT NULL,
    `course_name` VARCHAR(150) NULL DEFAULT NULL,
    `qualification` VARCHAR(100) NULL DEFAULT NULL,
    `guardian_name` VARCHAR(150) NULL DEFAULT NULL,
    `guardian_mobile` VARCHAR(20) NULL DEFAULT NULL,
    `date_of_birth` DATE NULL DEFAULT NULL,
    `status` ENUM('enrolled', 'active', 'completed', 'dropped') NOT NULL DEFAULT 'enrolled',
    `notes` TEXT NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_students_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_students_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_students_mobile` (`mobile`),
    INDEX `idx_students_status` (`status`),
    INDEX `idx_students_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Leads Table
CREATE TABLE IF NOT EXISTS `leads` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_code` VARCHAR(50) NOT NULL UNIQUE,
    `contact_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `name` VARCHAR(191) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `whatsapp_number` VARCHAR(20) NULL DEFAULT NULL,
    `email` VARCHAR(191) NULL DEFAULT NULL,
    `lead_source_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `referred_by` VARCHAR(150) NULL DEFAULT NULL,
    `interest_type` ENUM('service', 'course', 'other') NOT NULL DEFAULT 'service',
    `interested_in` VARCHAR(150) NOT NULL,
    `service_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `course_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `status` ENUM('new', 'contacted', 'interested', 'follow_up', 'converted', 'lost') NOT NULL DEFAULT 'new',
    `lost_reason` VARCHAR(255) NULL DEFAULT NULL,
    `assigned_to` BIGINT UNSIGNED NULL DEFAULT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `converted_at` DATETIME NULL DEFAULT NULL,
    `converted_client_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `converted_student_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_leads_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_source` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_converted_client` FOREIGN KEY (`converted_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_converted_student` FOREIGN KEY (`converted_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_leads_status` (`status`),
    INDEX `idx_leads_assigned_to` (`assigned_to`),
    INDEX `idx_leads_source` (`lead_source_id`),
    INDEX `idx_leads_mobile` (`mobile`),
    INDEX `idx_leads_created_at` (`created_at`),
    INDEX `idx_leads_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Upgrade Clients Table
ALTER TABLE `clients`
    ADD COLUMN `contact_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `client_code`,
    ADD COLUMN `whatsapp_number` VARCHAR(20) NULL DEFAULT NULL AFTER `mobile`,
    ADD CONSTRAINT `fk_clients_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 8. Upgrade Follow-ups Table
ALTER TABLE `follow_ups`
    MODIFY `client_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `lead_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `client_id`,
    MODIFY `type` ENUM('call', 'whatsapp', 'visit', 'meeting', 'email') NOT NULL DEFAULT 'call',
    ADD COLUMN `outcome` ENUM('connected', 'not_picked', 'busy', 'switched_off', 'call_back') NULL DEFAULT NULL AFTER `status`,
    ADD COLUMN `remarks` TEXT NULL DEFAULT NULL AFTER `notes`,
    ADD COLUMN `next_follow_up_at` DATETIME NULL DEFAULT NULL AFTER `outcome`,
    ADD CONSTRAINT `fk_follow_ups_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD INDEX `idx_follow_ups_lead_id` (`lead_id`);

-- 9. Roles & Permissions for Lead Management
INSERT INTO `roles` (`name`, `label`)
VALUES ('counselor', 'Counselor')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

INSERT INTO `permissions` (`name`, `label`) VALUES
('lead.view', 'View Leads'),
('lead.view_all', 'View All Leads'),
('lead.manage', 'Manage Leads'),
('lead.convert', 'Convert Leads'),
('lead_source.manage', 'Manage Lead Sources')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- Admin gets all new permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'admin' AND p.name IN ('lead.view', 'lead.view_all', 'lead.manage', 'lead.convert', 'lead_source.manage');

-- Manager gets view all, manage, convert
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'manager' AND p.name IN ('lead.view', 'lead.view_all', 'lead.manage', 'lead.convert');

-- Counselor gets view (scoped to own), manage, convert, follow-ups, and client creation
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'counselor' AND p.name IN ('lead.view', 'lead.manage', 'lead.convert', 'followup.manage', 'client.create');

-- Sales gets view (own), manage, follow-ups
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'sales' AND p.name IN ('lead.view', 'lead.manage', 'followup.manage');
