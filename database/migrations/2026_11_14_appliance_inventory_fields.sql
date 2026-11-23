-- Eco Metrics - Step 6: Appliance category & inventory fields
-- Run this once on an existing installation that was created before Step 6.
-- Fresh installations already get these columns from database/schema.sql.

USE `eco_metrics_db`;

ALTER TABLE `appliances`
    ADD COLUMN `voltage` DECIMAL(8, 2) NOT NULL DEFAULT 220.00 AFTER `brand_model`,
    ADD COLUMN `daily_operating_hours` DECIMAL(5, 2) NOT NULL DEFAULT 0.00 AFTER `quantity`,
    ADD COLUMN `usage_frequency` ENUM('daily', 'weekly', 'monthly', 'occasional') NOT NULL DEFAULT 'daily' AFTER `daily_operating_hours`,
    ADD COLUMN `archived_at` TIMESTAMP NULL DEFAULT NULL AFTER `status`,
    ADD INDEX `idx_appliances_status` (`status`),
    ADD INDEX `idx_appliances_archived` (`archived_at`);
