-- tm_053: furnizori cu return 10% lunar → adaos compensator pe feed = 0%
UPDATE `furnizori`
SET
    `price_markup_type` = 'percentage',
    `price_markup_value` = 0.00
WHERE UPPER(TRIM(`code`)) IN ('AUTOTOTAL', 'AUTONET', 'MATEROM');
