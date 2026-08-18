-- Bază dedicată imagini Autopartner (index fișiere + lookup coduri).
-- Fișierele JPG rămân pe disc; în MySQL stocăm calea, indexul și aliasurile de căutare.

CREATE DATABASE IF NOT EXISTS `autopartner_images`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `autopartner_images`;

DROP TABLE IF EXISTS `lookup`;
DROP TABLE IF EXISTS `images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `meta`;

CREATE TABLE `images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ap_index` VARCHAR(160) NOT NULL,
  `ap_index_norm` VARCHAR(128) NOT NULL,
  `folder` ENUM('zdjecia','miesieczne','rooks','root') NOT NULL DEFAULT 'zdjecia',
  `file_name` VARCHAR(255) NOT NULL,
  `rel_path` VARCHAR(512) NOT NULL,
  `seq` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rel_path` (`rel_path`),
  KEY `idx_ap_index_norm` (`ap_index_norm`),
  KEY `idx_ap_index` (`ap_index`),
  KEY `idx_folder_norm_seq` (`folder`, `ap_index_norm`, `seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lookup` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `norm_code` VARCHAR(128) NOT NULL,
  `ap_index` VARCHAR(160) NOT NULL,
  `ap_index_norm` VARCHAR(128) NOT NULL,
  `source` ENUM('filename','catalog_ap','catalog_tecdoc','ean','brand_code') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_norm_ap_src` (`norm_code`, `ap_index_norm`, `source`),
  KEY `idx_norm_code` (`norm_code`),
  KEY `idx_ap_index_norm` (`ap_index_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `ap_index` VARCHAR(160) NOT NULL,
  `ap_index_norm` VARCHAR(128) NOT NULL,
  `tecdoc_index` VARCHAR(160) DEFAULT NULL,
  `tecdoc_index_norm` VARCHAR(128) DEFAULT NULL,
  `name` VARCHAR(512) DEFAULT NULL,
  `brand_code` VARCHAR(32) DEFAULT NULL,
  `tecdoc_brand_code` VARCHAR(32) DEFAULT NULL,
  `ean` VARCHAR(32) DEFAULT NULL,
  `price` DECIMAL(10,2) DEFAULT NULL,
  `currency` CHAR(3) DEFAULT 'RON',
  `stock` DECIMAL(12,3) DEFAULT NULL,
  `image_count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`ap_index`),
  KEY `idx_ap_index_norm` (`ap_index_norm`),
  KEY `idx_tecdoc_norm` (`tecdoc_index_norm`),
  KEY `idx_ean` (`ean`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `meta` (
  `k` VARCHAR(64) NOT NULL,
  `v` TEXT NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
