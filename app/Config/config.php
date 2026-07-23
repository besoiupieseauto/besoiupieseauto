<?php
/*
 * ============================================================================
 * FIȘIER: app/Config/config.php (configurare conexiune bază de date — storefront)
 * ============================================================================
 * Scop: Returnează un array asociativ cu parametrii de conectare la MySQL.
 *       Valorile provin din variabilele de mediu (.env) cu fallback-uri locale
 *       pentru dezvoltare Laragon. Este inclus de serviciile care au nevoie
 *       de acces direct la configurarea DB din partea de vitrină.
 *
 * Include/require: Niciun fișier; doar citește $_ENV (populat de Dotenv în admin).
 *
 * Bază de date:
 *   - Conexiune principală (default): PDO MySQL via Config\Database
 *   - Conexiune legacy (opțională): bază separată pentru modulul caiet comenzi
 *   Parametrii sensibili (parolă) nu sunt hardcodate — vin din .env.
 * ============================================================================
 */

return [
    // Host MySQL — adresa serverului (local: 127.0.0.1; producție: din DB_HOST)
    'db_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',

    // Numele bazei de date principale a magazinului
    'db_name' => $_ENV['DB_NAME'] ?? 'besoiupieseauto.ro',

    // Utilizatorul MySQL cu drepturi pe baza principală
    'db_user' => $_ENV['DB_USER'] ?? 'root',

    // Parola utilizatorului MySQL — goală implicit pe Laragon local
    'db_pass' => $_ENV['DB_PASS'] ?? '',

    // --- Conexiune legacy (modul caiet comenzi / comenzi vechi) ---

    // Host pentru baza legacy; dacă lipsește, folosește același host ca baza principală
    'legacy_db_host' => $_ENV['LEGACY_DB_HOST'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),

    // Numele bazei legacy — gol dacă modulul nu e activ
    'legacy_db_name' => $_ENV['LEGACY_DB_NAME'] ?? '',

    // Utilizator legacy; fallback la utilizatorul bazei principale
    'legacy_db_user' => $_ENV['LEGACY_DB_USER'] ?? ($_ENV['DB_USER'] ?? 'root'),

    // Parolă legacy; fallback la parola bazei principale
    'legacy_db_pass' => $_ENV['LEGACY_DB_PASS'] ?? ($_ENV['DB_PASS'] ?? ''),
];
