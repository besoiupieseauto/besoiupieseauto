-- Descrieri duale per produs: website curat vs marketplace detaliat

ALTER TABLE `produse`
    ADD COLUMN `pNoteWebsite` LONGTEXT NULL COMMENT 'Descriere curatata pentru website' AFTER `pNote`,
    ADD COLUMN `pNoteMarketplace` LONGTEXT NULL COMMENT 'Descriere detaliata pentru marketplace' AFTER `pNoteWebsite`;

ALTER TABLE `import_produse`
    ADD COLUMN `pNoteWebsite` LONGTEXT NULL COMMENT 'Descriere curatata pentru website' AFTER `pNote`,
    ADD COLUMN `pNoteMarketplace` LONGTEXT NULL COMMENT 'Descriere detaliata pentru marketplace' AFTER `pNoteWebsite`;
