-- TVA comercial: stocat in price_formation_logic.config_json -> commercial_vat_percent (implicit 21 in cod).
-- Rulati migrarea doar daca doriti explicit cheia in JSON; altfel codul foloseste 21%.

UPDATE `price_formation_logic` AS `pfl`
JOIN (
    SELECT MIN(`id`) AS `min_id` FROM `price_formation_logic`
) AS `pick` ON `pfl`.`id` = `pick`.`min_id`
SET `pfl`.`config_json` = CASE
    WHEN `pfl`.`config_json` IS NULL OR TRIM(`pfl`.`config_json`) = '' THEN '{"commercial_vat_percent":21}'
    WHEN `pfl`.`config_json` NOT LIKE '%commercial_vat_percent%' THEN JSON_SET(CAST(`pfl`.`config_json` AS JSON), '$.commercial_vat_percent', 21)
    ELSE `pfl`.`config_json`
END;
