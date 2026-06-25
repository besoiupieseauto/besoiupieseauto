-- Cache DB pentru răspunsuri RapidAPI TecDoc (reduce apeluri repetate)

CREATE TABLE IF NOT EXISTS `tecdoc_api_cache` (
    `cache_key` CHAR(64) NOT NULL,
    `url` VARCHAR(768) NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`cache_key`),
    KEY `idx_tecdoc_api_cache_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
