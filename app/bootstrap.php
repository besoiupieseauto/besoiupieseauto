<?php
/*
 * ============================================================================
 * FIȘIER: app/bootstrap.php (bootstrap central storefront)
 * ============================================================================
 * Scop: Inițializează structura de directoare a proiectului prin constante
 *       globale și încarcă autoloader-ul pentru clasele namespace Storefront\.
 *       Este inclus din index.php (vitrină) înainte de pornirea aplicației.
 *
 * Include/require:
 *   - app/Core/autoload.php — înregistrează autoloader PSR-4 pentru
 *     Storefront\Core\ și Storefront\Modules\{Modul}\.
 *
 * Bază de date: Nu se conectează aici; doar pregătește căile către Legacy/
 *               unde există shop-db.php pentru conexiunea PDO a vitrinei.
 * ============================================================================
 */

// Verificare strictă a tipurilor PHP
declare(strict_types=1);

/**
 * Căi centralizate — singura sursă de adevăr pentru structura compactă.
 *
 * Root (5 foldere):
 *   api/     — endpoint-uri JSON
 *   assets/  — css, js, imagini
 *   app/     — tot PHP-ul (Core, Modules, Views, Legacy, Backend, Storage)
 */
// Dacă BESOIU_ROOT nu e deja definit (ex. din index.php), îl setăm la părintele app/
if (!defined('BESOIU_ROOT')) {
    define('BESOIU_ROOT', dirname(__DIR__));
}

// --- Constante de cale — folosite în tot proiectul pentru include/require sigur ---

define('BESOIU_APP', BESOIU_ROOT . '/app');           // Rădăcina codului PHP al aplicației
define('BESOIU_CONFIG', BESOIU_APP . '/Config');       // Fișiere de configurare (.env, rute, DB)
define('BESOIU_CORE', BESOIU_APP . '/Core');           // Nucleu storefront (router, template, DB)
define('BESOIU_STOREFRONT_MODULES', BESOIU_APP . '/Modules'); // Module funcționale (Blog, Shop, etc.)
define('BESOIU_VIEWS', BESOIU_APP . '/Views');           // Template-uri HTML ale vitrinei
define('BESOIU_LEGACY', BESOIU_APP . '/Legacy');         // Cod vechi compatibil (shop-db, sesiuni)
define('BESOIU_BACKEND', BESOIU_APP . '/Backend');       // Cod admin (Besoiu\Core\) — partajat
define('BESOIU_LIB', BESOIU_APP . '/Lib');               // Biblioteci auxiliare (scraper, utilitare)
define('BESOIU_STORAGE', BESOIU_APP . '/Storage');       // Stocare fișiere generate (cache, upload)
define('BESOIU_CACHE_TECDOC', BESOIU_STORAGE . '/cache/tecdoc'); // Cache index TecDoc
define('BESOIU_UPLOADS', BESOIU_STORAGE . '/uploads');   // Fișiere încărcate de utilizatori/admin
define('BESOIU_SEO', BESOIU_STORAGE . '/seo');           // Fișiere SEO generate (sitemap, HTML static)
define('BESOIU_ASSETS', BESOIU_ROOT . '/assets');      // Resurse statice publice (CSS, JS, imagini)

// Încarcă autoloader-ul care mapează namespace Storefront\ → fișiere din Core/ și Modules/
require_once BESOIU_CORE . '/autoload.php';
