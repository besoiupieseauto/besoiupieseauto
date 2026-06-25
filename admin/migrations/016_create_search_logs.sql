-- Jurnal căutări VIN/OEM negăsite (Sprint 1 — extindere stoc)

CREATE TABLE IF NOT EXISTS `search_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `query_type` ENUM('vin', 'oem', 'name') NOT NULL DEFAULT 'vin',
    `query_value` VARCHAR(128) NOT NULL,
    `found` TINYINT(1) NOT NULL DEFAULT 0,
    `car_id` INT UNSIGNED NULL DEFAULT NULL,
    `vehicle_label` VARCHAR(255) NULL DEFAULT NULL,
    `result_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `notice` VARCHAR(500) NULL DEFAULT NULL,
    `ip_hash` VARCHAR(64) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_search_logs_type_value` (`query_type`, `query_value`),
    KEY `idx_search_logs_found_created` (`found`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
