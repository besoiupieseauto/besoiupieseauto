-- Coș server-side (sesiune magazin)

CREATE TABLE IF NOT EXISTS `cart_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(128) NOT NULL,
    `shop_customer_id` INT UNSIGNED NULL DEFAULT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `randomn_id` VARCHAR(32) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `product_snapshot` JSON NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cart_session_product` (`session_id`, `randomn_id`),
    KEY `idx_cart_session` (`session_id`),
    KEY `idx_cart_customer` (`shop_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
