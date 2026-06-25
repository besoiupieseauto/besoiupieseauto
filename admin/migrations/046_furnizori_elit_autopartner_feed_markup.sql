-- tm_054: furnizori cu return mic → adaos compensator pe feed
UPDATE `furnizori`
SET
    `price_markup_type` = 'percentage',
    `price_markup_value` = 5.00
WHERE UPPER(TRIM(`code`)) = 'ELIT';

UPDATE `furnizori`
SET
    `price_markup_type` = 'percentage',
    `price_markup_value` = 10.00
WHERE UPPER(TRIM(`code`)) = 'AUTOPARTNER';
