CREATE TABLE
	IF NOT EXISTS `featherpanel_billingresourcesstore_resource_packages` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`name` VARCHAR(255) NOT NULL COMMENT 'Package name',
		`description` TEXT COMMENT 'Package description',
		`memory_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Memory limit in MB',
		`cpu_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'CPU limit in percentage',
		`disk_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Disk limit in MB',
		`server_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Server limit',
		`database_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Database limit',
		`backup_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Backup limit',
		`allocation_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Allocation limit',
		`price` INT (11) NOT NULL DEFAULT 0 COMMENT 'Price in credits',
		`enabled` TINYINT (1) NOT NULL DEFAULT 1 COMMENT 'Whether package is enabled',
		`sort_order` INT (11) NOT NULL DEFAULT 0 COMMENT 'Sort order for display',
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		KEY `idx_enabled` (`enabled`),
		KEY `idx_sort_order` (`sort_order`)
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE
	IF NOT EXISTS `featherpanel_billingresourcesstore_purchases` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`package_id` INT (11) NOT NULL,
		`price` INT (11) NOT NULL COMMENT 'Price paid in credits',
		`memory_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Memory limit purchased',
		`cpu_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'CPU limit purchased',
		`disk_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Disk limit purchased',
		`server_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Server limit purchased',
		`database_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Database limit purchased',
		`backup_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Backup limit purchased',
		`allocation_limit` INT (11) NOT NULL DEFAULT 0 COMMENT 'Allocation limit purchased',
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		KEY `idx_user_id` (`user_id`),
		KEY `idx_package_id` (`package_id`),
		KEY `idx_created_at` (`created_at`),
		CONSTRAINT `billingresourcesstore_purchases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE,
		CONSTRAINT `billingresourcesstore_purchases_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `featherpanel_billingresourcesstore_resource_packages` (`id`) ON DELETE RESTRICT
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
