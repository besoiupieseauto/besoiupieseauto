-- ============================================================
-- Tabele ERP minime pentru dev local (BD legacy)
-- Rulează: php admin/migrations/run_022_legacy_orders_core.php
-- ============================================================

CREATE TABLE IF NOT EXISTS `clienti` (
    `idclienti` INT NOT NULL AUTO_INCREMENT,
    `nume` VARCHAR(255) NULL,
    `telefon` VARCHAR(64) NULL,
    `adresa` VARCHAR(255) NULL,
    `companie` VARCHAR(255) NULL,
    `cif` VARCHAR(64) NULL,
    `marca` VARCHAR(128) NULL,
    `sasiu` VARCHAR(128) NULL,
    `nr_inmat` VARCHAR(64) NULL,
    `created_at` INT NULL,
    PRIMARY KEY (`idclienti`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `masina` (
    `idmasina` INT NOT NULL AUTO_INCREMENT,
    `marca` VARCHAR(128) NULL,
    `sasiu` VARCHAR(128) NULL,
    `nrmat` VARCHAR(64) NULL,
    PRIMARY KEY (`idmasina`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `produse` (
    `idprodus` INT NOT NULL,
    `denumire` VARCHAR(255) NULL,
    `cod_produs` VARCHAR(128) NULL,
    `pret` DECIMAL(12,2) NULL DEFAULT 0,
    `TVA` VARCHAR(16) NULL,
    `um` VARCHAR(16) NULL,
    `created_at` INT NULL,
    PRIMARY KEY (`idprodus`),
    KEY `idx_produse_cod` (`cod_produs`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comenzi` (
    `idcmd` INT NOT NULL AUTO_INCREMENT,
    `idcomanda` INT NOT NULL,
    `idclient` INT NOT NULL,
    `userid` INT NULL,
    `data` DATE NULL,
    `idmasina` INT NULL DEFAULT 1,
    `total` DECIMAL(12,2) NULL DEFAULT 0,
    `stare` INT NULL DEFAULT 1,
    `cont_awb` VARCHAR(64) NULL,
    `observations` TEXT NULL,
    `locatie_mgz` INT NULL DEFAULT 1,
    `created_at` INT NULL,
    PRIMARY KEY (`idcmd`),
    UNIQUE KEY `uniq_comenzi_idcomanda` (`idcomanda`),
    KEY `idx_comenzi_client` (`idclient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `detaliu` (
    `iddetaliu` INT NOT NULL AUTO_INCREMENT,
    `idcomanda` INT NOT NULL,
    `idprodus` INT NOT NULL,
    `cantitate` INT NOT NULL DEFAULT 1,
    `pret` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `furnizor` VARCHAR(32) NULL,
    `culoare` VARCHAR(16) NULL,
    `created_at` INT NULL,
    PRIMARY KEY (`iddetaliu`),
    KEY `idx_detaliu_order` (`idcomanda`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clienti` (`idclienti`, `nume`, `telefon`, `adresa`, `created_at`)
SELECT 1, 'Client test ERP', '0700000000', 'Timișoara', UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `clienti` WHERE `idclienti` = 1);
