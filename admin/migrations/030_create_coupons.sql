-- Cupoane reducere magazin

CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `discount_type` ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `valid_from` DATETIME NULL DEFAULT NULL,
    `valid_until` DATETIME NULL DEFAULT NULL,
    `max_uses` INT UNSIGNED NULL DEFAULT NULL,
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_coupons_code` (`code`),
    KEY `idx_coupons_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `coupons` (`code`, `discount_type`, `discount_value`, `min_order`, `is_active`)
SELECT 'BESOIU10', 'percent', 10.00, 100.00, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `coupons` WHERE `code` = 'BESOIU10' LIMIT 1);
