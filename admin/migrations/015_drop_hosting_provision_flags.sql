-- ============================================================
-- MIGRARE: Elimina flag-urile creare automata FTP/email
-- ============================================================

ALTER TABLE `furnizori`
    DROP COLUMN IF EXISTS `conn_create_ftp`,
    DROP COLUMN IF EXISTS `conn_create_inbox`;
