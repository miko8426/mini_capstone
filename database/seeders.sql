-- Eco Metrics Seeders File
-- Demonstration data. Every seeded account uses the password: Password123!
USE `eco_metrics_db`;

-- 1. ROLES SEED
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin', 'System administrator with full system access'),
(2, 'Campus Manager', 'Manages one campus, its offices, thresholds and reports'),
(3, 'Department Auditor', 'Manages authorized appliances and usage for one office'),
(4, 'Viewer', 'Read-only access to an assigned office');

-- 2. CAMPUSES SEED
INSERT INTO `campuses` (`id`, `name`, `code`, `address`) VALUES
(1, 'CHMSU_TALISAY(MAIN-CAMPUS)', 'MC', 'Talisay City, Negros Occidental'),
(2, 'CHMSU_FORTUNE-TOWNE', 'FT', 'Estifania, Bacolod City, Negros Occidental'),
(3, 'CHMSU_ALIJIS', 'AJ', 'Alijis, Bacolod City, Negros Occidental'),
(4, 'CHMSU-BINALBAGAN', 'BLB', 'Binalbagan, Negros Occidental');

-- 3. OFFICES SEED
INSERT INTO `offices` (`id`, `campus_id`, `room_number`, `name`, `floor_level`) VALUES
(1, 1, 'DO-101', 'Dean Office', 1),
(2, 2, 'ADM-201', 'Dean Office', 1),
(3, 2, 'ICTO-302', 'ICT_OFFICE', 1);

