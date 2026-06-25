-- ============================================================
-- MIGRARE: campuri pentru pret baza si reguli adaos
-- Compatibila cu versiuni MySQL fara ADD COLUMN IF NOT EXISTS
-- ============================================================

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pBasePrice'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pBasePrice` DECIMAL(10,2) DEFAULT NULL AFTER `pPrice`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pMarkupRuleId'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pMarkupRuleId` INT DEFAULT NULL AFTER `pWhatsapp`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pMarkupRuleName'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pMarkupRuleName` VARCHAR(150) DEFAULT NULL AFTER `pMarkupRuleId`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pMarkupAppliedAt'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pMarkupAppliedAt` DATETIME DEFAULT NULL AFTER `pMarkupRuleName`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pBasePrice'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pBasePrice` DECIMAL(10,2) DEFAULT NULL AFTER `pPrice`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pMarkupRuleId'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pMarkupRuleId` INT DEFAULT NULL AFTER `pWhatsapp`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pMarkupRuleName'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pMarkupRuleName` VARCHAR(150) DEFAULT NULL AFTER `pMarkupRuleId`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pMarkupAppliedAt'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pMarkupAppliedAt` DATETIME DEFAULT NULL AFTER `pMarkupRuleName`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `produse`
SET `pBasePrice` = CAST(
    NULLIF(
        TRIM(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(LOWER(`pPrice`), 'lei', ''),
                    'ron', ''),
                ',', '.'),
            ' ', '')
        ),
        ''
    ) AS DECIMAL(10,2)
)
WHERE (`pBasePrice` IS NULL OR `pBasePrice` = 0)
  AND `pPrice` IS NOT NULL
  AND `pPrice` <> '';

UPDATE `import_produse`
SET `pBasePrice` = CAST(
    NULLIF(
        TRIM(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(LOWER(`pPrice`), 'lei', ''),
                    'ron', ''),
                ',', '.'),
            ' ', '')
        ),
        ''
    ) AS DECIMAL(10,2)
)
WHERE (`pBasePrice` IS NULL OR `pBasePrice` = 0)
  AND `pPrice` IS NOT NULL
  AND `pPrice` <> '';
