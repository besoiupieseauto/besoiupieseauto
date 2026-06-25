-- Legatura structurala comenzi site <-> facturi <-> livrare (EvaSystem)

ALTER TABLE `facturi`
    ADD COLUMN `order_id` INT NULL DEFAULT NULL AFTER `order_number`;

ALTER TABLE `livrare`
    ADD COLUMN `order_id` INT NULL DEFAULT NULL AFTER `order_number`;

ALTER TABLE `comenzi`
    ADD COLUMN `invoice_randomn_id` INT NULL DEFAULT NULL AFTER `payment_status_detail`,
    ADD COLUMN `livrare_randomn_id` INT NULL DEFAULT NULL AFTER `invoice_randomn_id`;

CREATE INDEX `idx_facturi_order_id` ON `facturi` (`order_id`);
CREATE INDEX `idx_livrare_order_id` ON `livrare` (`order_id`);
CREATE INDEX `idx_comenzi_invoice_randomn_id` ON `comenzi` (`invoice_randomn_id`);
CREATE INDEX `idx_comenzi_livrare_randomn_id` ON `comenzi` (`livrare_randomn_id`);
