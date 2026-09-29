CREATE TABLE IF NOT EXISTS `follow_ups` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `due_at` DATETIME NOT NULL,
    `type` ENUM('call', 'meeting', 'email') NOT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `status` ENUM('pending', 'done', 'missed') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_follow_ups_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_follow_ups_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_follow_ups_due_status` (`due_at`, `status`),
    INDEX `idx_follow_ups_client_id` (`client_id`),
    INDEX `idx_follow_ups_user_id` (`user_id`),
    INDEX `idx_follow_ups_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
