-- Template-uri răspuns — Comunicare & Socializare

CREATE TABLE IF NOT EXISTS `reply_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `randomn_id` VARCHAR(32) NOT NULL,
    `title` VARCHAR(160) NOT NULL,
    `slug` VARCHAR(80) NOT NULL DEFAULT '',
    `category` VARCHAR(60) NOT NULL DEFAULT 'general',
    `channel` VARCHAR(40) NOT NULL DEFAULT 'all',
    `body_text` TEXT NOT NULL,
    `body_html` TEXT NULL,
    `is_quick` TINYINT(1) NOT NULL DEFAULT 0,
    `use_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_reply_templates_randomn` (`randomn_id`),
    KEY `idx_reply_templates_channel` (`channel`, `category`),
    KEY `idx_reply_templates_quick` (`is_quick`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `reply_templates` (`randomn_id`, `title`, `slug`, `category`, `channel`, `body_text`, `is_quick`, `status`) VALUES
('tpl_confirm_comanda', 'Confirmare comandă', 'confirmare-comanda', 'comenzi', 'whatsapp',
'Bună ziua {client_name}! Comanda {order_number} a fost înregistrată. Total: {total_amount} RON. Livrare: {delivery_method}. Vă contactăm pentru confirmare.', 0, 1),
('tpl_verificare_stoc', 'Verificare stoc piesă', 'verificare-stoc', 'stoc', 'whatsapp',
'Bună ziua! Verificăm disponibilitatea pentru {product_name} (cod {oem_code}). Revenim în maximum 30 de minute cu stoc și preț.', 0, 1),
('tpl_oferta_3_preturi', 'Ofertă 3 variante preț', 'oferta-3-preturi', 'oferte', 'whatsapp',
'Pentru {product_name} avem 3 variante:\n• Economic: {price_economic} RON\n• Mediu: {price_medium} RON\n• Premium: {price_premium} RON\nSpuneți ce variantă preferați.', 0, 1),
('tpl_cos_abandonat', 'Follow-up coș abandonat', 'cos-abandonat', 'followup', 'whatsapp',
'Bună ziua {client_name}! Am observat produse în coșul dvs. ({total_amount} RON). Vă putem ajuta să finalizați comanda sau să verificăm compatibilitatea?', 0, 1),
('tpl_olx_disponibil', 'Răspuns OLX — disponibil', 'olx-disponibil', 'marketplace', 'olx',
'Bună ziua! Da, piesa este disponibilă. Preț: {total_amount} RON. Livrare în toată țara. Pentru comandă rapidă: {shop_url}', 0, 1),
('tpl_email_livrare', 'Email confirmare livrare', 'email-livrare', 'livrare', 'email',
'Bună ziua {client_name},\n\nComanda {order_number} a fost expediată.\nAWB: {awb_number}\nCurier: {courier_name}\n\nBesoiu Piese Auto', 0, 1),
('tpl_facebook_pret', 'Facebook — întrebare preț', 'facebook-pret', 'social', 'facebook',
'Bună ziua! Pentru {product_name} prețul este {total_amount} RON (TVA inclus). Stoc limitat — confirmați dacă doriți rezervare.', 0, 1),
('tpl_quick_multumim', 'Snippet: Mulțumim', 'quick-multumim', 'general', 'all',
'Mulțumim pentru mesaj! Un coleg vă răspunde în curând.', 1, 1),
('tpl_quick_30min', 'Snippet: Revenim 30 min', 'quick-30min', 'general', 'all',
'Am primit solicitarea. Revenim în maximum 30 de minute cu răspuns complet.', 1, 1),
('tpl_retur_garantie', 'Retur și garanție', 'retur-garantie', 'postvanzare', 'email',
'Bună ziua {client_name},\n\nPentru retur/garanție la {product_name} avem nevoie de: factură, serie piesă și motiv. Termen legal: 14 zile conform OUG 140/2021.\n\nBesoiu Piese Auto', 0, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
