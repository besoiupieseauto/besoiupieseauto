-- ============================================================
-- MIGRARE 050: O singură rută activă /admin/cron + legacy dezactivat
-- ============================================================

UPDATE `routes`
SET `is_active` = 0
WHERE `method` = 'GET'
  AND `path` IN (
    '/admin/public/cron',
    '/admin/cron/',
    '/admin/cron/index.php'
  );

UPDATE `routes`
SET `is_active` = 1,
    `path` = '/admin/cron',
    `controller` = 'Admin',
    `action` = 'index',
    `load_type` = 'loadPage',
    `dir` = '/admin/Templates/admin/pages/cron/'
WHERE `method` = 'GET'
  AND `path` = '/admin/cron';

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/cron', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/cron/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/cron');

UPDATE `role_nav`
SET `url` = '/admin/cron', `path` = '/admin/cron', `is_active` = 1
WHERE `url` IN ('/admin/public/cron', '/admin/cron/');
