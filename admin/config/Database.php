<?php

declare(strict_types=1);

namespace Config;

use Evasystem\Core\Exception\DatabaseConnectionException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO — o conexiune per nume (default + legacy).
 * Standarde: 03_safety_net.mdc, 08_project_specific.mdc
 */
final class Database
{
    /** @var array<string, self> */
    private static array $instances = [];

    /** @var array<string, PDO> */
    private static array $connections = [];

    private string $connectionName;

    private function __construct(
        string $host,
        string $databaseName,
        string $username,
        string $password,
        string $connectionName = 'default'
    ) {
        $this->connectionName = $connectionName;

        try {
            $dataSourceName = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $host,
                $databaseName
            );

            $driverOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
                $driverOptions[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 5;
            }

            $pdo = new PDO($dataSourceName, $username, $password, $driverOptions);
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

            self::$connections[$connectionName] = $pdo;
        } catch (PDOException $exception) {
            throw DatabaseConnectionException::fromDriverError(
                $connectionName,
                $exception->getMessage()
            );
        }
    }

    public static function getInstance(
        string $host,
        string $databaseName,
        string $username,
        string $password,
        string $connectionName = 'default'
    ): self {
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

    public static function getDB(string $connectionName = 'default'): PDO
    {
        if (!isset(self::$connections[$connectionName])) {
            throw new RuntimeException(
                'Baza de date nu este inițializată. Apelează Database::getInstance() mai întâi.'
            );
        }

        return self::$connections[$connectionName];
    }

    public static function hasConnection(string $connectionName = 'default'): bool
    {
        return isset(self::$connections[$connectionName]);
    }

    /**
     * Conectare lazy la BD legacy (modul caiet comenzi).
     *
     * @param array<string, mixed> $applicationConfig
     */
    public static function ensureLegacy(array $applicationConfig): void
    {
        if (self::hasConnection('legacy')) {
            return;
        }

        $legacyDatabaseName = trim((string) ($applicationConfig['legacy_db_name'] ?? ''));
        if ($legacyDatabaseName === '') {
            return;
        }

        self::getInstance(
            (string) ($applicationConfig['legacy_db_host'] ?? $applicationConfig['db_host']),
            $legacyDatabaseName,
            (string) ($applicationConfig['legacy_db_user'] ?? $applicationConfig['db_user']),
            (string) ($applicationConfig['legacy_db_pass'] ?? $applicationConfig['db_pass']),
            'legacy'
        );
    }

    /** @return list<string> */
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
            '/admin/caiet-clienti',
            '/admin/caiet-facturi',
            '/admin/caiet-incasari',
            '/admin/order-create',
            '/admin/order-edit',
            '/admin/api/legacy_orders_endpoint.php',
            '/admin/api/caiet_comenzi_endpoint.php',
            '/admin/api/order_tmp_endpoint.php',
        ];
    }

    public static function requestNeedsLegacy(): bool
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        foreach (self::legacyPathHints() as $pathHint) {
            $pathPrefix = rtrim($pathHint, '/') . '/';
            if ($requestPath === $pathHint || strpos($requestPath, $pathPrefix) === 0) {
                return true;
            }
        }

        return false;
    }

    private function __clone(): void
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('Deserializarea singleton Database este interzisă.');
    }
}
