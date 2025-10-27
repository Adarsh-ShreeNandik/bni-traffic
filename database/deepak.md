<!-- 16-10-2025 -->
ALTER TABLE `users`
ADD COLUMN `phone` VARCHAR(100) NOT NULL AFTER `email`,
ADD COLUMN `join_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() AFTER `phone`,
ADD COLUMN `first_name` VARCHAR(100) NOT NULL AFTER `join_date`,
ADD COLUMN `last_name` VARCHAR(100) NOT NULL AFTER `first_name`,
ADD COLUMN `chapter` VARCHAR(100) NOT NULL AFTER `last_name`,
ADD COLUMN `region_name` VARCHAR(100) NOT NULL AFTER `chapter`,
ADD COLUMN `role_id` INT(10) NOT NULL AFTER `region_name`;

ALTER TABLE `relevants` ADD `first_name` VARCHAR(100) NOT NULL AFTER `updated_at`, ADD `last_name` VARCHAR(100) NOT NULL AFTER `first_name`;

ALTER TABLE `relevants` ADD `targeted_date` DATE NULL AFTER `last_name`;

<!-- 27-10-2025 -->
ALTER TABLE `users` ADD `gst` VARCHAR(100) NULL AFTER `updated_at`, ADD `company_name` VARCHAR(100) NULL AFTER `gst`, ADD `address` VARCHAR(255) NULL AFTER `company_name`;