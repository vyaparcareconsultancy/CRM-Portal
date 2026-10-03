-- Migration 019: Training Institute (Courses upgrade, Batches, Enrollments, Attendance & Progress)

-- 1. Upgrade Courses Table with syllabus_modules and duration label
ALTER TABLE `courses`
ADD COLUMN `duration` VARCHAR(100) NULL AFTER `duration_weeks`,
ADD COLUMN `syllabus_modules` JSON NULL AFTER `syllabus_summary`;

-- Update default sample courses with modules and duration
UPDATE `courses` SET
    `duration` = '6 Weeks',
    `syllabus_modules` = JSON_ARRAY(
        'Module 1: GST Law & Constitutional Framework',
        'Module 2: Registration & Threshold Limits',
        'Module 3: Invoicing, E-way Bills & E-Invoicing',
        'Module 4: Input Tax Credit (ITC) Rules & Reconciliation',
        'Module 5: Returns Filing (GSTR-1, 3B, 9 & 9C)',
        'Module 6: GST Audits, Assessments & Practical Workshop'
    )
WHERE `course_code` = 'CRS-GST';

UPDATE `courses` SET
    `duration` = '8 Weeks',
    `syllabus_modules` = JSON_ARRAY(
        'Module 1: Accounting Fundamentals & Double Entry',
        'Module 2: Company Creation & Masters in Tally Prime',
        'Module 3: Voucher Entry & Inventory Management',
        'Module 4: GST & TDS Transactions in Tally',
        'Module 5: Banking & Bank Reconciliation (BRS)',
        'Module 6: Balance Sheet & Profit-Loss Finalization'
    )
WHERE `course_code` = 'CRS-TALLY';

UPDATE `courses` SET
    `duration` = '4 Weeks',
    `syllabus_modules` = JSON_ARRAY(
        'Module 1: Income Tax Basics & Heads of Income',
        'Module 2: Deductions under Chapter VI-A',
        'Module 3: ITR-1 (Sahaj) & ITR-2 Filing on Portal',
        'Module 4: ITR-3 & ITR-4 (Presumptive Taxation, 44AD/ADA)'
    )
WHERE `course_code` = 'CRS-ITR';

UPDATE `courses` SET
    `duration` = '6 Weeks',
    `syllabus_modules` = JSON_ARRAY(
        'Module 1: Companies Act 2013 & LLP Act Overview',
        'Module 2: Company Incorporation via SPICe+',
        'Module 3: Annual ROC Filings (AOC-4 & MGT-7)',
        'Module 4: Director Compliance (DIR-3 KYC, Changes in Directors)'
    )
WHERE `course_code` = 'CRS-ROC';

-- 2. Batches Table
CREATE TABLE IF NOT EXISTS `batches` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `batch_code` VARCHAR(50) NOT NULL UNIQUE,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `trainer_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(150) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `timing` VARCHAR(100) NULL,
    `days` VARCHAR(100) NULL,
    `capacity` INT UNSIGNED NOT NULL DEFAULT 30,
    `status` ENUM('upcoming', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_batches_course` (`course_id`),
    INDEX `idx_batches_trainer` (`trainer_id`),
    INDEX `idx_batches_status` (`status`),
    INDEX `idx_batches_start_date` (`start_date`),
    INDEX `idx_batches_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_batches_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_batches_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Enrollments / Admissions Table
CREATE TABLE IF NOT EXISTS `enrollments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `enrollment_no` VARCHAR(50) NOT NULL UNIQUE,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `batch_id` BIGINT UNSIGNED NOT NULL,
    `admission_date` DATE NOT NULL,
    `agreed_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `invoice_id` BIGINT UNSIGNED NULL,
    `status` ENUM('active', 'completed', 'dropped') NOT NULL DEFAULT 'active',
    `certificate_ready` TINYINT(1) NOT NULL DEFAULT 0,
    `certificate_no` VARCHAR(100) NULL,
    `certified_at` DATE NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_enrollments_student` (`student_id`),
    INDEX `idx_enrollments_course` (`course_id`),
    INDEX `idx_enrollments_batch` (`batch_id`),
    INDEX `idx_enrollments_status` (`status`),
    INDEX `idx_enrollments_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_enrollments_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_enrollments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_enrollments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Attendance Table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `batch_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `session_date` DATE NOT NULL,
    `status` ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
    `marked_by` BIGINT UNSIGNED NULL,
    `remarks` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_batch_student_date` (`batch_id`, `student_id`, `session_date`),
    INDEX `idx_attendance_batch_date` (`batch_id`, `session_date`),
    INDEX `idx_attendance_student` (`student_id`),
    INDEX `idx_attendance_status` (`status`),
    CONSTRAINT `fk_attendance_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_marked_by` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Student Module Progress Table
CREATE TABLE IF NOT EXISTS `student_module_progress` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `enrollment_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `module_name` VARCHAR(150) NOT NULL,
    `module_order` INT NOT NULL DEFAULT 1,
    `status` ENUM('not_started', 'in_progress', 'completed') NOT NULL DEFAULT 'not_started',
    `trainer_remarks` VARCHAR(255) NULL,
    `test_score` DECIMAL(5,2) NULL,
    `max_score` DECIMAL(5,2) NULL DEFAULT 100.00,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_enrollment_module` (`enrollment_id`, `module_name`),
    INDEX `idx_progress_enrollment` (`enrollment_id`),
    INDEX `idx_progress_student` (`student_id`),
    INDEX `idx_progress_status` (`status`),
    CONSTRAINT `fk_progress_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_progress_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_progress_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
