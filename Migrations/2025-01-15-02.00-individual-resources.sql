CREATE TABLE
	IF NOT EXISTS `featherpanel_billingresourcesstore_individual_resources` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`name` VARCHAR(255) NOT NULL COMMENT 'Display name (e.g., "RAM", "CPU")',
		`description` TEXT COMMENT 'Description of the resource',
		`resource_type` VARCHAR(50) NOT NULL COMMENT 'Resource type (memory_limit, cpu_limit, etc.)',
		`unit` VARCHAR(20) NOT NULL DEFAULT 'MB' COMMENT 'Unit of measurement (MB, GB, %, count)',
		`price_per_unit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Price per unit in credits',
		`minimum_amount` INT (11) NOT NULL DEFAULT 1 COMMENT 'Minimum purchase amount',
		`maximum_amount` INT (11) NULL COMMENT 'Maximum purchase amount (NULL for unlimited)',
		`discount_percentage` DECIMAL(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Discount percentage (0-100)',
		`discount_start_date` DATETIME NULL COMMENT 'Discount start date',
		`discount_end_date` DATETIME NULL COMMENT 'Discount end date',
		`discount_enabled` TINYINT (1) NOT NULL DEFAULT 0 COMMENT 'Whether discount is enabled',
		`enabled` TINYINT (1) NOT NULL DEFAULT 1 COMMENT 'Whether this resource is enabled',
		`sort_order` INT (11) NOT NULL DEFAULT 0 COMMENT 'Sort order for display',
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		KEY `idx_enabled` (`enabled`),
		KEY `idx_resource_type` (`resource_type`),
		KEY `idx_sort_order` (`sort_order`)
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


