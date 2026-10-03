-- Migration 018: Invoices, Payments, Installment Plans, and Settings

-- 1. Settings Table (For business details, receipt/invoice prefix series)
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `value`) VALUES
('business_name', 'Vyapar Care Consultancy & Training Institute'),
('business_address', '101, Business Towers, Commercial Complex, Mumbai, Maharashtra - 400001'),
('business_phone', '+91 98765 43210'),
('business_email', 'accounts@vyaparcare.com'),
('business_gstin', '27AABCU9603R1ZM'),
('business_pan', 'AABCU9603R'),
('business_logo_url', '/assets/images/logo.png'),
('receipt_prefix', 'REC'),
('invoice_prefix', 'INV')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- 2. Invoices / Fee Records Table
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
    `client_id` BIGINT UNSIGNED NULL,
    `client_service_id` BIGINT UNSIGNED NULL,
    `student_id` BIGINT UNSIGNED NULL,
    `course_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `gst_rate_pct` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `gst_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `net_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `balance_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `issue_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `status` ENUM('unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'unpaid',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_invoices_client` (`client_id`),
    INDEX `idx_invoices_student` (`student_id`),
    INDEX `idx_invoices_status` (`status`),
    INDEX `idx_invoices_due_date` (`due_date`),
    INDEX `idx_invoices_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_invoices_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invoices_client_service` FOREIGN KEY (`client_service_id`) REFERENCES `client_services` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invoices_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invoices_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Installment Plans Table (For course fees and staggered services)
CREATE TABLE IF NOT EXISTS `invoice_installments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `installment_no` INT NOT NULL,
    `due_date` DATE NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'partially_paid', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_installments_invoice` (`invoice_id`),
    INDEX `idx_installments_status` (`status`),
    INDEX `idx_installments_due_date` (`due_date`),
    CONSTRAINT `fk_installments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Payments Table (Supports multiple partial payments per invoice)
CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `receipt_no` VARCHAR(50) NOT NULL UNIQUE,
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `client_id` BIGINT UNSIGNED NULL,
    `student_id` BIGINT UNSIGNED NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_mode` ENUM('cash', 'upi', 'bank_transfer', 'cheque', 'card') NOT NULL DEFAULT 'cash',
    `reference_no` VARCHAR(100) NULL,
    `received_by` BIGINT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_payments_invoice` (`invoice_id`),
    INDEX `idx_payments_client` (`client_id`),
    INDEX `idx_payments_student` (`student_id`),
    INDEX `idx_payments_date` (`payment_date`),
    INDEX `idx_payments_mode` (`payment_mode`),
    INDEX `idx_payments_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_payments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
