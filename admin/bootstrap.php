<?php
/*
 * ============================================================================
 * FIȘIER: admin/bootstrap.php (bootstrap panou administrare)
 * ============================================================================
 * Scop: Definește constantele de cale specifice adminului și leagă folderul
 *       admin/ de app/Backend (cod Besoiu\Core\) și app/Legacy (compatibilitate).
 *       Este primul fișier inclus din admin/public/index.php și admin/index.php.
 *
 * Include/require: Niciun fișier extern; doar definește constante globale.
 *
 * Bază de date: Nu se conectează aici; conexiunea PDO se inițializează în
 *               HttpApplication::initializeDatabaseConnections().
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Bootstrap admin Laragon — leagă admin/ de app/Backend + app/Core + app/Legacy.
 *
 * Fișierul stă în admin/bootstrap.php → __DIR__ = admin/, părintele = rădăcina site.
 */

// Rădăcina proiectului (părintele folderului admin/) — dacă nu e deja definită
if (!defined('BESOIU_ROOT')) {
    define('BESOIU_ROOT', dirname(__DIR__));
}

// --- Constante de cale pentru structura admin ---

define('BESOIU_ADMIN', __DIR__);                      // Folderul admin/ (config, templates, public)
define('BESOIU_APP', BESOIU_ROOT . '/app');           // Cod PHP partajat (Backend, Core, Legacy)
define('BESOIU_CONFIG', BESOIU_APP . '/Config');       // Configurare globală (.env, DB)
define('BESOIU_BACKEND', BESOIU_APP . '/Backend');     // Cod admin Besoiu\Core\ (src/, vendor/)
define('BESOIU_CORE', BESOIU_APP . '/Core');           // Nucleu storefront (partajat ocazional)
define('BESOIU_LEGACY', BESOIU_APP . '/Legacy');       // Cod legacy (sesiuni, shop-db, CRUD vechi)
define('BESOIU_VIEWS', BESOIU_APP . '/Views');           // View-uri partajate
if (!defined('BESOIU_LIB')) {
    define('BESOIU_LIB', BESOIU_APP . '/Lib');           // Biblioteci auxiliare (scraper, utilitare)
}

// Flag: structură Laragon compactă (admin fără duplicate de module)
define('BESOIU_LARAGON_COMPACT', true);

// Flag: module admin în modules/ (lângă admin/)
define('BESOIU_ADMIN_MODULES', BESOIU_ROOT . '/modules');
define('BESOIU_ADMIN_MODULES_DISABLED', false);
