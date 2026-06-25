-- ============================================================
-- MIGRARE: badge vizibil pe cartelele de produs (HOT, NOU, etc.)
-- ============================================================

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pBadge'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pBadge` VARCHAR(20) DEFAULT NULL AFTER `pMarkupAppliedAt`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pBadge'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pBadge` VARCHAR(20) DEFAULT NULL AFTER `pMarkupAppliedAt`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
