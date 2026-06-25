-- ============================================================
-- MIGRARE: Livrare curier Da/Nu pe produse (tm_042)
-- ============================================================

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pCurierLivrare'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pCurierLivrare` VARCHAR(8) NOT NULL DEFAULT ''Da'' COMMENT ''Livrare curier: Da sau Nu'' AFTER `pShipping`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pCurierLivrare'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pCurierLivrare` VARCHAR(8) NOT NULL DEFAULT ''Da'' COMMENT ''Livrare curier: Da sau Nu'' AFTER `pShipping`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
