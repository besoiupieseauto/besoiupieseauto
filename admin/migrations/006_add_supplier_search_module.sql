-- ============================================================
-- MIGRARE: Modul Supplier Search in Admin
-- Adauga rutele pentru pagina supplier search.
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/supplier-search', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/supplier-search/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/supplier-search'
);

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/searching', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/supplier-search/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/searching'
);
