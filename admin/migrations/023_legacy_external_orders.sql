-- ============================================================
-- Tabele comenzi externe legacy (M1f) — dev local
-- ============================================================

CREATE TABLE IF NOT EXISTS `comenzi_ext` (
    `idcmd` INT NOT NULL AUTO_INCREMENT,
    `idcomanda` INT NOT NULL,
    `idclient` INT NOT NULL,
    `userid` INT NULL,
    `idprodus` INT NULL,
    `cantitate` INT NULL DEFAULT 1,
    `total` DECIMAL(12,2) NULL DEFAULT 0,
    `idmasina` INT NULL DEFAULT 1,
    `stare` INT NULL DEFAULT 1,
    `retur` INT NULL DEFAULT 1,
    `data` DATE NULL,
    `awb` VARCHAR(64) NULL,
    `cont_awb` VARCHAR(64) NULL,
    `observations` TEXT NULL,
    `created_at` INT NULL,
    PRIMARY KEY (`idcmd`),
    UNIQUE KEY `uniq_comenzi_ext_idcomanda` (`idcomanda`),
    KEY `idx_comenzi_ext_client` (`idclient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `detaliu_ext` (
    `iddetaliu` INT NOT NULL AUTO_INCREMENT,
    `idcomanda` INT NOT NULL,
    `idprodus` INT NOT NULL,
    `cantitate` INT NOT NULL DEFAULT 1,
    `pret` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `furnizor` VARCHAR(32) NULL,
    `culoare` VARCHAR(16) NULL,
    `created_at` INT NULL,
    PRIMARY KEY (`iddetaliu`),
    KEY `idx_detaliu_ext_order` (`idcomanda`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
