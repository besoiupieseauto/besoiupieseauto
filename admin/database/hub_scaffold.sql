-- Schema referință: tabele hub scaffold (EvaSystem admin hub)
-- Migrare 037: cross_reference, search_logs_scaffold (fără cratimă)

CREATE TABLE IF NOT EXISTS `alerts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `status` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scan` LIKE `alerts`;
CREATE TABLE IF NOT EXISTS `cron` LIKE `alerts`;
CREATE TABLE IF NOT EXISTS `report` LIKE `alerts`;
CREATE TABLE IF NOT EXISTS `settings` LIKE `alerts`;

CREATE TABLE IF NOT EXISTS `cross_reference` LIKE `alerts`;
CREATE TABLE IF NOT EXISTS `search_logs_scaffold` LIKE `alerts`;
