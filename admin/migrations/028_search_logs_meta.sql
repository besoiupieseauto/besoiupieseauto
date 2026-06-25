-- Detalii scanare (filtre, preview produse) pentru jurnal căutări

ALTER TABLE `search_logs`
    ADD COLUMN IF NOT EXISTS `meta_json` JSON NULL DEFAULT NULL AFTER `notice`;
