-- Metadate SmartBill pe facturi EvaSystem

ALTER TABLE `facturi`
    ADD COLUMN `smartbill_series` VARCHAR(32) NULL DEFAULT NULL AFTER `invoice_number`,
    ADD COLUMN `smartbill_number` VARCHAR(32) NULL DEFAULT NULL AFTER `smartbill_series`,
    ADD COLUMN `smartbill_invoice_id` VARCHAR(64) NULL DEFAULT NULL AFTER `smartbill_number`;

CREATE INDEX `idx_facturi_smartbill_number` ON `facturi` (`smartbill_series`, `smartbill_number`);

ALTER TABLE `livrare`
    ADD COLUMN `courier_provider` VARCHAR(32) NULL DEFAULT NULL AFTER `courier`,
    ADD COLUMN `courier_response` TEXT NULL DEFAULT NULL AFTER `notes`;
