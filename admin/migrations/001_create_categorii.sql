-- ============================================================
-- MIGRARE: Creare tabel `categorii` + rute admin
-- Rulează o singură dată pe baza de date `evasystem`
-- ============================================================

-- 1. Tabel principal
CREATE TABLE IF NOT EXISTS `categorii` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(100) NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(255) DEFAULT '',
    `parent_id` INT DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `type` ENUM('categorie','marca','model','motorizare') DEFAULT 'categorie',
    `tecdoc_id` INT DEFAULT NULL,
    `meta` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_parent` (`parent_id`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Rute admin (pagina + crud)
INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`) VALUES
('GET',  '/admin/public/categorii',     'Admin', 'index',        'loadPage', '/admin/Templates/admin/pages/categorii/', 1),
('POST', '/admin/public/crudcategorii', 'Admin', 'rootFunction', NULL,       '/admin/src/Controllers/Categorii/crudu.php', 1),
('GET',  '/admin/public/addcategorii',  'Admin', 'index',        'loadPage', '/admin/Templates/admin/pages/categorii/', 1);

-- 3. Date inițiale — cele 8 categorii standard
INSERT INTO `categorii` (`slug`, `label`, `icon`, `parent_id`, `sort_order`, `is_active`, `type`) VALUES
('frane',      'Frâne',          'img/icons/01_frane.svg',        NULL, 10, 1, 'categorie'),
('filtre',     'Filtre',         'img/icons/02_filtre.svg',       NULL, 20, 1, 'categorie'),
('ulei',       'Ulei & Lichide', 'img/icons/03_ulei_lichide.svg', NULL, 30, 1, 'categorie'),
('suspensie',  'Suspensie',      'img/icons/04_suspensie.svg',    NULL, 40, 1, 'categorie'),
('motor',      'Motor',          'img/icons/05_motor.svg',        NULL, 50, 1, 'categorie'),
('electric',   'Electric',       'img/icons/06_electric.svg',     NULL, 60, 1, 'categorie'),
('caroserie',  'Caroserie',      'img/icons/07_caroserie.svg',    NULL, 70, 1, 'categorie'),
('transmisie', 'Transmisie',     'img/icons/08_transmisie.svg',   NULL, 80, 1, 'categorie');
