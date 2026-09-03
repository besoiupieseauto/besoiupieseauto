<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

final class MatcDb
{
    /** @var array<string, PDO> */
    private static array $pool = [];

    public static function pdo(?string $database = null): PDO
    {
        $db = $database ?? matc_read_active_db();
        if (isset(self::$pool[$db])) {
            return self::$pool[$db];
        }

        [$user, $pass] = matc_mysql_credentials();
        $pdo = new PDO(matc_mysql_dsn($db), $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);

        return self::$pool[$db] = $pdo;
    }

    public static function serverPdo(): PDO
    {
        $key = '__server__';
        if (isset(self::$pool[$key])) {
            return self::$pool[$key];
        }

        [$user, $pass] = matc_mysql_credentials();
        $pdo = new PDO('mysql:host=' . matc_mysql_host() . ';charset=utf8mb4', $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pool[$key] = $pdo;
    }

    public static function normalizeCode(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', trim(str_replace(['"', "'"], '', $value))) ?? '');
    }

    public static function normalizeBrand(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function databaseExists(string $database): bool
    {
        $pdo = self::serverPdo();
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
        $stmt->execute([$database]);

        return (bool) $stmt->fetchColumn();
    }

    public static function tableExists(string $table, ?string $database = null): bool
    {
        $db = $database ?? matc_read_active_db();
        $pdo = self::serverPdo();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$db, $table]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return list<string> */
    public static function listTables(?string $database = null): array
    {
        $db = $database ?? matc_read_active_db();
        $pdo = self::serverPdo();
        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME'
        );
        $stmt->execute([$db]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'TABLE_NAME');
    }

    /** @return list<array{Key_name:string,Column_name:string,Non_unique:int}> */
    public static function listIndexes(string $table, ?string $database = null): array
    {
        $pdo = self::pdo($database);
        $stmt = $pdo->query('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '`');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function hasIndex(string $table, string $indexName, ?string $database = null): bool
    {
        foreach (self::listIndexes($table, $database) as $row) {
            if (($row['Key_name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public static function stats(?string $database = null): array
    {
        $db = $database ?? matc_read_active_db();
        if (!self::databaseExists($db)) {
            return ['database' => $db, 'available' => false, 'error' => 'Database missing'];
        }

        if (!self::tableExists('products', $db)) {
            return [
                'database' => $db,
                'available' => false,
                'error' => 'Tabela products lipseste',
                'tables' => self::listTables($db),
            ];
        }

        $pdo = self::pdo($db);
        $tables = ['products', 'brands', 'product_codes', 'product_compatibilities', 'product_images'];
        $counts = [];
        foreach ($tables as $table) {
            if (!self::tableExists($table, $db)) {
                $counts[$table] = null;
                continue;
            }
            if ($table === 'product_compatibilities') {
                $counts[$table] = (int) $pdo->query(
                    "SELECT table_rows FROM information_schema.tables "
                    . "WHERE table_schema = " . $pdo->quote($db)
                    . " AND table_name = 'product_compatibilities'"
                )->fetchColumn();
                continue;
            }
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        }

        $indexOk = self::hasIndex('product_codes', 'idx_lookup_brand_code', $db);

        return [
            'database' => $db,
            'available' => true,
            'counts' => $counts,
            'lookup_index_ready' => $indexOk,
            'has_view_lookup' => self::tableExists('v_product_lookup', $db),
        ];
    }

    public static function resolveBrandId(PDO $pdo, string $brand): ?int
    {
        $brandUp = self::normalizeBrand($brand);
        if ($brandUp === '') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id FROM brands WHERE name = ? OR name_norm = ? LIMIT 1');
        $stmt->execute([$brandUp, self::normalizeCode($brandUp)]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}
