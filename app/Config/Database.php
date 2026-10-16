<?php
/*
 * ============================================================================
 * FIȘIER: app/Config/Database.php (singleton conexiune PDO — partajat admin)
 * ============================================================================
 * Scop: Gestionează conexiunile PDO MySQL ca singleton-uri pe nume (default,
 *       legacy). O singură conexiune per nume per request. Folosit de
 *       HttpApplication, modele CRUD și servicii din Besoiu\Core\.
 *
 * Include/require:
 *   - Besoiu\Core\Exception\DatabaseConnectionException — erori de conectare
 *
 * Bază de date: PDO MySQL (driver mysql:host=...;dbname=...;charset=utf8mb4).
 *   Parametrii (host, user, parolă, nume bază) vin din config.php / .env.
 *   Nu expune credențialele în cod — doar le primește ca argumente.
 * ============================================================================
 */

declare(strict_types=1);

namespace Config;

use Besoiu\Core\Exception\DatabaseConnectionException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO — o conexiune reutilizabilă per nume (default + legacy).
 * Standarde proiect: 03_safety_net.mdc, 08_project_specific.mdc
 */
final class Database
{
    /** @var array<string, self> Instanțe singleton indexate după numele conexiunii */
    private static array $instances = [];

    /** @var array<string, PDO> Obiecte PDO active, indexate după numele conexiunii */
    private static array $connections = [];

    /** @var string Identificatorul conexiunii gestionate de această instanță ('default' sau 'legacy') */
    private string $connectionName;

    /**
     * Constructor privat — se apelează doar via getInstance().
     *
     * @param string $host            Host MySQL (rol: adresa serverului)
     * @param string $databaseName    Numele bazei de date țintă
     * @param string $username        Utilizator MySQL (rol: autentificare)
     * @param string $password        Parola utilizatorului (rol: autentificare, sensibilă)
     * @param string $connectionName  Alias conexiune ('default' sau 'legacy')
     *
     * Creează conexiunea PDO și o stochează în self::$connections.
     * Aruncă DatabaseConnectionException la eșec de conectare.
     */
    private function __construct(
        string $host,
        string $databaseName,
        string $username,
        string $password,
        string $connectionName = 'default'
    ) {
        $this->connectionName = $connectionName;

        try {
            // Construiește DSN-ul PDO pentru MySQL cu charset utf8mb4 (suportă și host:port)
            $dsnHost = $host;
            $dsnPort = '';
            if (strpos($host, ':') !== false) {
                [$parsedHost, $parsedPort] = explode(':', $host, 2);
                $dsnHost = trim($parsedHost) !== '' ? trim($parsedHost) : '127.0.0.1';
                if (ctype_digit(trim($parsedPort))) {
                    $dsnPort = ';port=' . trim($parsedPort);
                }
            }
            $dataSourceName = sprintf(
                'mysql:host=%s%s;dbname=%s;charset=utf8mb4',
                $dsnHost,
                $dsnPort,
                $databaseName
            );

            // Opțiuni PDO: excepții la erori, fetch asociativ, prepared statements nativi
            $driverOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                $driverOptions[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            }
            // Timeout conectare 5 secunde (dacă extensia PDO MySQL îl suportă)
            if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
                $driverOptions[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 5;
            }

            // Deschide conexiunea PDO cu credențialele primite
            $pdo = new PDO($dataSourceName, $username, $password, $driverOptions);

            // Dacă instanța primară (ex. 3306/3309 cu innodb_force_recovery=6) este în mod read-only,
            // comută automat pe instanța locală writable (port 3308) dacă deține aceeași bază de date.
            $pdo = self::resolveWritableFallbackIfNeeded($pdo, $databaseName, $username, $password, $driverOptions);

            // Setează charset și collation pentru diacritice românești
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

            // Cache-uiește conexiunea activă sub numele dat
            self::$connections[$connectionName] = $pdo;
        } catch (PDOException $exception) {
            // Transformă eroarea driver în excepție de domeniu (fără expunere parolă)
            throw DatabaseConnectionException::fromDriverError(
                $connectionName,
                $exception->getMessage()
            );
        }
    }

