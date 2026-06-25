-- ============================================================
-- MIGRARE: Extinde tabela furnizori existenta (schema vechi)
-- ============================================================

ALTER TABLE `furnizori`
    ADD COLUMN `randomn_id` INT UNSIGNED NULL AFTER `id`,
    ADD COLUMN `code` VARCHAR(50) NULL AFTER `name`,
    ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `code`,
    ADD COLUMN `price_markup_type` VARCHAR(20) NOT NULL DEFAULT 'percentage' AFTER `status`,
    ADD COLUMN `price_markup_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `price_markup_type`,
    ADD COLUMN `price_round_to` DECIMAL(10,2) NULL AFTER `price_markup_value`,
    ADD COLUMN `price_min_margin` DECIMAL(10,2) NULL AFTER `price_round_to`,
    ADD COLUMN `stock_zero_mode` VARCHAR(30) NOT NULL DEFAULT 'full' AFTER `price_min_margin`,
    ADD COLUMN `scan_include_zero_stock` TINYINT(1) NOT NULL DEFAULT 1 AFTER `stock_zero_mode`,
    ADD COLUMN `scan_skip_unavailable` TINYINT(1) NOT NULL DEFAULT 0 AFTER `scan_include_zero_stock`,
    ADD COLUMN `connection_type` VARCHAR(20) NOT NULL DEFAULT 'ftp' AFTER `scan_skip_unavailable`,
    ADD COLUMN `scan_interval_minutes` INT UNSIGNED NOT NULL DEFAULT 60 AFTER `connection_type`,
    ADD COLUMN `conn_host` VARCHAR(255) NULL AFTER `scan_interval_minutes`,
    ADD COLUMN `conn_port` INT UNSIGNED NULL AFTER `conn_host`,
    ADD COLUMN `conn_username` VARCHAR(255) NULL AFTER `conn_port`,
    ADD COLUMN `conn_password` TEXT NULL AFTER `conn_username`,
    ADD COLUMN `conn_remote_path` VARCHAR(500) NULL AFTER `conn_password`,
    ADD COLUMN `conn_passive` TINYINT(1) NOT NULL DEFAULT 1 AFTER `conn_remote_path`,
    ADD COLUMN `conn_email` VARCHAR(255) NULL AFTER `conn_passive`,
    ADD COLUMN `conn_email_inbox` VARCHAR(255) NULL AFTER `conn_email`,
    ADD COLUMN `conn_imap_host` VARCHAR(255) NULL AFTER `conn_email_inbox`,
    ADD COLUMN `conn_imap_port` INT UNSIGNED NULL DEFAULT 993 AFTER `conn_imap_host`,
    ADD COLUMN `conn_email_password` TEXT NULL AFTER `conn_imap_port`,
    ADD COLUMN `conn_create_inbox` TINYINT(1) NOT NULL DEFAULT 0 AFTER `conn_email_password`,
    ADD COLUMN `api_base_url` VARCHAR(500) NULL AFTER `conn_create_inbox`,
    ADD COLUMN `api_token` TEXT NULL AFTER `api_base_url`,
    ADD COLUMN `last_scan_at` DATETIME NULL AFTER `api_token`,
    ADD COLUMN `last_scan_status` VARCHAR(30) NULL AFTER `last_scan_at`,
    ADD COLUMN `last_scan_message` VARCHAR(500) NULL AFTER `last_scan_status`,
    ADD COLUMN `last_test_status` VARCHAR(30) NULL AFTER `last_scan_message`,
    ADD COLUMN `last_test_message` VARCHAR(500) NULL AFTER `last_test_status`,
    ADD COLUMN `last_test_at` DATETIME NULL AFTER `last_test_message`,
    ADD COLUMN `products_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_test_at`,
    ADD COLUMN `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

UPDATE `furnizori` SET `randomn_id` = 600000 + `id` WHERE `randomn_id` IS NULL;

ALTER TABLE `furnizori`
    MODIFY `randomn_id` INT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uq_furnizori_randomn_id` (`randomn_id`);
