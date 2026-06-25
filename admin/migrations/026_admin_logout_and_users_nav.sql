-- ============================================================
-- MIGRARE 026: Rută logout + meniu Utilizatori admin
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/logout', 'Admin', 'logout', 'simplepag', '', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/logout');

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Utilizatori admin', '/admin/public/users', '/admin/public/users', NULL, 95, 'bx bx-user-circle', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/users'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Utilizatori admin', '/admin/public/users', '/admin/public/users', NULL, 95, 'bx bx-user-circle', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/users'
);
