-- Adaos comercial global pe BD: price_formation_logic.config_json
-- Cheie: global_commercial_markup_percent (implicit 0)

UPDATE `price_formation_logic` AS `pfl`
JOIN (
    SELECT MIN(`id`) AS `min_id` FROM `price_formation_logic`
) AS `pick` ON `pfl`.`id` = `pick`.`min_id`
SET `pfl`.`config_json` = CASE
    WHEN `pfl`.`config_json` IS NULL OR TRIM(`pfl`.`config_json`) = '' THEN '{"global_commercial_markup_percent":0}'
    WHEN `pfl`.`config_json` NOT LIKE '%global_commercial_markup_percent%' THEN JSON_SET(CAST(`pfl`.`config_json` AS JSON), '$.global_commercial_markup_percent', 0)
    ELSE `pfl`.`config_json`
END;
