-- Migration: Expand pan_no column to store AES-256-GCM encrypted ciphertext and tag
ALTER TABLE `clients` MODIFY COLUMN `pan_no` VARCHAR(255) NULL DEFAULT NULL;
