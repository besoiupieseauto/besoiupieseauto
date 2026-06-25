-- ============================================================
-- MIGRARE 025: Repară rute pagini hub (scan, cron, alerts, etc.)
-- Problema: dir greșit → căuta pages/scan.php în loc de pages/scan/scan.php
-- ============================================================

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/scan/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` IN ('/admin/public/scan', '/admin/public/scaner');

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/cron/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` = '/admin/public/cron';

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/alerts/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` = '/admin/public/alerts';

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/report/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` IN ('/admin/public/reports', '/admin/public/report');

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/cross-reference/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` IN ('/admin/public/cross-reference', '/admin/public/crossreference');

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/settings/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` = '/admin/public/settings';

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/searchlogs/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` = '/admin/public/searchlogs';

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/searchlogs/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` = '/admin/public/search-logs';

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/users/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` IN ('/admin/public/users', '/admin/public/addusers');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/scan', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/scan/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/scan');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/cron', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/cron/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/cron');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/alerts', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/alerts/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/alerts');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/reports', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/report/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/reports');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/cross-reference', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/cross-reference/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/cross-reference');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/search-logs', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/search-logs/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/search-logs');

UPDATE `role_nav` SET `url` = '/admin/public/searchlogs', `path` = '/admin/public/searchlogs'
WHERE `url` IN ('/admin/public/search-logs', '/admin/public/search-logs/');
