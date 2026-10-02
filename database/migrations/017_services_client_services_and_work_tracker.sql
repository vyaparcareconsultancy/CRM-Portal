-- Migration 017: Services Master, Client Services, Compliance Details & Work Tracker

-- 1. Ensure type and frequency columns on services table
SET @existType := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'type');
SET @sqlType := IF(@existType = 0, 'ALTER TABLE `services` ADD COLUMN `type` ENUM(\'one_time\', \'recurring\') NOT NULL DEFAULT \'recurring\' AFTER `name`', 'SELECT 1');
PREPARE stmtType FROM @sqlType;
EXECUTE stmtType;
DEALLOCATE PREPARE stmtType;

SET @existFreq := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'frequency');
SET @sqlFreq := IF(@existFreq = 0, 'ALTER TABLE `services` ADD COLUMN `frequency` ENUM(\'monthly\', \'quarterly\', \'yearly\') NULL AFTER `type`', 'SELECT 1');
PREPARE stmtFreq FROM @sqlFreq;
EXECUTE stmtFreq;
DEALLOCATE PREPARE stmtFreq;

-- 2. Client Services Subscription Table
CREATE TABLE IF NOT EXISTS `client_services` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED NOT NULL,
    `assigned_accountant_id` BIGINT UNSIGNED NULL,
    `fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `frequency` ENUM('one_time', 'monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
    `start_date` DATE NOT NULL,
    `status` ENUM('active', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_client_services_client` (`client_id`),
    INDEX `idx_client_services_service` (`service_id`),
    INDEX `idx_client_services_status` (`status`),
    INDEX `idx_client_services_accountant` (`assigned_accountant_id`),
    CONSTRAINT `fk_client_services_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_client_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_client_services_accountant` FOREIGN KEY (`assigned_accountant_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Client Compliance Details Table
CREATE TABLE IF NOT EXISTS `client_compliance_details` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `gstin` VARCHAR(20) NULL,
    `gst_filing_type` ENUM('monthly', 'qrmp', 'composition', 'none') NOT NULL DEFAULT 'monthly',
    `pan` VARCHAR(50) NULL,
    `tan` VARCHAR(20) NULL,
    `cin_llpin` VARCHAR(30) NULL,
    `pf_esi_codes` VARCHAR(100) NULL,
    `financial_year` VARCHAR(20) NULL,
    `portal_notes` TEXT NULL,
    `portal_credentials_encrypted` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_client_compliance_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Work Tracker per Service Period Table
CREATE TABLE IF NOT EXISTS `service_work_tracker` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_service_id` BIGINT UNSIGNED NOT NULL,
    `client_id` BIGINT UNSIGNED NOT NULL,
    `period` VARCHAR(50) NOT NULL,
    `status` ENUM('pending', 'data_received', 'filed', 'acknowledged') NOT NULL DEFAULT 'pending',
    `acknowledgment_no` VARCHAR(100) NULL,
    `filing_date` DATE NULL,
    `assigned_to` BIGINT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_work_tracker_client_service` (`client_service_id`),
    INDEX `idx_work_tracker_client` (`client_id`),
    INDEX `idx_work_tracker_status` (`status`),
    INDEX `idx_work_tracker_period` (`period`),
    CONSTRAINT `fk_work_tracker_client_service` FOREIGN KEY (`client_service_id`) REFERENCES `client_services` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_work_tracker_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_work_tracker_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed Standard Default Services
INSERT INTO `services` (`code`, `name`, `type`, `frequency`, `default_fee`, `category`, `is_active`) VALUES
('SRV-GST-REG', 'GST Registration', 'one_time', NULL, 1500.00, 'gst', 1),
('SRV-GST-RET', 'GST Return', 'recurring', 'monthly', 2000.00, 'gst', 1),
('SRV-ITR-STD', 'ITR', 'recurring', 'yearly', 2500.00, 'itr', 1),
('SRV-ACC-BKP', 'Accounting/Bookkeeping', 'recurring', 'monthly', 5000.00, 'accounting', 1),
('SRV-TAX-AUD', 'Tax Audit', 'recurring', 'yearly', 15000.00, 'audit', 1),
('SRV-REG-BUS', 'Company/Firm Registration', 'one_time', NULL, 7500.00, 'registration', 1),
('SRV-ROC-CMP', 'ROC Compliance', 'recurring', 'yearly', 6000.00, 'roc', 1),
('SRV-TDS-RET', 'TDS', 'recurring', 'quarterly', 3000.00, 'tds', 1),
('SRV-PF-ESI', 'PF/ESI', 'recurring', 'monthly', 2500.00, 'pf_esi', 1)
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `type` = VALUES(`type`),
    `frequency` = VALUES(`frequency`),
    `default_fee` = VALUES(`default_fee`),
    `category` = VALUES(`category`);
