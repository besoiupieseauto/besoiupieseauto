<?php

declare(strict_types=1);

namespace Besoiu\Services\Orders;

use PDO;
use RuntimeException;

/**
 * Creează tabelele ERP legacy minime dacă lipsesc (migrări 020–023).
 */
final class LegacyOrderSchemaBootstrap
{
    private static bool $ready = false;

    /** @var list<string> */
    private const SQL_FILES = [
        '020_supplier_carts_legacy_table.sql',
        '021_tmp_legacy_table.sql',
        '022_legacy_orders_core.sql',
        '023_legacy_external_orders.sql',
    ];

    public static function ensure(?PDO $pdo): void
    {
        if ($pdo === null) {
            throw new RuntimeException('Conexiune legacy indisponibilă (LEGACY_DB_*).');
        }
        if (self::$ready) {
            return;
        }

        $dir = dirname(__DIR__, 3) . '/migrations';
        foreach (self::SQL_FILES as $file) {
            $path = $dir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            self::execSqlFile($pdo, $path);
        }

        self::$ready = true;
    }

    private static function execSqlFile(PDO $pdo, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $lines = array_filter(
                array_map('trim', preg_split('/\R/', $statement) ?: []),
                static fn (string $line): bool => $line !== '' && !str_starts_with($line, '--')
            );
            $statement = trim(implode("\n", $lines));
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }
}
