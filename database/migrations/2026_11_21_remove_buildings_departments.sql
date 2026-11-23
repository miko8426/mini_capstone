-- Eco Metrics - remove Buildings and Departments
-- Apply once to an installation created before the campus -> office hierarchy.
-- Campuses and offices are preserved: every office is re-attached to the campus
-- of the building it used to belong to before buildings/departments are dropped.
-- Fresh installations get the final structure from database/schema.sql.

USE `eco_metrics_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Offices: attach directly to the campus, then drop the building/department links.
ALTER TABLE `offices` ADD COLUMN `campus_id` INT UNSIGNED NULL AFTER `id`;
UPDATE `offices` o
JOIN `buildings` b ON b.id = o.building_id
SET o.campus_id = b.campus_id;

ALTER TABLE `offices` DROP FOREIGN KEY `fk_offices_building`;
ALTER TABLE `offices` DROP FOREIGN KEY `fk_offices_department`;
ALTER TABLE `offices`
    DROP COLUMN `building_id`,
    DROP COLUMN `department_id`;
ALTER TABLE `offices`
    MODIFY COLUMN `campus_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_offices_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD INDEX `idx_offices_campus` (`campus_id`);

-- 2. Users: drop the building/department scope columns.
ALTER TABLE `users` DROP FOREIGN KEY `fk_users_building`;
ALTER TABLE `users` DROP FOREIGN KEY `fk_users_department`;
ALTER TABLE `users` DROP FOREIGN KEY `fk_users_campus`;
ALTER TABLE `users` DROP FOREIGN KEY `fk_users_office`;
ALTER TABLE `users` DROP INDEX `idx_users_scope`;
ALTER TABLE `users`
    DROP COLUMN `building_id`,
    DROP COLUMN `department_id`;
ALTER TABLE `users`
    ADD CONSTRAINT `fk_users_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_users_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD INDEX `idx_users_scope` (`campus_id`, `office_id`);

-- 3. Manual meter readings move from buildings to campuses.
ALTER TABLE `consumption_records` ADD COLUMN `campus_id` INT UNSIGNED NULL AFTER `id`;
UPDATE `consumption_records` cr
JOIN `buildings` b ON b.id = cr.building_id
SET cr.campus_id = b.campus_id;

ALTER TABLE `consumption_records` DROP FOREIGN KEY `fk_consumption_building`;
ALTER TABLE `consumption_records`
    DROP COLUMN `building_id`;
ALTER TABLE `consumption_records`
    MODIFY COLUMN `campus_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_consumption_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD INDEX `idx_consumption_campus` (`campus_id`);

-- 4. Historical snapshots keep their frozen campus/office names only.
ALTER TABLE `consumption_snapshots`
    DROP COLUMN `building_id`,
    DROP COLUMN `department_id`,
    DROP COLUMN `building_name`,
    DROP COLUMN `department_name`;

-- 5. Thresholds: building/department scopes are no longer supported.
DELETE FROM `consumption_thresholds` WHERE `scope_type` IN ('building', 'department');
ALTER TABLE `consumption_thresholds`
    MODIFY COLUMN `scope_type` ENUM('campus', 'office', 'appliance') NOT NULL;

-- 6. Finally drop the removed organizational tables.
DROP TABLE IF EXISTS `buildings`;
DROP TABLE IF EXISTS `departments`;

SET FOREIGN_KEY_CHECKS = 1;
