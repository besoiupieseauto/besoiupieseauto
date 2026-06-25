-- Extensii comenzi: cupon, plată online

CREATE TABLE IF NOT EXISTS `payment_sessions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_randomn_id` INT UNSIGNED NULL DEFAULT NULL,
    `provider` VARCHAR(40) NOT NULL DEFAULT 'stub',
    `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency` CHAR(3) NOT NULL DEFAULT 'RON',
    `reference` VARCHAR(64) NOT NULL,
    `payload_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_payment_sessions_reference` (`reference`),
    KEY `idx_payment_sessions_order` (`order_randomn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `queue_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(60) NOT NULL DEFAULT 'default',
    `job_type` VARCHAR(80) NOT NULL,
    `payload_json` JSON NOT NULL,
    `status` ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reserved_at` DATETIME NULL DEFAULT NULL,
    `last_error` VARCHAR(500) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_queue_jobs_poll` (`queue`, `status`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
