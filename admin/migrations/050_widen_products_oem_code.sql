-- Lățime cod OEM — unele referințe cross TecDoc depășesc 80 caractere.
ALTER TABLE `products_oem`
    MODIFY COLUMN `oem_code` VARCHAR(120) NOT NULL,
    MODIFY COLUMN `oem_norm` VARCHAR(120) NOT NULL;
