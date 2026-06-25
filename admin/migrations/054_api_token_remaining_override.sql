-- MIGRARE 054: ajustare manuală tokeni rămași per provider

ALTER TABLE `api_token_budgets`
    ADD COLUMN IF NOT EXISTS `remaining_override` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Tokeni rămași setați manual (NULL = calcul automat)'
        AFTER `tokens_per_request`;
