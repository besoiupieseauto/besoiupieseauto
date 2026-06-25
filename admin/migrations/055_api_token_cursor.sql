INSERT INTO `api_token_budgets` (`provider_key`, `label`, `env_key`, `monthly_quota`, `tokens_per_request`, `cost_per_unit`, `warning_pct`, `is_active`, `notes`)
SELECT 'cursor', 'Cursor AI (Composer)', 'CURSOR_API_KEY', 2000000, 2500, 0.0000, 80, 1, 'Audit imagini + agent scraper Composer 2.5'
WHERE NOT EXISTS (SELECT 1 FROM `api_token_budgets` WHERE `provider_key` = 'cursor');
