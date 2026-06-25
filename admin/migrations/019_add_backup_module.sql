-- ============================================================
-- MIGRARE: Modul Backup admin
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/backup', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/backup/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/backup'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Backup', '/admin/public/backup', '/admin/public/backup', NULL, 90, 'bx bx-data', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/backup'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Backup', '/admin/public/backup', '/admin/public/backup', NULL, 90, 'bx bx-data', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/backup'
);
