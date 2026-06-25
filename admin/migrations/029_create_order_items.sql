-- ============================================================
-- MIGRARE: Linii comandă structurate (checkout site)
-- ============================================================

CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT NOT NULL,
    `product_id` INT UNSIGNED NULL DEFAULT NULL,
    `randomn_id` VARCHAR(32) NULL DEFAULT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `product_image` VARCHAR(500) NULL DEFAULT NULL,
    `oem_code` VARCHAR(100) NULL DEFAULT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order` (`order_id`),
    KEY `idx_order_items_product` (`product_id`),
    KEY `idx_order_items_randomn` (`randomn_id`),
    CONSTRAINT `fk_order_items_comenzi`
        FOREIGN KEY (`order_id`) REFERENCES `comenzi` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
