CREATE DATABASE IF NOT EXISTS `imagine_autototal`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `imagine_autototal`;

DROP TABLE IF EXISTS `aliases`;
DROP TABLE IF EXISTS `images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `meta`;

CREATE TABLE `products` (
  `code_norm` VARCHAR(128) NOT NULL,
  `brand` VARCHAR(64) NOT NULL DEFAULT '',
  `code_raw` VARCHAR(160) DEFAULT NULL,
  `itemkey` VARCHAR(160) DEFAULT NULL,
  `name` VARCHAR(512) DEFAULT NULL,
  `price` DECIMAL(12,2) DEFAULT NULL,
  `image_count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`brand`, `code_norm`),
  KEY `idx_code_norm` (`code_norm`),
  KEY `idx_itemkey` (`itemkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `brand` VARCHAR(64) NOT NULL DEFAULT '',
  `code_norm` VARCHAR(128) NOT NULL,
  `code_raw` VARCHAR(160) DEFAULT NULL,
  `original_url` VARCHAR(768) NOT NULL,
  `disk_name` VARCHAR(255) NOT NULL,
  `rel_path` VARCHAR(512) NOT NULL,
  `downloaded` TINYINT(1) NOT NULL DEFAULT 0,
  `source` VARCHAR(32) NOT NULL DEFAULT 'autotal',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rel_path` (`rel_path`),
  KEY `idx_brand_code` (`brand`, `code_norm`),
  KEY `idx_code_norm` (`code_norm`),
  KEY `idx_downloaded` (`downloaded`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aliases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `alias_brand` VARCHAR(64) NOT NULL DEFAULT '',
  `alias_code_norm` VARCHAR(128) NOT NULL,
  `brand` VARCHAR(64) NOT NULL DEFAULT '',
  `code_norm` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`alias_brand`, `alias_code_norm`, `brand`, `code_norm`),
  KEY `idx_alias_code` (`alias_code_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `meta` (
  `k` VARCHAR(64) NOT NULL,
  `v` TEXT NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
