-- ============================================================
-- MIGRARE 047: Rută curată /admin/cron + role_nav + scan
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/cron', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/cron/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/cron');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/scan', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/scan/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/scan');

UPDATE `role_nav`
SET `url` = '/admin/cron', `path` = '/admin/cron'
WHERE `url` IN ('/admin/public/cron', '/admin/cron/');

UPDATE `role_nav`
SET `url` = '/admin/scan', `path` = '/admin/scan'
WHERE `url` IN ('/admin/public/scan', '/admin/public/scaner', '/admin/scaner');

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Cron Sync', '/admin/cron', '/admin/cron', NULL, 86, 'bx bx-refresh', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/cron'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Cron Sync', '/admin/cron', '/admin/cron', NULL, 86, 'bx bx-refresh', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/cron'
);
