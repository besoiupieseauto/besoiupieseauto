-- MIGRARE 029: Căi module CrossReference / SearchLogs (nume PHP valide)
-- Rulează: mysql -u root besoiupieseauto.ro < migrations/029_fix_module_paths.sql

UPDATE `routes`
SET `dir` = REPLACE(`dir`, '/src/Controllers/Cross-reference/', '/src/Controllers/CrossReference/')
WHERE `dir` LIKE '%/src/Controllers/Cross-reference/%';

UPDATE `routes`
SET `dir` = REPLACE(`dir`, '/admin/src/Controllers/Cross-reference/', '/admin/src/Controllers/CrossReference/')
WHERE `dir` LIKE '%/admin/src/Controllers/Cross-reference/%';

UPDATE `routes`
SET `dir` = REPLACE(`dir`, '/src/Controllers/Search-logs/', '/src/Controllers/SearchLogs/')
WHERE `dir` LIKE '%/src/Controllers/Search-logs/%';

UPDATE `routes`
SET `dir` = REPLACE(`dir`, '/admin/src/Controllers/Search-logs/', '/admin/src/Controllers/SearchLogs/')
WHERE `dir` LIKE '%/admin/src/Controllers/Search-logs/%';
