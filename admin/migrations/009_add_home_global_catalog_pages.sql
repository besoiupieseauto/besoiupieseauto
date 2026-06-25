-- Pagini CMS: homepage, global, catalog
INSERT INTO `site_pages` (`slug`, `label`, `title`, `meta_description`, `sort_order`, `is_active`) VALUES
('home', 'Acasă (index)', 'Besoiu Piese Auto', 'Magazin online de piese auto — compatibilitate verificată, livrare rapidă 24-48h.', 1, 1),
('global', 'Header & Footer', 'Setări globale site', 'Texte comune header, footer, topbar.', 2, 1),
('catalog', 'Catalog', 'Catalog piese auto', 'Catalog complet de piese auto.', 15, 1)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`),
    `title` = VALUES(`title`),
    `meta_description` = VALUES(`meta_description`),
    `sort_order` = VALUES(`sort_order`),
    `is_active` = VALUES(`is_active`);
