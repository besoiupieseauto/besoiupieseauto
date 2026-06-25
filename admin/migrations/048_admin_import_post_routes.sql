-- ============================================================
-- MIGRARE 048: API POST import — URL-uri curate /admin/import*
-- Rezolvă 404 JSON la „Reîncarcă lista” pe /admin/import
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'POST', '/admin/importproduse', 'Admin', 'rootFunction', 'rootFunction', '/admin/src/Controllers/Produse/importproduse.php', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'POST' AND `path` = '/admin/importproduse');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'POST', '/admin/import', 'Admin', 'rootFunction', 'rootFunction', '/admin/src/Controllers/Produse/importproduse.php', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'POST' AND `path` = '/admin/import');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'POST', '/admin/import-action', 'Admin', 'rootFunction', 'rootFunction', '/admin/src/Controllers/Produse/importproduse_action.php', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'POST' AND `path` = '/admin/import-action');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/importreview', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/import/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/importreview');
