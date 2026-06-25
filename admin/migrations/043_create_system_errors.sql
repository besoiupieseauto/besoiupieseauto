-- ============================================================
-- MIGRARE: Jurnal centralizat erori sistem (procesare fundal)
-- ============================================================

CREATE TABLE IF NOT EXISTS `system_errors` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `level` ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'error',
    `channel` VARCHAR(64) NOT NULL DEFAULT 'general',
    `message` VARCHAR(1000) NOT NULL,
    `context_json` JSON NULL,
    `source_file` VARCHAR(255) NULL DEFAULT NULL,
    `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_system_errors_channel_created` (`channel`, `created_at`),
    KEY `idx_system_errors_level_created` (`level`, `created_at`),
    KEY `idx_system_errors_resolved_created` (`is_resolved`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/system-errors', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/system-errors/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/system-errors'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'super_ambassador', 'Erori sistem', '/admin/public/system-errors', '/admin/public/system-errors', NULL, 48, 'bx bx-error-circle', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'super_ambassador' AND `url` = '/admin/public/system-errors'
);

INSERT INTO `role_nav` (`role_slug`, `label`, `path`, `url`, `parent_id`, `sort_order`, `icon`, `is_active`)
SELECT 'manager', 'Erori sistem', '/admin/public/system-errors', '/admin/public/system-errors', NULL, 48, 'bx bx-error-circle', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `role_nav` WHERE `role_slug` = 'manager' AND `url` = '/admin/public/system-errors'
);
