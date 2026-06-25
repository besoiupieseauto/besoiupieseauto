-- ============================================================
-- MIGRARE: chei de identitate produs (cod + brand normalizat)
-- ============================================================

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pCodeNorm'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pCodeNorm` VARCHAR(80) DEFAULT NULL AFTER `pCode`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pBrandNorm'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pBrandNorm` VARCHAR(120) DEFAULT NULL AFTER `pCodeNorm`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND INDEX_NAME = 'idx_produse_code_brand'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD INDEX `idx_produse_code_brand` (`pCodeNorm`, `pBrandNorm`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `produse`
SET
    `pCodeNorm` = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`pCode`), ' ', ''), '-', ''), '.', ''), '/', '')),
    `pBrandNorm` = UPPER(TRIM(`pBrand`))
WHERE (`pCodeNorm` IS NULL OR `pCodeNorm` = '')
  AND TRIM(`pCode`) <> '';

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pCodeNorm'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pCodeNorm` VARCHAR(80) DEFAULT NULL AFTER `pCode`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pBrandNorm'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pBrandNorm` VARCHAR(120) DEFAULT NULL AFTER `pCodeNorm`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'conflict_product_id'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `conflict_product_id` INT DEFAULT NULL AFTER `imported_product_id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'conflict_reason'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `conflict_reason` VARCHAR(50) DEFAULT NULL AFTER `conflict_product_id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND INDEX_NAME = 'idx_import_code_brand_status'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD INDEX `idx_import_code_brand_status` (`pCodeNorm`, `pBrandNorm`, `status`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `import_produse`
SET
    `pCodeNorm` = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`pCode`), ' ', ''), '-', ''), '.', ''), '/', '')),
    `pBrandNorm` = UPPER(TRIM(`pBrand`))
WHERE (`pCodeNorm` IS NULL OR `pCodeNorm` = '')
  AND TRIM(`pCode`) <> '';
