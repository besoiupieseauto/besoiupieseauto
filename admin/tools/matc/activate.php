<?php
declare(strict_types=1);

/**
 * Activeaza baza Matc de lucru + indexuri lookup.
 *
 * Usage:
 *   php admin/tools/matc/activate.php
 *   php admin/tools/matc/activate.php --db=besoiu_tecdoc_matc_20260826
 *   php admin/tools/matc/activate.php --db=besoiu_tecdoc_matc_20260826 --indexes
 */

set_time_limit(0);

require_once __DIR__ . '/lib/MatcDb.php';

$opts = getopt('', ['db::', 'indexes', 'status']);
$database = trim((string) ($opts['db'] ?? MATC_DB_SNAPSHOT));
$runIndexes = isset($opts['indexes']) || !isset($opts['status']);

if (!MatcDb::databaseExists($database)) {
    fwrite(STDERR, "Baza {$database} nu exista pe MySQL.\n");
    exit(1);
}

$stats = MatcDb::stats($database);
if (empty($stats['available'])) {
    fwrite(STDERR, 'Baza nu e gata: ' . ($stats['error'] ?? 'unknown') . "\n");
    if (!empty($stats['tables'])) {
        echo 'Tabele: ' . implode(', ', $stats['tables']) . "\n";
    }
    exit(1);
}

matc_write_active_db($database, 'activate.php ' . date('Y-m-d H:i:s'));

echo "=== Matc ACTIV ===\n";
echo "Database: {$database}\n";
echo "Produse:  " . number_format((int) ($stats['counts']['products'] ?? 0), 0, ',', '.') . "\n";
echo "Coduri:   " . number_format((int) ($stats['counts']['product_codes'] ?? 0), 0, ',', '.') . "\n";
echo "Compat:   ~" . number_format((int) ($stats['counts']['product_compatibilities'] ?? 0), 0, ',', '.') . " (estimat)\n";
echo "Index lookup: " . (!empty($stats['lookup_index_ready']) ? 'DA' : 'NU') . "\n";
echo "Config:   " . matc_active_config_path() . "\n\n";

if ($runIndexes && empty($stats['lookup_index_ready'])) {
    echo "Creare indexuri lookup (poate dura cateva minute pe 3M coduri)...\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/ensure_indexes.php') . ' --db=' . escapeshellarg($database));
    echo "\n";
}

if ($database === MATC_DB_SNAPSHOT) {
    echo "NOTA: Snapshot full import. Pentru alias scurt «" . MATC_DB_ACTIVE_NAME . "», foloseste aceeasi baza via active_db.json\n";
    echo "      sau redenumire manuala MySQL daca vrei neaparat numele scurt.\n";
}

echo "Gata. Test: php admin/tools/matc/lookup.php --brand=DAYCO --code=KTBWP1230\n";
