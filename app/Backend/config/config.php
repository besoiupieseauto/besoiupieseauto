<?php
/*
 * ============================================================================
 * FIȘIER: app/Backend/config/config.php (configurare DB — backend duplicat)
 * ============================================================================
 * Scop: Copie a configurației DB folosită de componentele din app/Backend/
 *       care referă explicit acest path. Structură identică cu app/Config/config.php.
 *
 * Include/require: Niciun fișier extern.
 *
 * Bază de date: PDO MySQL via Config\Database.
 * ============================================================================
 */

return [
    // Host MySQL — din DB_HOST sau localhost implicit
    'db_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    // Numele bazei de date principale
    'db_name' => $_ENV['DB_NAME'] ?? 'besoiupieseauto.ro',
    // Utilizator MySQL
    'db_user' => $_ENV['DB_USER'] ?? 'root',
    // Parolă — sensibilă, din .env
    'db_pass' => $_ENV['DB_PASS'] ?? '',
    // --- Legacy DB (caiet comenzi) ---
    'legacy_db_host' => $_ENV['LEGACY_DB_HOST'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),
    'legacy_db_name' => $_ENV['LEGACY_DB_NAME'] ?? '',
    'legacy_db_user' => $_ENV['LEGACY_DB_USER'] ?? ($_ENV['DB_USER'] ?? 'root'),
    'legacy_db_pass' => $_ENV['LEGACY_DB_PASS'] ?? ($_ENV['DB_PASS'] ?? ''),
];
