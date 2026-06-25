-- ============================================================
-- MIGRARE: Adaos comercial pentru produse
-- Creează tabela de reguli și rutele necesare în admin
-- ============================================================

CREATE TABLE IF NOT EXISTS `adaos_comercial_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `category_filter` VARCHAR(150) DEFAULT NULL,
    `brand_filter` VARCHAR(150) DEFAULT NULL,
    `price_min` DECIMAL(10,2) DEFAULT NULL,
    `price_max` DECIMAL(10,2) DEFAULT NULL,
    `adjustment_type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    `adjustment_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `round_to` DECIMAL(10,2) DEFAULT NULL,
    `priority` INT NOT NULL DEFAULT 100,
    `note` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_adaos_active` (`is_active`),
    INDEX `idx_adaos_priority` (`priority`),
    INDEX `idx_adaos_category` (`category_filter`),
    INDEX `idx_adaos_brand` (`brand_filter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/public/adaoscomercial', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/adaoscomercial/', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/public/adaoscomercial'
);

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'POST', '/admin/public/crudadaoscomercial', 'Admin', 'rootFunction', NULL, '/admin/src/Controllers/AdaosComercial/crudu.php', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `routes` WHERE `method` = 'POST' AND `path` = '/admin/public/crudadaoscomercial'
);
