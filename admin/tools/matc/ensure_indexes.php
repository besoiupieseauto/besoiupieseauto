<?php
declare(strict_types=1);

/**
 * Indexuri lookup pentru match rapid (2M+ produse, mii de coduri).
 *
 * Usage:
 *   php admin/tools/matc/ensure_indexes.php
 *   php admin/tools/matc/ensure_indexes.php --db=besoiu_tecdoc_matc_20260826
 */

set_time_limit(0);

require_once __DIR__ . '/lib/MatcDb.php';

$opts = getopt('', ['db::', 'dry-run']);
$database = trim((string) ($opts['db'] ?? matc_read_active_db()));
$dryRun = isset($opts['dry-run']);

if (!MatcDb::databaseExists($database)) {
    fwrite(STDERR, "Baza {$database} nu exista.\n");
    exit(1);
}

$indexes = [
    'product_codes' => [
        'idx_lookup_brand_code' => 'CREATE INDEX idx_lookup_brand_code ON product_codes (brand_id, code_norm)',
        'idx_lookup_code' => 'CREATE INDEX idx_lookup_code ON product_codes (code_norm)',
        'uq_product_codes' => 'CREATE UNIQUE INDEX uq_product_codes ON product_codes (product_id, code_norm)',
    ],
    'products' => [
        'idx_brand_code' => 'CREATE INDEX idx_brand_code ON products (brand_id, art_code_1)',
        'uq_products_brand_code' => 'CREATE UNIQUE INDEX uq_products_brand_code ON products (brand_id, art_code_1)',
        'idx_products_brand' => 'CREATE INDEX idx_products_brand ON products (brand_id)',
        'idx_products_art_name' => 'CREATE INDEX idx_products_art_name ON products (art_name(191))',
    ],
    'product_compatibilities' => [
        'idx_compat_product' => 'CREATE INDEX idx_compat_product ON product_compatibilities (product_id)',
    ],
    'brands' => [
        'idx_brands_name' => 'CREATE INDEX idx_brands_name ON brands (name)',
        'idx_brands_name_norm' => 'CREATE INDEX idx_brands_name_norm ON brands (name_norm)',
    ],
];

echo "=== Ensure indexes: {$database}" . ($dryRun ? ' [DRY-RUN]' : '') . " ===\n";

$pdo = MatcDb::pdo($database);
$created = 0;
$skipped = 0;

foreach ($indexes as $table => $defs) {
    if (!MatcDb::tableExists($table, $database)) {
        echo "SKIP tabela lipsa: {$table}\n";
        continue;
    }

    foreach ($defs as $name => $sql) {
        if (MatcDb::hasIndex($table, $name, $database)) {
            echo "OK   {$table}.{$name}\n";
            ++$skipped;
            continue;
        }

        echo "ADD  {$table}.{$name}\n";
        if (!$dryRun) {
            try {
                $pdo->exec($sql);
                ++$created;
            } catch (Throwable $e) {
                fwrite(STDERR, "ERR  {$table}.{$name}: " . $e->getMessage() . "\n");
            }
        }
    }
}

echo "\nRezultat: create={$created}, existente={$skipped}\n";
if ($dryRun) {
    echo "Ruleaza fara --dry-run pentru a crea indexurile.\n";
}
