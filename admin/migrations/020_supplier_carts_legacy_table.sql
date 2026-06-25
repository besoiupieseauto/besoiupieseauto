-- ============================================================
-- Tabel coș furnizori — BD legacy (compatibil Laravel ERP)
-- Rulează pe baza LEGACY_DB_* (caiet comenzi).
-- ============================================================

CREATE TABLE IF NOT EXISTS `supplier_carts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `cart` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_supplier_carts_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
