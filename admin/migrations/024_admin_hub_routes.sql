-- ============================================================
-- MIGRARE 024: Rute caiet ERP + redirect search-logs
-- ============================================================

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/caiet-clienti', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/caiet-clienti');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/caiet-produse', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/caiet-produse');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/caiet-facturi', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/caiet-facturi');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/caiet-incasari', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/caietcomenzi/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/caiet-incasari');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/users', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/users/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/users');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/settings', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/settings/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/settings');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/alerts', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/alerts/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/alerts');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/reports', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/report/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/reports');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/cron', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/cron/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/cron');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/scan', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/scan/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/scan');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/cross-reference', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/cross-reference/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `path` = '/admin/public/cross-reference');
