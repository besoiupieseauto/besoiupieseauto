-- ============================================================
-- MIGRARE 049: Eliminare pagină /admin/scan (înlocuită de Cron Sync + Furnizori)
-- ============================================================

UPDATE `routes`
SET `is_active` = 0
WHERE `method` = 'GET'
  AND `path` IN (
    '/admin/scan',
    '/admin/public/scan',
    '/admin/public/scaner',
    '/admin/scaner'
  );

UPDATE `role_nav`
SET `is_active` = 0
WHERE `url` IN ('/admin/scan', '/admin/public/scan', '/admin/public/scaner', '/admin/scaner')
   OR `path` IN ('/admin/scan', '/admin/public/scan', '/admin/public/scaner', '/admin/scaner');
