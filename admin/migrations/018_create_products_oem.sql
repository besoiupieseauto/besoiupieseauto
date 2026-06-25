-- ============================================================
-- MIGRARE: Tabel products_oem (cross-reference OEM)
-- ============================================================

CREATE TABLE IF NOT EXISTS `products_oem` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `oem_code` VARCHAR(80) NOT NULL,
    `oem_norm` VARCHAR(80) NOT NULL,
    `brand` VARCHAR(120) NULL DEFAULT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `source` VARCHAR(40) NOT NULL DEFAULT 'import',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_products_oem_product_norm` (`product_id`, `oem_norm`),
    KEY `idx_products_oem_norm` (`oem_norm`),
    KEY `idx_products_oem_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
