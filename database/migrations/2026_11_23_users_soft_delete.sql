-- Recycle bin support for user accounts.
-- Accounts deleted from User Management are flagged here instead of removed,
-- so they can be restored or permanently deleted from the Recycle Bin.
ALTER TABLE `users`
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD INDEX `idx_users_deleted` (`deleted_at`);
