-- ============================================================
-- MIGRARE 052: Hub setări — permisiuni utilizatori + buget tokeni API
-- ============================================================

ALTER TABLE `users_connect`
    ADD COLUMN IF NOT EXISTS `permissions_json` TEXT NULL COMMENT 'Module admin delegabile (JSON array)'
    AFTER `role`;

CREATE TABLE IF NOT EXISTS `api_token_budgets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_key` VARCHAR(48) NOT NULL,
    `label` VARCHAR(120) NOT NULL DEFAULT '',
    `env_key` VARCHAR(80) NULL DEFAULT NULL,
    `monthly_quota` INT UNSIGNED NOT NULL DEFAULT 1000,
    `cost_per_unit` DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'RON per request/unit',
    `warning_pct` TINYINT UNSIGNED NOT NULL DEFAULT 80,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` VARCHAR(255) NULL DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_api_token_budget_provider` (`provider_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_token_usage_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_key` VARCHAR(48) NOT NULL,
    `units` INT UNSIGNED NOT NULL DEFAULT 1,
    `cost_ron` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `source` VARCHAR(64) NULL DEFAULT NULL,
    `note` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_api_token_usage_provider_created` (`provider_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `api_token_budgets` (`provider_key`, `label`, `env_key`, `monthly_quota`, `cost_per_unit`, `warning_pct`, `is_active`, `notes`)
SELECT 'rapidapi_tecdoc', 'RapidAPI TecDoc', 'RAPIDAPI_AUTOPARTS_KEY', 5000, 0.0500, 80, 1, 'Căutări catalog / imagini TecDoc'
WHERE NOT EXISTS (SELECT 1 FROM `api_token_budgets` WHERE `provider_key` = 'rapidapi_tecdoc');

INSERT INTO `api_token_budgets` (`provider_key`, `label`, `env_key`, `monthly_quota`, `cost_per_unit`, `warning_pct`, `is_active`, `notes`)
SELECT 'scrape_do', 'Scrape.do (Autodoc/eMAG)', 'SCRAPE_DO_TOKEN', 1000, 0.1200, 80, 1, 'Fetch pagini via proxy rezidențial'
WHERE NOT EXISTS (SELECT 1 FROM `api_token_budgets` WHERE `provider_key` = 'scrape_do');

INSERT INTO `api_token_budgets` (`provider_key`, `label`, `env_key`, `monthly_quota`, `cost_per_unit`, `warning_pct`, `is_active`, `notes`)
SELECT 'openai', 'OpenAI', 'OPENAI_KEY', 500000, 0.0001, 80, 1, 'Tokeni AI — cost per 1k tokeni'
WHERE NOT EXISTS (SELECT 1 FROM `api_token_budgets` WHERE `provider_key` = 'openai');

INSERT INTO `api_token_budgets` (`provider_key`, `label`, `env_key`, `monthly_quota`, `cost_per_unit`, `warning_pct`, `is_active`, `notes`)
SELECT 'groq', 'Groq', 'GROQ_KEY', 1000000, 0.0000, 80, 1, 'Chat robot / audit'
WHERE NOT EXISTS (SELECT 1 FROM `api_token_budgets` WHERE `provider_key` = 'groq');

INSERT INTO `api_token_budgets` (`provider_key`, `label`, `env_key`, `monthly_quota`, `cost_per_unit`, `warning_pct`, `is_active`, `notes`)
SELECT 'gemini', 'Google Gemini', 'GEMINI_KEY', 500000, 0.0001, 80, 1, 'Alternative AI'
WHERE NOT EXISTS (SELECT 1 FROM `api_token_budgets` WHERE `provider_key` = 'gemini');

INSERT INTO `api_token_budgets` (`provider_key`, `label`, `env_key`, `monthly_quota`, `cost_per_unit`, `warning_pct`, `is_active`, `notes`)
SELECT 'grok', 'Grok (xAI)', 'GROK_KEY', 300000, 0.0001, 80, 1, 'Alternative AI'
WHERE NOT EXISTS (SELECT 1 FROM `api_token_budgets` WHERE `provider_key` = 'grok');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/api/settings_endpoint.php', 'Admin', 'index', 'loadPage', '/admin/public/api/settings_endpoint.php', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/api/settings_endpoint.php'
);

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'POST', '/admin/public/api/settings_endpoint.php', 'Admin', 'index', 'loadPage', '/admin/public/api/settings_endpoint.php', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'POST' AND `path` = '/admin/public/api/settings_endpoint.php'
);
