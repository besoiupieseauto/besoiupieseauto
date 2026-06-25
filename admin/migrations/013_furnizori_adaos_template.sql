-- Sablon regula adaos comercial aplicat pe furnizor
ALTER TABLE `furnizori`
    ADD COLUMN `adaos_template_rule_id` INT UNSIGNED NULL DEFAULT NULL AFTER `price_min_margin`;
