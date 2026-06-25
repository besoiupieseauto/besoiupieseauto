-- ============================================================
-- MIGRARE 026: search-logs → searchlogs (fără redirect HTTP în layout)
-- ============================================================

UPDATE `routes`
SET `dir` = '/admin/Templates/admin/pages/searchlogs/', `load_type` = 'loadPage', `is_active` = 1
WHERE `method` = 'GET' AND `path` = '/admin/public/search-logs';
