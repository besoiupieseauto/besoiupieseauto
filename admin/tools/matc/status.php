<?php
declare(strict_types=1);

/**
 * Status baze Matc pe Laragon.
 *
 * Usage: php admin/tools/matc/status.php
 */

require_once __DIR__ . '/lib/MatcDb.php';

$active = matc_read_active_db();

echo "=== Matc DB Status ===\n";
echo "Activ (config): {$active}\n";
echo "Config file:    " . matc_active_config_path() . "\n\n";

foreach (matc_known_databases() as $db) {
    if (!MatcDb::databaseExists($db)) {
        echo "[--] {$db} — lipseste\n";
        continue;
    }

    $stats = MatcDb::stats($db);
    $marker = ($db === $active) ? '>>' : '  ';
    if (empty($stats['available'])) {
        echo "{$marker} {$db} — incompleta: " . ($stats['error'] ?? '?') . "\n";
        if (!empty($stats['tables'])) {
            echo "     tabele: " . implode(', ', $stats['tables']) . "\n";
        }
        continue;
    }

    $products = number_format((int) ($stats['counts']['products'] ?? 0), 0, ',', '.');
    $codes = number_format((int) ($stats['counts']['product_codes'] ?? 0), 0, ',', '.');
    $idx = !empty($stats['lookup_index_ready']) ? 'index OK' : 'FARA index lookup';

    echo "{$marker} {$db} — {$products} produse, {$codes} coduri, {$idx}\n";
}

echo "\nComenzi utile:\n";
echo "  php admin/tools/matc/activate.php --db=" . MATC_DB_SNAPSHOT . " --indexes\n";
echo "  php admin/tools/matc/lookup.php --brand=BOSCH --code=0451103316\n";
echo "  php admin/tools/matc/batch_match.php --file=coduri.txt --out=rezultat.csv\n";
