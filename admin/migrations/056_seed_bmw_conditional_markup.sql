-- tm_083: Regula exemplu BMW + peste 2000 RON → +3000 RON fix (inactiva = activare selectiva)
INSERT INTO `adaos_comercial_rules` (
    `name`,
    `category_filter`,
    `brand_filter`,
    `price_min`,
    `price_max`,
    `adjustment_type`,
    `adjustment_value`,
    `round_to`,
    `priority`,
    `note`,
    `is_active`
)
SELECT
    'BMW peste 2000 RON +3000 fix',
    NULL,
    'BMW',
    2000.00,
    NULL,
    'fixed',
    3000.00,
    NULL,
    50,
    'tm_083: exemplu regula conditionata brand + prag. Activeaza manual din Adaos comercial.',
    0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `adaos_comercial_rules`
    WHERE `name` = 'BMW peste 2000 RON +3000 fix'
);
