-- MIGRARE 053: buget API — tokeni per query + cotă în tokeni (nu request-uri)

ALTER TABLE `api_token_budgets`
    ADD COLUMN IF NOT EXISTS `tokens_per_request` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Tokeni consumați per query/request'
        AFTER `monthly_quota`;

UPDATE `api_token_budgets` SET `tokens_per_request` = 10, `monthly_quota` = 5000
WHERE `provider_key` = 'rapidapi_tecdoc' AND `tokens_per_request` = 1;

UPDATE `api_token_budgets` SET `tokens_per_request` = 1, `monthly_quota` = 1000
WHERE `provider_key` = 'scrape_do' AND `monthly_quota` = 1000;

UPDATE `api_token_budgets` SET `tokens_per_request` = 1500
WHERE `provider_key` = 'openai' AND `tokens_per_request` = 1;

UPDATE `api_token_budgets` SET `tokens_per_request` = 800
WHERE `provider_key` = 'groq' AND `tokens_per_request` = 1;

UPDATE `api_token_budgets` SET `tokens_per_request` = 1200
WHERE `provider_key` = 'gemini' AND `tokens_per_request` = 1;

UPDATE `api_token_budgets` SET `tokens_per_request` = 1200
WHERE `provider_key` = 'grok' AND `tokens_per_request` = 1;