-- 4. USERS SEED (password is 'Password123!')
INSERT INTO `users`
    (`id`, `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `status`,
     `campus_id`, `office_id`)
VALUES
(1, 1, 'Admin', 'User', 'admin@ecometrics.edu', '$2y$12$m2iaXqebULruQCHeUmJHHe.h3slbYHNlvSeIHEZnatSysP82kvmSq', 'active', NULL, NULL),
(2, 2, 'Maria', 'Santos', 'maria.santos@ecometrics.edu', '$2y$12$m2iaXqebULruQCHeUmJHHe.h3slbYHNlvSeIHEZnatSysP82kvmSq', 'active', 1, NULL),
(3, 3, 'Juan', 'Dela Cruz', 'juan.delacruz@ecometrics.edu', '$2y$12$m2iaXqebULruQCHeUmJHHe.h3slbYHNlvSeIHEZnatSysP82kvmSq', 'active', 1, 1),
(4, 4, 'Jane', 'Doe', 'jane.doe@ecometrics.edu', '$2y$12$m2iaXqebULruQCHeUmJHHe.h3slbYHNlvSeIHEZnatSysP82kvmSq', 'active', 2, 3);

-- 5. USER PERMISSIONS SEED
INSERT INTO `user_permissions` (`user_id`, `permission_key`) VALUES
(1, 'manage_users'),
(1, 'manage_settings'),
(1, 'manage_rates'),
(2, 'manage_campuses'),
(3, 'input_usage');

-- 6. APPLIANCE CATEGORIES SEED
INSERT INTO `appliance_categories` (`id`, `name`, `description`) VALUES
(1, 'Air Conditioning & Cooling', 'HVAC systems, split-type, and window ACs'),
(2, 'Computers & IT Equipment', 'Desktops, servers, laptops, and monitors'),
(3, 'Lighting', 'LED tubes, fluorescent lamps, and floodlights'),
(4, 'Refrigeration & Kitchen', 'Refrigerators, water dispensers, and microwaves');

-- 7. APPLIANCES SEED
INSERT INTO `appliances` (`id`, `category_id`, `office_id`, `name`, `brand_model`, `voltage`, `wattage`, `quantity`, `daily_operating_hours`, `usage_frequency`, `status`) VALUES
(1, 1, 1, 'Split AC 2HP', 'Carrier Inverter', 220.00, 1800.00, 2, 8.00, 'daily', 'active'),
(2, 2, 3, 'Desktop Workstation PC', 'Dell OptiPlex 7090', 220.00, 250.00, 30, 9.50, 'daily', 'active'),
(3, 3, 2, 'LED Ceiling Panel 40W', 'Philips MasterLED', 220.00, 40.00, 10, 10.00, 'daily', 'active'),
(4, 4, 2, 'Water Dispenser', 'Hanabishi Hot/Cold', 220.00, 500.00, 1, 6.00, 'daily', 'active');

-- 8. APPLIANCE USAGE RECORDS SEED
INSERT INTO `appliance_usage_records` (`id`, `appliance_id`, `recorded_by`, `hours_per_day`, `days_per_week`, `usage_date`) VALUES
(1, 1, 3, 8.00, 5.0, '2026-10-01'),
(2, 2, 3, 9.50, 5.0, '2026-10-01'),
(3, 3, 2, 10.00, 6.0, '2026-10-01');

-- 9. ELECTRICITY RATES SEED (effective-dated; history keeps its applied rate)
INSERT INTO `electricity_rates` (`id`, `rate_per_kwh`, `currency`, `effective_date`, `is_active`) VALUES
(1, 11.8500, 'PHP', '2026-01-01', 1),
(2, 12.5000, 'PHP', '2026-11-01', 1);

-- 10. EMISSION FACTORS SEED
INSERT INTO `emission_factors` (`id`, `factor_name`, `kg_co2_per_kwh`, `source`, `effective_date`, `is_active`) VALUES
(1, 'Luzon-Visayas Grid Emission Factor', 0.712200, 'DOE Philippines GHG Grid Report', '2026-01-01', 1),
(2, 'Updated Philippine Grid Factor 2026', 0.650000, 'DOE Philippines GHG Grid Report', '2026-11-01', 1);

-- 11. CONSUMPTION RECORDS SEED (manual campus-level meter readings)
INSERT INTO `consumption_records` (`id`, `campus_id`, `recorded_by`, `billing_period_start`, `billing_period_end`, `total_kwh`, `total_cost`, `total_co2_kg`) VALUES
(1, 1, 2, '2026-09-01', '2026-09-30', 14500.00, 171825.00, 10326.90),
(2, 2, 2, '2026-09-01', '2026-09-30', 11200.00, 132720.00, 7976.64);

-- 12. CONSUMPTION THRESHOLDS SEED (administrator configurable, any scope)
INSERT INTO `consumption_thresholds` (`id`, `scope_type`, `scope_id`, `monthly_kwh_limit`, `alert_percentage`) VALUES
(1, 'campus', 1, 15000.00, 85.00),
(2, 'campus', 2, 10000.00, 80.00),
(3, 'office', 1, 2500.00, 80.00),
(4, 'appliance', 2, 2000.00, 90.00);

-- 13. NOTIFICATIONS SEED
INSERT INTO `notifications` (`id`, `user_id`, `threshold_id`, `period_key`, `title`, `message`, `type`, `is_read`) VALUES
(1, 1, 2, '2026-09', 'Consumption Threshold Exceeded', 'Information Technology Center exceeded 80% of its monthly energy limit for 2026-09.', 'warning', 0),
(2, 2, 2, '2026-09', 'Consumption Threshold Exceeded', 'Information Technology Center exceeded 80% of its monthly energy limit for 2026-09.', 'warning', 0);

-- 14. IMMUTABLE CONSUMPTION SNAPSHOTS SEED
INSERT INTO `consumption_snapshots`
    (`id`, `appliance_id`, `office_id`, `recorded_by`, `period_type`, `period_start`, `period_end`,
     `appliance_name`, `category_name`, `campus_name`, `office_name`,
     `quantity`, `wattage`, `daily_operating_hours`, `days_in_period`, `total_kwh`,
     `rate_per_kwh`, `electricity_cost`, `emission_factor`, `co2_kg`, `notes`)
VALUES
(1, 1, 1, 2, 'monthly', '2026-07-01', '2026-07-31', 'Split AC 2HP', 'Air Conditioning & Cooling', 'CHMSU_TALISAY(MAIN-CAMPUS)', 'Dean Office', 2, 1800.00, 8.00, 31, 892.8000, 11.8500, 10579.68, 0.712200, 635.85, 'Monthly snapshot'),
(2, 2, 3, 2, 'monthly', '2026-07-01', '2026-07-31', 'Desktop Workstation PC', 'Computers & IT Equipment', 'CHMSU_FORTUNE-TOWNE', 'ICT_OFFICE', 30, 250.00, 9.50, 31, 2208.7500, 11.8500, 26173.69, 0.712200, 1573.07, 'Monthly snapshot'),
(3, 3, 2, 2, 'monthly', '2026-07-01', '2026-07-31', 'LED Ceiling Panel 40W', 'Lighting', 'CHMSU_FORTUNE-TOWNE', 'Dean Office', 10, 40.00, 10.00, 31, 124.0000, 11.8500, 1469.40, 0.712200, 88.31, 'Monthly snapshot'),
(4, 4, 2, 2, 'monthly', '2026-07-01', '2026-07-31', 'Water Dispenser', 'Refrigeration & Kitchen', 'CHMSU_FORTUNE-TOWNE', 'Dean Office', 1, 500.00, 6.00, 31, 93.0000, 11.8500, 1102.05, 0.712200, 66.23, 'Monthly snapshot'),
(5, 1, 1, 2, 'monthly', '2026-08-01', '2026-08-31', 'Split AC 2HP', 'Air Conditioning & Cooling', 'CHMSU_TALISAY(MAIN-CAMPUS)', 'Dean Office', 2, 1800.00, 8.00, 31, 892.8000, 11.8500, 10579.68, 0.712200, 635.85, 'Monthly snapshot'),
(6, 2, 3, 2, 'monthly', '2026-08-01', '2026-08-31', 'Desktop Workstation PC', 'Computers & IT Equipment', 'CHMSU_FORTUNE-TOWNE', 'ICT_OFFICE', 30, 250.00, 9.50, 31, 2208.7500, 11.8500, 26173.69, 0.712200, 1573.07, 'Monthly snapshot'),
(7, 3, 2, 2, 'monthly', '2026-08-01', '2026-08-31', 'LED Ceiling Panel 40W', 'Lighting', 'CHMSU_FORTUNE-TOWNE', 'Dean Office', 10, 40.00, 10.00, 31, 124.0000, 11.8500, 1469.40, 0.712200, 88.31, 'Monthly snapshot'),
(8, 4, 2, 2, 'monthly', '2026-08-01', '2026-08-31', 'Water Dispenser', 'Refrigeration & Kitchen', 'CHMSU_FORTUNE-TOWNE', 'Dean Office', 1, 500.00, 6.00, 31, 93.0000, 11.8500, 1102.05, 0.712200, 66.23, 'Monthly snapshot'),
(9, 1, 1, 2, 'monthly', '2026-09-01', '2026-09-30', 'Split AC 2HP', 'Air Conditioning & Cooling', 'CHMSU_TALISAY(MAIN-CAMPUS)', 'Dean Office', 2, 1800.00, 8.00, 30, 864.0000, 11.8500, 10238.40, 0.712200, 615.34, 'Monthly snapshot'),
(10, 2, 3, 2, 'monthly', '2026-09-01', '2026-09-30', 'Desktop Workstation PC', 'Computers & IT Equipment', 'CHMSU_FORTUNE-TOWNE', 'ICT_OFFICE', 30, 250.00, 9.50, 30, 2137.5000, 11.8500, 25329.38, 0.712200, 1522.33, 'Monthly snapshot'),
(11, 3, 2, 2, 'monthly', '2026-09-01', '2026-09-30', 'LED Ceiling Panel 40W', 'Lighting', 'CHMSU_FORTUNE-TOWNE', 'Dean Office', 10, 40.00, 10.00, 30, 120.0000, 11.8500, 1422.00, 0.712200, 85.46, 'Monthly snapshot'),
(12, 4, 2, 2, 'monthly', '2026-09-01', '2026-09-30', 'Water Dispenser', 'Refrigeration & Kitchen', 'CHMSU_FORTUNE-TOWNE', 'Dean Office', 1, 500.00, 6.00, 30, 90.0000, 11.8500, 1066.50, 0.712200, 64.10, 'Monthly snapshot'),
(13, 1, 1, 2, 'monthly', '2026-10-01', '2026-10-31', 'Split AC 2HP', 'Air Conditioning & Cooling', 'CHMSU_TALISAY(MAIN-CAMPUS)', 'Dean Office', 2, 1800.00, 8.00, 31, 892.8000, 11.8500, 10579.68, 0.712200, 635.85, 'Monthly snapshot'),
(14, 2, 3, 2, 'monthly', '2026-10-01', '2026-10-31', 'Desktop Workstation PC', 'Computers & IT Equipment', 'CHMSU_FORTUNE-TOWNE', 'ICT_OFFICE', 30, 250.00, 9.50, 31, 2208.7500, 11.8500, 26173.69, 0.712200, 1573.07, 'Monthly snapshot'),
(15, 3, 2, 2, 'monthly', '2026-10-01', '2026-10-31', 'LED Ceiling Panel 40W', 'Lighting', 'CHMSU_FORTUNE-TOWNE', 'Dean Office', 10, 40.00, 10.00, 31, 124.0000, 11.8500, 1469.40, 0.712200, 88.31, 'Monthly snapshot'),
(16, 4, 2, 2, 'monthly', '2026-10-01', '2026-10-31', 'Water Dispenser', 'Refrigeration & Kitchen', 'CHMSU_FORTUNE-TOWNE', 'Dean Office', 1, 500.00, 6.00, 31, 93.0000, 11.8500, 1102.05, 0.712200, 66.23, 'Monthly snapshot');

-- Snapshot hierarchy ids (kept stable for filtering/reporting)
UPDATE `consumption_snapshots` s
JOIN `appliances` a ON a.id = s.appliance_id
JOIN `offices` o ON o.id = a.office_id
JOIN `campuses` cp ON cp.id = o.campus_id
SET s.campus_id = o.campus_id,
    s.office_id = o.id,
    s.campus_name = cp.name,
    s.office_name = o.name
WHERE s.appliance_id IS NOT NULL;

-- 15. AUDIT LOGS SEED
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity`, `entity_id`, `details`, `ip_address`) VALUES
(1, 1, 'CREATE', 'users', 2, 'Created user Maria Santos', '127.0.0.1');

-- 16. SYSTEM SETTINGS SEED
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`) VALUES
(1, 'site_name', 'Eco Metrics Portal', 'Application Title'),
(2, 'institution_name', 'Eco Metrics University', 'Institution name printed on reports'),
(3, 'default_currency', 'PHP', 'System Default Currency Code'),
(4, 'carbon_offset_cost_per_ton', '500.00', 'Estimated PHP cost to offset 1 metric ton of CO2');
