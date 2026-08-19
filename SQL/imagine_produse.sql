CREATE DATABASE IF NOT EXISTS `imagine_produse`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `imagine_produse`;

DROP TABLE IF EXISTS `images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `meta`;

CREATE TABLE `products` (
  `code_norm` VARCHAR(128) NOT NULL,
  `brand` VARCHAR(64) NOT NULL DEFAULT '',
  `code_a` VARCHAR(160) DEFAULT NULL,
  `code_c` VARCHAR(160) DEFAULT NULL,
  `a_norm` VARCHAR(128) DEFAULT NULL,
  `name` VARCHAR(512) DEFAULT NULL,
  `price` DECIMAL(10,2) DEFAULT NULL,
  `currency` CHAR(3) DEFAULT 'RON',
  `image_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `tecdoc_match` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`brand`, `code_norm`),
  KEY `idx_code_norm` (`code_norm`),
  KEY `idx_a_norm` (`a_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `brand` VARCHAR(64) NOT NULL DEFAULT '',
  `code_norm` VARCHAR(128) NOT NULL,
  `code_after_strip` VARCHAR(160) DEFAULT NULL,
  `original_file` VARCHAR(255) NOT NULL,
  `disk_name` VARCHAR(255) NOT NULL,
  `rel_path` VARCHAR(512) NOT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'catalog_A',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disk_name` (`disk_name`),
  KEY `idx_brand_code` (`brand`, `code_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `meta` (
  `k` VARCHAR(64) NOT NULL,
  `v` TEXT NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
