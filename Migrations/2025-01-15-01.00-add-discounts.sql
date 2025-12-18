-- Add discount fields to packages table
ALTER TABLE `featherpanel_billingresourcesstore_resource_packages`
ADD COLUMN `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Package-specific discount percentage' AFTER `price`,
ADD COLUMN `discount_start_date` DATETIME NULL COMMENT 'Discount start date' AFTER `discount_percentage`,
ADD COLUMN `discount_end_date` DATETIME NULL COMMENT 'Discount end date' AFTER `discount_start_date`,
ADD COLUMN `discount_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether package discount is enabled' AFTER `discount_end_date`;


