-- ============================================================
-- MIGRARE: Produse vitrină homepage (selective din admin)
-- ============================================================

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produse' AND COLUMN_NAME = 'pVitrina'
    ),
    'SELECT 1',
    'ALTER TABLE `produse` ADD COLUMN `pVitrina` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Afisare vitrina homepage'' AFTER `pBadge`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_produse' AND COLUMN_NAME = 'pVitrina'
    ),
    'SELECT 1',
    'ALTER TABLE `import_produse` ADD COLUMN `pVitrina` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Afisare vitrina homepage'' AFTER `pBadge`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/produse-selective', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/produse/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/produse-selective'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Produse selective', '/admin/public/produse-selective', '/admin/public/produse-selective', NULL, 47, 'bx bx-store', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/produse-selective'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Produse selective', '/admin/public/produse-selective', '/admin/public/produse-selective', NULL, 47, 'bx bx-store', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/produse-selective'
);
