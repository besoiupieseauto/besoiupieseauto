-- Config global formare pret (ordine scanare furnizori, omit, verificari brand/stoc/pret)
CREATE TABLE IF NOT EXISTS `price_formation_logic` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `config_json` LONGTEXT NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `price_formation_logic` (`config_json`)
SELECT '{"scan_order":["AUTOTOTAL","AUTONET","MATEROM","AUTOPARTNER","ELIT","INTERCARS"],"omit_suppliers":[],"brand_verify":"exact","stock_verify":"skip_zero","price_strategy":"hierarchical_top3_lowest","compare_tier_size":3}'
WHERE NOT EXISTS (SELECT 1 FROM `price_formation_logic` LIMIT 1);
