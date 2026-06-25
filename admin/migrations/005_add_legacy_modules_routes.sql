-- ============================================================
-- MIGRARE: Adauga rutele pentru comenzi legacy in caiet de comenzi
-- Comenzi TM, Comenzi UTVIN, Comenzi externe
-- ============================================================

-- Rute noi doar pentru comenzi legacy
INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/comenzi-tm', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/comenzi-tm');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/comenzi-utvin', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/comenzi-utvin');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/comenzi-externe', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/comenzi-externe');

-- Actualizam label-ul existent daca exista
UPDATE `role_nav` SET `label` = 'Caiet comenzi' WHERE `url` = '/admin/public/caietcomenzi' AND `role_slug` = 'super_ambassador';