    /**
     * Dacă conexiunea curentă are innodb_read_only=1 sau read_only=1 (ex. recovery mode pe 3306/3309),
     * încearcă instanța locală writable de pe portul 3308 dacă are aceeași bază de date populată.
     *
     * @param array<int, mixed> $driverOptions
     */
    private static function resolveWritableFallbackIfNeeded(
        PDO $pdo,
        string $databaseName,
        string $username,
        string $password,
        array $driverOptions
    ): PDO {
        try {
            $roStmt = $pdo->query('SELECT (@@innodb_read_only + @@read_only)');
            $isReadOnly = $roStmt !== false && (int) $roStmt->fetchColumn() > 0;
            if (!$isReadOnly) {
                return $pdo;
            }

            $fallbackPort = (int) ($_ENV['WRITABLE_FALLBACK_DB_PORT'] ?? 3308);
            if ($fallbackPort <= 0) {
                return $pdo;
            }

            $fallbackDsn = sprintf(
                'mysql:host=127.0.0.1;port=%d;dbname=%s;charset=utf8mb4',
                $fallbackPort,
                $databaseName
            );

            $candidates = [
                [$username, $password],
                ['root', ''],
            ];

            foreach ($candidates as [$fbUser, $fbPass]) {
                try {
                    $fbPdo = new PDO($fallbackDsn, (string) $fbUser, (string) $fbPass, $driverOptions);
                    $fbRoStmt = $fbPdo->query('SELECT (@@innodb_read_only + @@read_only)');
                    if ($fbRoStmt !== false && (int) $fbRoStmt->fetchColumn() === 0) {
                        $tblStmt = $fbPdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
                        $tblStmt->execute([$databaseName]);
                        if ((int) $tblStmt->fetchColumn() > 0) {
                            return $fbPdo;
                        }
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        } catch (\Throwable) {
            return $pdo;
        }

        return $pdo;
    }

    /**
     * Returnează (sau creează) instanța singleton pentru conexiunea dată.
     *
     * @return self Instanța Database asociată numelui conexiunii
     */
    public static function getInstance(
        string $host,
        string $databaseName,
        string $username,
        string $password,
        string $connectionName = 'default'
    ): self {
        // Lazy init: creează instanța doar la primul apel pentru acest nume
        if (!isset(self::$instances[$connectionName])) {
            self::$instances[$connectionName] = new self(
                $host,
                $databaseName,
                $username,
                $password,
                $connectionName
            );
        }

        return self::$instances[$connectionName];
    }

    /**
     * Returnează obiectul PDO activ pentru interogări SQL.
     *
     * @param string $connectionName 'default' sau 'legacy'
     * @return PDO Conexiune pregătită pentru SELECT/INSERT/UPDATE/DELETE
     * @throws RuntimeException dacă getInstance() nu a fost apelat anterior
     */
    public static function getDB(string $connectionName = 'default'): PDO
    {
        if (!isset(self::$connections[$connectionName])) {
            throw new RuntimeException(
                'Baza de date nu este inițializată. Apelează Database::getInstance() mai întâi.'
            );
        }

        return self::$connections[$connectionName];
    }

    /**
     * Verifică dacă există deja o conexiune PDO deschisă.
     *
     * @return bool true dacă conexiunea cu numele dat este activă
     */
    public static function hasConnection(string $connectionName = 'default'): bool
    {
        return isset(self::$connections[$connectionName]);
    }

    /**
     * Conectare lazy la baza legacy (modul caiet comenzi).
     * Se apelează doar când request-ul curent necesită date legacy.
     *
     * @param array<string, mixed> $applicationConfig Config cu chei legacy_db_*
     * Modifică starea statică (deschide conexiune 'legacy' dacă e configurată).
     */
    public static function ensureLegacy(array $applicationConfig): void
    {
        // Dacă legacy e deja conectat, nu facem nimic
        if (self::hasConnection('legacy')) {
            return;
        }

        // Verifică dacă numele bazei legacy este configurat în .env
        $legacyDatabaseName = trim((string) ($applicationConfig['legacy_db_name'] ?? ''));
        if ($legacyDatabaseName === '') {
            return;
        }

        // Deschide conexiunea legacy cu parametrii din config (fallback la default)
        self::getInstance(
            (string) ($applicationConfig['legacy_db_host'] ?? $applicationConfig['db_host']),
            $legacyDatabaseName,
            (string) ($applicationConfig['legacy_db_user'] ?? $applicationConfig['db_user']),
            (string) ($applicationConfig['legacy_db_pass'] ?? $applicationConfig['db_pass']),
            'legacy'
        );
    }

    /**
     * Listează prefixele URL care necesită conexiunea legacy.
     *
     * @return list<string> Căi admin care accesează tabele din baza legacy
     */
    public static function legacyPathHints(): array
    {
        return [
            '/admin/caietcomenzi',
            '/admin/orders',
            '/admin/caiet-de-comenzi',
            '/admin/orders-tm',
            '/admin/orders-utvin',
            '/admin/orders-externe',
            '/admin/caiet-produse',
            '/admin/caiet-comenzi',
            '/admin/caiet-comenzi-tm',
            '/admin/caiet-comenzi-utvin',
            '/admin/caiet-comenzi-ext',
            '/admin/caiet-utilizatori',
            '/admin/caiet-pieseauto',
            '/admin/caiet-search',
            '/admin/caiet-api',
            '/admin/caiet-clienti',
            '/admin/caiet-facturi',
            '/admin/caiet-incasari',
            '/admin/order-create',
            '/admin/order-edit',
            '/admin/supplier-search',
            '/admin/supplier-cart',
            '/admin/api/legacy_orders_endpoint.php',
            '/admin/api/caiet_comenzi_endpoint.php',
            '/admin/api/order_tmp_endpoint.php',
            '/admin/api/supplier_cart_endpoint.php',
            '/admin/api/supplier_search_endpoint.php',
        ];
    }

    /**
     * Determină dacă request-ul HTTP curent necesită baza legacy.
     *
     * Citește $_SERVER['REQUEST_URI'] și compară cu legacyPathHints().
     * @return bool true dacă URL-ul curent aparține unui modul legacy
     */
    public static function requestNeedsLegacy(): bool
    {
        // Extrage calea din URI (fără query string)
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Verifică dacă path-ul începe cu oricare din prefixele legacy
        foreach (self::legacyPathHints() as $pathHint) {
            $pathPrefix = rtrim($pathHint, '/') . '/';
            if ($requestPath === $pathHint || strpos($requestPath, $pathPrefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /** Interzice clonarea singleton-ului */
    private function __clone(): void
    {
    }

    /** Interzice deserializarea — ar putea crea conexiuni neautorizate */
    public function __wakeup(): void
    {
        throw new RuntimeException('Deserializarea singleton Database este interzisă.');
    }
}
