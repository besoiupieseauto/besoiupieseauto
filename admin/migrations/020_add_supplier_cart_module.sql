-- ============================================================
-- MIGRARE 020: Coș furnizori B2B (pagină admin)
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/supplier-cart', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/supplier-search/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/supplier-cart'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Coș furnizori', '/admin/public/supplier-cart', '/admin/public/supplier-cart', NULL, 45, 'bx bx-cart', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/supplier-cart'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Coș furnizori', '/admin/public/supplier-cart', '/admin/public/supplier-cart', NULL, 45, 'bx bx-cart', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/supplier-cart'
);
