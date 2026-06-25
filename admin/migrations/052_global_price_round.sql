-- Rotunjire globală magazin: price_formation_logic.config_json
-- Chei: global_price_round_mode (none|next_integer|round_to), global_price_round_value (implicit 1)

UPDATE `price_formation_logic` AS `pfl`
JOIN (
    SELECT MIN(`id`) AS `min_id` FROM `price_formation_logic`
) AS `pick` ON `pfl`.`id` = `pick`.`min_id`
SET `pfl`.`config_json` = CASE
    WHEN `pfl`.`config_json` IS NULL OR TRIM(`pfl`.`config_json`) = '' THEN '{"global_price_round_mode":"none","global_price_round_value":1}'
    WHEN `pfl`.`config_json` NOT LIKE '%global_price_round_mode%' THEN JSON_SET(CAST(`pfl`.`config_json` AS JSON), '$.global_price_round_mode', 'none', '$.global_price_round_value', 1)
    ELSE `pfl`.`config_json`
END;
