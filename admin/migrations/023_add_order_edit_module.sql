-- ============================================================
-- MIGRARE 023: Editare comandă legacy + rute M1c–M1g
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/order-edit', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/orders/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/order-edit'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Editare comandă', '/admin/public/order-edit', '/admin/public/order-edit', NULL, 45, 'bx bx-edit', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/order-edit'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Editare comandă', '/admin/public/order-edit', '/admin/public/order-edit', NULL, 45, 'bx bx-edit', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/order-edit'
);
