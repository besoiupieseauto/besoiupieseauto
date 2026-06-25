-- ============================================================
-- MIGRARE 027: Indexuri performanță admin + site
-- ============================================================

ALTER TABLE `routes`
    ADD INDEX `idx_routes_method_path_active` (`method`, `path`, `is_active`);

ALTER TABLE `comenzi`
    ADD INDEX `idx_comenzi_created_at` (`created_at`),
    ADD INDEX `idx_comenzi_order_status` (`order_status`);

ALTER TABLE `produse`
    ADD INDEX `idx_produse_status` (`status`);

ALTER TABLE `import_produse`
    ADD INDEX `idx_import_produse_status` (`status`);

ALTER TABLE `messages`
    ADD INDEX `idx_messages_created_id` (`created_at`, `id`);
