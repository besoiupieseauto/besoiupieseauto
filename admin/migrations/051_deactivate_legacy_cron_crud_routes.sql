-- ============================================================
-- MIGRARE 051: Dezactivează rutele hub CRUD legacy pentru cron
-- Panoul live = GET /admin/cron (Cron Sync). Tabelul `cron` rămâne în BD.
-- ============================================================

UPDATE `routes`
SET `is_active` = 0
WHERE `path` IN (
    '/admin/public/addcron',
    '/admin/public/profilecron',
    '/admin/public/crudcron'
);
