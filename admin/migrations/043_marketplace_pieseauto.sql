-- Marketplace PieseAuto: conturi + rută panou robot
-- NOTĂ securitate: coloana pas trebuie criptată la scriere (password_hash) în aplicație.
CREATE TABLE IF NOT EXISTS `pieseauto_accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `randomn_id` INT UNSIGNED NOT NULL,
    `id_users` INT UNSIGNED NOT NULL DEFAULT 0,
    `company_name` VARCHAR(255) NULL DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL DEFAULT '',
            `pas` VARCHAR(512) NOT NULL DEFAULT '',
    `target_user` VARCHAR(64) NOT NULL DEFAULT 'besoiu',
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_randomn_id` (`randomn_id`),
    KEY `idx_id_users` (`id_users`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/marketplace-pieseauto', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/marketplace/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/marketplace-pieseauto');
