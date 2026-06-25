-- MIGRARE 030: Registru tabele legacy + note convenții SQL (11)
-- Nu redenumește tabele în producție — documentează excepțiile acceptate.

CREATE TABLE IF NOT EXISTS `schema_legacy_registry` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `table_name` VARCHAR(128) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `target_name` VARCHAR(128) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_schema_legacy_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_legacy_registry` (`table_name`, `reason`, `target_name`)
SELECT 'cross-reference', 'Generator CRUD vechi — cratimă în nume', 'cross_reference'
WHERE NOT EXISTS (SELECT 1 FROM `schema_legacy_registry` WHERE `table_name` = 'cross-reference');

INSERT INTO `schema_legacy_registry` (`table_name`, `reason`, `target_name`)
SELECT 'search-logs', 'Scaffold CRUD vechi — înlocuit de search_logs', 'search_logs'
WHERE NOT EXISTS (SELECT 1 FROM `schema_legacy_registry` WHERE `table_name` = 'search-logs');

-- View rapid pentru audit manual
CREATE OR REPLACE VIEW `v_schema_convention_gaps` AS
SELECT
    t.TABLE_NAME AS table_name,
    CASE WHEN t.TABLE_NAME REGEXP '[-A-Z]' THEN 1 ELSE 0 END AS has_non_standard_name,
    CASE WHEN c_created.COLUMN_NAME IS NULL THEN 1 ELSE 0 END AS missing_created_at,
    CASE WHEN c_updated.COLUMN_NAME IS NULL THEN 1 ELSE 0 END AS missing_updated_at,
    CASE WHEN lr.table_name IS NOT NULL THEN 1 ELSE 0 END AS is_legacy_exception
FROM information_schema.TABLES t
LEFT JOIN information_schema.COLUMNS c_created
    ON c_created.TABLE_SCHEMA = t.TABLE_SCHEMA
    AND c_created.TABLE_NAME = t.TABLE_NAME
    AND c_created.COLUMN_NAME = 'created_at'
LEFT JOIN information_schema.COLUMNS c_updated
    ON c_updated.TABLE_SCHEMA = t.TABLE_SCHEMA
    AND c_updated.TABLE_NAME = t.TABLE_NAME
    AND c_updated.COLUMN_NAME = 'updated_at'
LEFT JOIN `schema_legacy_registry` lr ON lr.table_name = t.TABLE_NAME AND lr.is_active = 1
WHERE t.TABLE_SCHEMA = DATABASE()
  AND t.TABLE_TYPE = 'BASE TABLE';
