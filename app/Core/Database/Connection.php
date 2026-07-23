<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Database/Connection.php
 * ============================================================================
 * Scop: Singleton pentru conexiunea PDO a vitrinei (shop). Oferă o singură
 *       instanță PDO reutilizabilă în toate modulele storefront.
 *
 * Include/require:
 *   - app/Legacy/shop-db.php — funcția shop_db_bootstrap() care creează PDO
 *
 * Bază de date: PDO MySQL; parametrii de conectare sunt în shop-db.php / .env.
 *               Conexiunea se deschide lazy (la primul apel get()).
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Database;

use PDO;

/**
 * Conexiune unică storefront → delegă la shop-db (PDO prepared statements).
 * Pattern singleton: o singură conexiune PDO per request HTTP.
 */
final class Connection
{
    /** @var PDO|null Instanța PDO cache-uită; null până la primul apel get() */
    private static ?PDO $pdo = null;

    /**
     * Returnează conexiunea PDO activă; o creează la primul apel.
     *
     * @return PDO Conexiune MySQL cu ERRMODE_EXCEPTION și FETCH_ASSOC
     * Nu primește parametri — citește config din shop-db.php.
     * Modifică starea statică (cache PDO); nu scrie în DB.
     */
    public static function get(): PDO
    {
        // Dacă conexiunea există deja în cache, o returnăm direct
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // Determină calea către shop-db.php din Legacy (compatibil cu constantele BESOIU_*)
        $shopDb = (defined('BESOIU_LEGACY') ? BESOIU_LEGACY : (defined('BESOIU_ROOT') ? BESOIU_ROOT . '/app/Legacy' : dirname(__DIR__, 2) . '/app/Legacy')) . '/shop-db.php';

        // Încarcă funcția de bootstrap a conexiunii PDO
        require_once $shopDb;

        // Creează și cache-uiește conexiunea PDO via funcția legacy
        self::$pdo = shop_db_bootstrap();

        return self::$pdo;
    }
}
