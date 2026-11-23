-- Eco Metrics - Steps 8-13 schema update
-- Apply once to an installation created before the rates/factors, history,
-- threshold, notification and user-scoping upgrade. Fresh installations get
-- all of this from database/schema.sql.

USE `eco_metrics_db`;

-- User access scoping
ALTER TABLE `users`
    ADD COLUMN `campus_id` INT UNSIGNED NULL AFTER `status`,
    ADD COLUMN `building_id` INT UNSIGNED NULL AFTER `campus_id`,
    ADD COLUMN `department_id` INT UNSIGNED NULL AFTER `building_id`,
    ADD COLUMN `office_id` INT UNSIGNED NULL AFTER `department_id`,
    ADD CONSTRAINT `fk_users_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_users_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_users_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD INDEX `idx_users_scope` (`campus_id`, `building_id`, `department_id`, `office_id`);

-- Effective-dated emission factors
ALTER TABLE `emission_factors`
    ADD COLUMN `effective_date` DATE NOT NULL DEFAULT '2026-01-01' AFTER `source`;
ALTER TABLE `emission_factors` DROP COLUMN `effective_year`;

-- Historical snapshots
CREATE TABLE IF NOT EXISTS `consumption_snapshots` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appliance_id` INT UNSIGNED NULL,
    `campus_id` INT UNSIGNED NULL,
    `building_id` INT UNSIGNED NULL,
    `department_id` INT UNSIGNED NULL,
    `office_id` INT UNSIGNED NULL,
    `recorded_by` INT UNSIGNED NULL,
    `period_type` ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'monthly',
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `appliance_name` VARCHAR(100) NOT NULL,
    `category_name` VARCHAR(100) NOT NULL,
    `campus_name` VARCHAR(100) NOT NULL,
    `building_name` VARCHAR(100) NOT NULL,
    `department_name` VARCHAR(100) NULL,
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
    INDEX `idx_snapshot_building` (`building_id`),
    INDEX `idx_snapshot_department` (`department_id`),
    INDEX `idx_snapshot_office` (`office_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thresholds become scope-based (campus/building/department/office/appliance)
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `consumption_thresholds`;
CREATE TABLE `consumption_thresholds` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope_type` ENUM('campus', 'building', 'department', 'office', 'appliance') NOT NULL,
    `scope_id` INT UNSIGNED NOT NULL,
    `monthly_kwh_limit` DECIMAL(12, 2) NOT NULL,
    `alert_percentage` DECIMAL(5, 2) DEFAULT 80.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_threshold_scope` (`scope_type`, `scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
