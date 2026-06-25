-- Coșuri abandonate — lead-uri checkout + sesiuni cu produse în coș

CREATE TABLE IF NOT EXISTS `cart_abandonments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(128) NOT NULL DEFAULT '',
    `client_name` VARCHAR(160) NOT NULL DEFAULT '',
    `phone` VARCHAR(50) NOT NULL DEFAULT '',
    `email` VARCHAR(255) NOT NULL DEFAULT '',
    `cart_json` JSON NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `items_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `checkout_step` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `status` ENUM('open', 'contacted', 'converted', 'dismissed') NOT NULL DEFAULT 'open',
    `notes` TEXT NULL,
    `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cart_abandon_status` (`status`, `last_seen_at`),
    KEY `idx_cart_abandon_phone` (`phone`),
    KEY `idx_cart_abandon_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
