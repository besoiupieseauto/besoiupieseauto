-- ============================================================
-- MIGRARE: Modul Caiet de comenzi (baza legacy Laravel)
-- Adauga ruta + item de navigare in admin.
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/caietcomenzi', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/caietcomenzi'
);

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/caiet-de-comenzi', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/caiet-de-comenzi'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Caiet comenzi', '/admin/public/caietcomenzi', '/admin/public/caietcomenzi', NULL, 83, 'bx bx-book', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/caietcomenzi'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Caiet comenzi', '/admin/public/caietcomenzi', '/admin/public/caietcomenzi', NULL, 83, 'bx bx-book', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/caietcomenzi'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'regional_ambassador', 'Caiet comenzi', '/admin/public/caietcomenzi', '/admin/public/caietcomenzi', NULL, 83, 'bx bx-book', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'regional_ambassador' AND `url` = '/admin/public/caietcomenzi'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'executive', 'Caiet comenzi', '/admin/public/caietcomenzi', '/admin/public/caietcomenzi', NULL, 83, 'bx bx-book', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'executive' AND `url` = '/admin/public/caietcomenzi'
);

