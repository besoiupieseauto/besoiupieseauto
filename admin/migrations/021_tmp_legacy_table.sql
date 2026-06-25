-- ============================================================
-- Tabel coș temporar comenzi ERP — BD legacy
-- ============================================================

CREATE TABLE IF NOT EXISTS `tmp` (
    `id_tmp` INT NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(128) NOT NULL,
    `id_produs` INT NULL,
    `cantitate_tmp` INT NOT NULL DEFAULT 1,
    `pret_tmp` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `furnizor` VARCHAR(32) NULL,
    `culoare` VARCHAR(16) NULL,
    `tva` DECIMAL(5,2) NULL,
    `tva_tmp` DECIMAL(12,2) NULL,
    PRIMARY KEY (`id_tmp`),
    KEY `idx_tmp_session` (`session_id`),
    KEY `idx_tmp_session_product` (`session_id`, `id_produs`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
