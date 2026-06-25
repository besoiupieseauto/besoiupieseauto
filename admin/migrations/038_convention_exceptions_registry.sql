-- MIGRARE 038: registru excepții convenții acceptate (hub scaffold status, tabele plural legacy)

INSERT INTO `schema_legacy_registry` (`table_name`, `reason`, `target_name`, `is_active`)
SELECT 'hub_scaffold_status', 'Coloană status TINYINT pe tabele hub scaffold — fără prefix is_', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM `schema_legacy_registry` WHERE `table_name` = 'hub_scaffold_status');

INSERT INTO `schema_legacy_registry` (`table_name`, `reason`, `target_name`, `is_active`)
SELECT 'categorii', 'Nume plural românesc stabil în producție', 'categories', 1
WHERE NOT EXISTS (SELECT 1 FROM `schema_legacy_registry` WHERE `table_name` = 'categorii');

INSERT INTO `schema_legacy_registry` (`table_name`, `reason`, `target_name`, `is_active`)
SELECT 'produse', 'Nume plural românesc stabil în producție', 'products', 1
WHERE NOT EXISTS (SELECT 1 FROM `schema_legacy_registry` WHERE `table_name` = 'produse');

INSERT INTO `schema_legacy_registry` (`table_name`, `reason`, `target_name`, `is_active`)
SELECT 'comenzi', 'Nume plural românesc stabil în producție', 'orders', 1
WHERE NOT EXISTS (SELECT 1 FROM `schema_legacy_registry` WHERE `table_name` = 'comenzi');
