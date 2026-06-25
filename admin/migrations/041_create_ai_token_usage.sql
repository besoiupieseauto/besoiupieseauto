-- ============================================================
-- MIGRARE: Dashboard consum tokeni AI (Grok / Gemini / Groq)
-- ============================================================

CREATE TABLE IF NOT EXISTS `ai_token_usage` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider` ENUM('grok', 'gemini', 'groq', 'openai') NOT NULL DEFAULT 'groq',
    `model` VARCHAR(96) NOT NULL DEFAULT '',
    `prompt_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
    `completion_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
    `source` VARCHAR(64) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ai_token_provider_created` (`provider`, `created_at`),
    KEY `idx_ai_token_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_token_thresholds` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider` VARCHAR(32) NOT NULL,
    `daily_limit` INT UNSIGNED NOT NULL DEFAULT 500000,
    `warning_pct` TINYINT UNSIGNED NOT NULL DEFAULT 80,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ai_token_threshold_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ai_token_thresholds` (`provider`, `daily_limit`, `warning_pct`, `is_active`)
SELECT 'grok', 500000, 80, 1
WHERE NOT EXISTS (SELECT 1 FROM `ai_token_thresholds` WHERE `provider` = 'grok');

INSERT INTO `ai_token_thresholds` (`provider`, `daily_limit`, `warning_pct`, `is_active`)
SELECT 'gemini', 500000, 80, 1
WHERE NOT EXISTS (SELECT 1 FROM `ai_token_thresholds` WHERE `provider` = 'gemini');

INSERT INTO `ai_token_thresholds` (`provider`, `daily_limit`, `warning_pct`, `is_active`)
SELECT 'groq', 1000000, 80, 1
WHERE NOT EXISTS (SELECT 1 FROM `ai_token_thresholds` WHERE `provider` = 'groq');

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/ai-tokens', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/ai-tokens/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/ai-tokens'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Tokeni AI', '/admin/public/ai-tokens', '/admin/public/ai-tokens', NULL, 47, 'bx bx-chip', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/ai-tokens'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Tokeni AI', '/admin/public/ai-tokens', '/admin/public/ai-tokens', NULL, 47, 'bx bx-chip', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/ai-tokens'
);
