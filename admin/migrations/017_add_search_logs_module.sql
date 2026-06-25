-- ============================================================
-- MIGRARE: Modul Search Logs (jurnal VIN/OEM negăsite)
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/searchlogs', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/searchlogs/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/searchlogs'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Search Logs', '/admin/public/searchlogs', '/admin/public/searchlogs', NULL, 46, 'bx bx-search-alt', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/searchlogs'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Search Logs', '/admin/public/searchlogs', '/admin/public/searchlogs', NULL, 46, 'bx bx-search-alt', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/searchlogs'
);
