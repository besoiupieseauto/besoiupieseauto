-- ============================================================
-- MIGRARE 026: Rute curate admin + corectare dir fără /admin
-- ============================================================

-- Corectează dir-uri vechi fără prefix /admin
UPDATE `routes`
SET `dir` = CONCAT('/admin', `dir`)
WHERE `dir` LIKE '/Templates/%'
  AND `dir` NOT LIKE '/admin/%';

-- Rute curate furnizori / import / hub
UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/furnizori/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` IN ('/admin/public/furnizori', '/admin/public/profilefurnizori', '/admin/public/addfurnizori');

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/import/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` IN ('/admin/public/import', '/admin/public/importreview');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/suppliers', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/furnizori/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/suppliers');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/import', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/import/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/import');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/facturi', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/facturi/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/facturi');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/clienti', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/clienti/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/clienti');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/livrare', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/livrare/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/livrare');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/categorii', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/categorii/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/categorii');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/adaoscomercial', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/adaoscomercial/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/adaoscomercial');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/bots', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/bots/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/bots');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/messages', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/messages/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/messages');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/marketplace', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/marketplace/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/marketplace');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/website', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/website/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/website');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/blog', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/blog/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/blog');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/scraper', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/scraper/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/scraper');

UPDATE `role_nav` SET `url` = '/admin/suppliers', `path` = '/admin/suppliers'
WHERE `url` IN ('/admin/public/furnizori', '/admin/furnizori') AND `label` LIKE '%furnizor%';
