-- ============================================================
-- MIGRARE 022: Pagină creare comandă internă (M1b)
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/order-create', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/orders/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/order-create'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Comandă internă', '/admin/public/order-create', '/admin/public/order-create', NULL, 44, 'bx bx-plus-circle', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/order-create'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Comandă internă', '/admin/public/order-create', '/admin/public/order-create', NULL, 44, 'bx bx-plus-circle', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/order-create'
);
