<?php
declare(strict_types=1);

/**
 * Lookup simplu: brand + cod in baza Matc activa.
 *
 * Usage:
 *   php admin/tools/matc/lookup.php --brand=DAYCO --code=KTBWP1230
 *   php admin/tools/matc/lookup.php --brand=BOSCH --code=0451103316 --compat=5
 *   php admin/tools/matc/lookup.php --brand=DAYCO --code=KTBWP1230 --db=besoiu_tecdoc_matc_20260826 --json
 */

require_once __DIR__ . '/lib/MatcLookup.php';

$opts = getopt('', ['brand:', 'code:', 'db::', 'compat::', 'json']);
$brand = trim((string) ($opts['brand'] ?? ''));
$code = trim((string) ($opts['code'] ?? ''));
$database = trim((string) ($opts['db'] ?? matc_read_active_db()));
$compatLimit = max(0, (int) ($opts['compat'] ?? 0));
$asJson = isset($opts['json']);

if ($brand === '' || $code === '') {
    fwrite(STDERR, "Usage: php lookup.php --brand=BRAND --code=COD [--db=...] [--compat=N] [--json]\n");
    exit(1);
}

$result = MatcLookup::find($brand, [$code], $database);

if ($asJson) {
    if ($result['found'] && $compatLimit > 0) {
        $result['compat_sample'] = MatcLookup::compatRows(
            (int) $result['product']['product_id'],
            $compatLimit,
            $database
        );
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit($result['found'] ? 0 : 2);
}

echo "DB: {$database}\n";
echo "Caut: {$brand} / {$code}\n";

if (!$result['found']) {
    echo "Rezultat: NEGASIT ({$result['query_ms']} ms)\n";
    exit(2);
}

$p = $result['product'];
echo "Rezultat: GASIT ({$result['query_ms']} ms)\n";
echo "  product_id:  {$p['product_id']}\n";
echo "  brand:       {$p['brand_name']}\n";
echo "  art_code_1:  {$p['art_code_1']}\n";
echo "  art_code_2:  {$p['art_code_2']}\n";
echo "  art_name:    {$p['art_name']}\n";
echo "  ttc_art_id:  {$p['ttc_art_id']}\n";
echo "  compat:      {$result['compat_count']}\n";

if ($compatLimit > 0) {
    echo "\nCompat (primele {$compatLimit}):\n";
    foreach (MatcLookup::compatRows((int) $p['product_id'], $compatLimit, $database) as $row) {
        echo "  - {$row['car_brand']} {$row['car_model']} {$row['car_typ']} ({$row['car_of_year']}-{$row['car_to_year']})\n";
    }
}
