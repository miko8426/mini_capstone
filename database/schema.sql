-- Eco Metrics Database Schema
-- Compatible with MySQL / Laragon

CREATE DATABASE IF NOT EXISTS `eco_metrics_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `eco_metrics_db`;

-- Disable foreign key checks for clean table recreation if needed
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `consumption_snapshots`;
DROP TABLE IF EXISTS `consumption_thresholds`;
DROP TABLE IF EXISTS `consumption_records`;
DROP TABLE IF EXISTS `emission_factors`;
DROP TABLE IF EXISTS `electricity_rates`;
DROP TABLE IF EXISTS `appliance_usage_records`;
DROP TABLE IF EXISTS `appliances`;
DROP TABLE IF EXISTS `appliance_categories`;
DROP TABLE IF EXISTS `offices`;
DROP TABLE IF EXISTS `campuses`;
DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `system_settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. ROLES TABLE
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS TABLE
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `campus_id` INT UNSIGNED NULL,
    `office_id` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role` (`role_id`),
    INDEX `idx_users_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. USER PERMISSIONS TABLE
CREATE TABLE `user_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `permission_key` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uk_user_permission` (`user_id`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CAMPUSES TABLE
CREATE TABLE `campuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `address` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. OFFICES TABLE
CREATE TABLE `offices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campus_id` INT UNSIGNED NOT NULL,
    `room_number` VARCHAR(30) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `floor_level` INT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_offices_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_offices_campus` (`campus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User access scoping (assigned after organizational tables exist)
ALTER TABLE `users`
    ADD CONSTRAINT `fk_users_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_users_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD INDEX `idx_users_scope` (`campus_id`, `office_id`);

-- 8. APPLIANCE CATEGORIES TABLE
CREATE TABLE `appliance_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. APPLIANCES TABLE
CREATE TABLE `appliances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `office_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `brand_model` VARCHAR(100) NULL,
    `voltage` DECIMAL(8, 2) NOT NULL DEFAULT 220.00,
    `wattage` DECIMAL(10, 2) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `daily_operating_hours` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `usage_frequency` ENUM('daily', 'weekly', 'monthly', 'occasional') NOT NULL DEFAULT 'daily',
    `status` ENUM('active', 'under_maintenance', 'decommissioned') DEFAULT 'active',
    `archived_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_appliances_category` FOREIGN KEY (`category_id`) REFERENCES `appliance_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_appliances_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_appliances_category` (`category_id`),
    INDEX `idx_appliances_office` (`office_id`),
    INDEX `idx_appliances_status` (`status`),
    INDEX `idx_appliances_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. APPLIANCE USAGE RECORDS TABLE
CREATE TABLE `appliance_usage_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appliance_id` INT UNSIGNED NOT NULL,
    `recorded_by` INT UNSIGNED NULL,
    `hours_per_day` DECIMAL(5, 2) NOT NULL,
    `days_per_week` DECIMAL(3, 1) NOT NULL,
    `usage_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_usage_appliance` FOREIGN KEY (`appliance_id`) REFERENCES `appliances` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_usage_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_usage_appliance` (`appliance_id`),
    INDEX `idx_usage_date` (`usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. ELECTRICITY RATES TABLE
CREATE TABLE `electricity_rates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rate_per_kwh` DECIMAL(10, 4) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'PHP',
    `effective_date` DATE NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_rates_effective_date` (`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. EMISSION FACTORS TABLE
CREATE TABLE `emission_factors` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `factor_name` VARCHAR(100) NOT NULL,
    `kg_co2_per_kwh` DECIMAL(10, 6) NOT NULL,
    `source` VARCHAR(255) NULL,
    `effective_date` DATE NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_factors_effective_date` (`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. IMMUTABLE CONSUMPTION SNAPSHOTS TABLE
-- Every calculation stores the appliance specifications and the rate/factor
-- that were applied, so later edits never rewrite history.
CREATE TABLE `consumption_snapshots` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appliance_id` INT UNSIGNED NULL,
    `campus_id` INT UNSIGNED NULL,
    `office_id` INT UNSIGNED NULL,
    `recorded_by` INT UNSIGNED NULL,
    `period_type` ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'monthly',
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `appliance_name` VARCHAR(100) NOT NULL,
    `category_name` VARCHAR(100) NOT NULL,
    `campus_name` VARCHAR(100) NOT NULL,
    `office_name` VARCHAR(100) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `wattage` DECIMAL(10, 2) NOT NULL,
    `daily_operating_hours` DECIMAL(5, 2) NOT NULL,
    `days_in_period` INT UNSIGNED NOT NULL,
    `total_kwh` DECIMAL(14, 4) NOT NULL,
    `rate_per_kwh` DECIMAL(10, 4) NOT NULL,
    `electricity_cost` DECIMAL(14, 2) NOT NULL,
    `emission_factor` DECIMAL(10, 6) NOT NULL,
    `co2_kg` DECIMAL(14, 4) NOT NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_snapshot_appliance` FOREIGN KEY (`appliance_id`) REFERENCES `appliances` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_snapshot_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_snapshot_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_snapshot_period` (`period_start`, `period_end`),
    INDEX `idx_snapshot_appliance` (`appliance_id`),
    INDEX `idx_snapshot_campus` (`campus_id`),
    INDEX `idx_snapshot_office` (`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. CONSUMPTION RECORDS TABLE
CREATE TABLE `consumption_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campus_id` INT UNSIGNED NOT NULL,
    `recorded_by` INT UNSIGNED NULL,
    `billing_period_start` DATE NOT NULL,
    `billing_period_end` DATE NOT NULL,
    `total_kwh` DECIMAL(12, 2) NOT NULL,
    `total_cost` DECIMAL(12, 2) NOT NULL,
    `total_co2_kg` DECIMAL(12, 2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_consumption_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_consumption_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_consumption_campus` (`campus_id`),
    INDEX `idx_consumption_dates` (`billing_period_start`, `billing_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. CONSUMPTION THRESHOLDS TABLE (administrator configurable, any scope)
CREATE TABLE `consumption_thresholds` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope_type` ENUM('campus', 'office', 'appliance') NOT NULL,
    `scope_id` INT UNSIGNED NOT NULL,
    `monthly_kwh_limit` DECIMAL(12, 2) NOT NULL,
    `alert_percentage` DECIMAL(5, 2) DEFAULT 80.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_threshold_scope` (`scope_type`, `scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. NOTIFICATIONS TABLE
CREATE TABLE `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `threshold_id` INT UNSIGNED NULL,
    `period_key` VARCHAR(20) NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'warning', 'critical') DEFAULT 'info',
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_threshold` FOREIGN KEY (`threshold_id`) REFERENCES `consumption_thresholds` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_notifications_user` (`user_id`),
    INDEX `idx_notifications_read` (`is_read`),
    UNIQUE KEY `uk_notification_dedupe` (`user_id`, `threshold_id`, `period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. AUDIT LOGS TABLE
CREATE TABLE `audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity` VARCHAR(50) NOT NULL,
    `entity_id` INT UNSIGNED NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_entity` (`entity`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. SYSTEM SETTINGS TABLE
CREATE TABLE `system_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
