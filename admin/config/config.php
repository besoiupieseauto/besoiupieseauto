<?php
/*
 * ============================================================================
 * FIȘIER: admin/config/config.php (configurare conexiune bază de date — admin)
 * ============================================================================
 * Scop: Returnează parametrii de conectare MySQL pentru panoul admin.
 *       Identic ca structură cu app/Config/config.php; este inclus din
 *       admin/public/index.php înainte de HttpApplication::run().
 *
 * Include/require: Niciun fișier; citește $_ENV populat de Dotenv.
 *
 * Bază de date: PDO MySQL (conexiune default + legacy opțională).
 * ============================================================================
 */

return [
    // Host server MySQL — din variabila de mediu DB_HOST sau localhost implicit
    'db_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',

    // Numele bazei de date principale (magazin + admin)
    'db_name' => $_ENV['DB_NAME'] ?? 'besoiupieseauto.ro',

    // Utilizator MySQL cu permisiuni pe baza principală
    'db_user' => $_ENV['DB_USER'] ?? 'root',

    // Parola utilizatorului — sensibilă, provine exclusiv din .env
    'db_pass' => $_ENV['DB_PASS'] ?? '',

    // --- Baza de date legacy (caiet comenzi, comenzi vechi) ---

    'legacy_db_host' => $_ENV['LEGACY_DB_HOST'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),
    'legacy_db_name' => $_ENV['LEGACY_DB_NAME'] ?? '',
    'legacy_db_user' => $_ENV['LEGACY_DB_USER'] ?? ($_ENV['DB_USER'] ?? 'root'),
    'legacy_db_pass' => $_ENV['LEGACY_DB_PASS'] ?? ($_ENV['DB_PASS'] ?? ''),
];
