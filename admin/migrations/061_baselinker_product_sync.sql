-- BaseLinker catalog sync: inventar + mapping câmpuri produse
ALTER TABLE `marketplace`
    ADD COLUMN `bl_inventory_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ID inventar BaseLinker' AFTER `webhook_url`;

ALTER TABLE `marketplace`
    ADD COLUMN `field_mapping` JSON NULL COMMENT 'Mapare câmpuri Besoiu -> BaseLinker' AFTER `bl_inventory_id`;

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`)
SELECT 'GET', '/admin/marketplace-baselinker', 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/marketplace/', 1
WHERE NOT EXISTS (SELECT 1 FROM `routes` WHERE `method` = 'GET' AND `path` = '/admin/marketplace-baselinker');
