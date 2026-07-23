<?php
/*
 * ============================================================================
 * FIȘIER: admin/public/index.php (front controller panou administrare)
 * ============================================================================
 * Scop: Punct de intrare principal al panoului admin. Încarcă variabilele
 *       de mediu (.env), configurația DB, și pornește HttpApplication care
 *       gestionează sesiunea, permisiunile, rutele și dispatch-ul request-ului.
 *
 * Include/require:
 *   - admin/bootstrap.php — constante BESOIU_* pentru căi
 *   - admin/vendor/autoload.php — autoloader Composer (Besoiu\Core\)
 *   - app/Config/.env sau admin/.env — variabile de mediu via Dotenv
 *   - admin/config/config.php — parametri DB (host, user, parolă, nume bază)
 *
 * Bază de date: PDO MySQL via Config\Database::getInstance() în HttpApplication.
 *               Conexiune legacy opțională pentru modulul caiet comenzi.
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Front controller Admin Laragon — CORE only, fără admin/modules/.
 */

// Bootstrap: constante de cale admin + flag-uri Laragon
require_once dirname(__DIR__) . '/bootstrap.php';

use Besoiu\Core\Bootstrap\HttpApplication;
use Dotenv\Dotenv;
use Throwable;

try {
    // Autoloader Composer — preferă junction admin/vendor, fallback app/Backend/vendor
    $adminAutoload = BESOIU_ADMIN . '/vendor/autoload.php';
    $backendAutoload = BESOIU_BACKEND . '/vendor/autoload.php';
    if (is_file($adminAutoload)) {
        require_once $adminAutoload;
    } elseif (is_file($backendAutoload)) {
        require_once $backendAutoload;
    } else {
        throw new RuntimeException(
            'Lipsește vendor/autoload.php. Rulează composer install în app/Backend '
            . 'și recreează junction-ul admin/vendor → app/Backend/vendor.'
        );
    }

    // Determină folderul unde se află fișierul .env (prioritate: app/Config/.env)
    $envFile = BESOIU_ADMIN;
    if (is_file(BESOIU_APP . '/Config/.env')) {
        $envFile = BESOIU_APP . '/Config';
    }

    // Încarcă variabilele de mediu (.env) în $_ENV fără a suprascrie cele existente
    $dotenv = Dotenv::createImmutable($envFile);
    $dotenv->safeLoad();

    /** @var array<string, mixed> $applicationConfig */
    // Configurare DB: host, nume bază, user, parolă (+ legacy opțional)
    $applicationConfig = require BESOIU_ADMIN . '/config/config.php';

    // Pornește aplicația admin: sesiune → DB → module → router → controller
    (new HttpApplication($applicationConfig))->run();
} catch (Throwable $exception) {
    // La orice eroare neprinsă, returnăm HTTP 500
    http_response_code(500);

    // APP_DEBUG=1 afișează detalii tehnice; altfel mesaj generic pentru utilizator
    $debug = filter_var($_ENV['APP_DEBUG'] ?? '1', FILTER_VALIDATE_BOOL);
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Admin Laragon error: ' . $exception->getMessage() . "\n";
        echo $exception->getFile() . ':' . $exception->getLine() . "\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ro"><body><h1>Eroare admin</h1></body></html>';
    }
}
