<?php
declare(strict_types=1);

/**
 * Import arbore categorii Besoiu
 *
 * Usage:
 *   php app/Backend/tools/import_besoiu_category_tree.php preview
 *   php app/Backend/tools/import_besoiu_category_tree.php export
 *   php app/Backend/tools/import_besoiu_category_tree.php import [--keep]
 *   php app/Backend/tools/import_besoiu_category_tree.php excel-preview
 *   php app/Backend/tools/import_besoiu_category_tree.php excel-export
 *   php app/Backend/tools/import_besoiu_category_tree.php excel-import [--keep]
 *   php app/Backend/tools/import_besoiu_category_tree.php renew
 *   php app/Backend/tools/import_besoiu_category_tree.php purge-alternate
 */
$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

require_once BESOIU_LEGACY . '/shop-db.php';

$config = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

use Besoiu\Services\BesoiuCategoryTreeExcelReader;
use Besoiu\Services\BesoiuCategoryTreeImportService;
use Besoiu\Services\BesoiuCategoryTreeParser;

$action = strtolower(trim($argv[1] ?? 'preview'));
$keepExisting = in_array('--keep', $argv, true);
$service = new BesoiuCategoryTreeImportService();

if (str_starts_with($action, 'excel-')) {
    if (!BesoiuCategoryTreeExcelReader::isAvailable()) {
        fwrite(STDERR, "Excel lipsă: categorie/categorii_tree_final.xlsx\n");
        exit(1);
    }

    $reader = new BesoiuCategoryTreeExcelReader();
    $payload = $reader->load();
    $validation = $payload['stats'];

    echo "=== EXCEL CATEGORII BESOIU ===\n";
    echo 'Sheet-uri: ' . implode(', ', $payload['sheets']) . "\n";
    echo 'Total: ' . ($validation['total'] ?? 0) . "\n";
    echo 'Frunze: ' . ($validation['leaves'] ?? 0) . "\n";
    echo 'Sinonime: ' . count($payload['synonyms']) . "\n";
    echo 'Secundare: ' . count($payload['secondary']) . "\n";

    if ($action === 'excel-preview') {
        exit(0);
    }

    if ($action === 'excel-export') {
        $exports = $reader->exportJsonSidecars($payload['secondary'], $payload['synonyms']);
        echo "\nJSON secundare: " . ($exports['secondary_path'] ?? '') . "\n";
        echo 'JSON sinonime: ' . ($exports['synonyms_path'] ?? '') . "\n";
        exit(0);
    }

    if ($action === 'excel-import') {
        $result = $service->importFromExcel(!$keepExisting);
        echo "\nImport Excel OK\n";
        echo 'Noi: ' . ($result['imported'] ?? 0) . ', actualizate: ' . ($result['updated'] ?? 0) . "\n";
        echo 'Sinonime: ' . ($result['synonyms']['imported'] ?? 0) . ', secundare: ' . ($result['secondary']['imported'] ?? 0) . "\n";
        exit(0);
    }
}

$parser = new BesoiuCategoryTreeParser();
$nodes = $parser->parseFile();
$validation = $parser->validate($nodes);

echo "=== ARBORE CATEGORII BESOIU (vizual.txt) ===\n";
echo 'Total: ' . $validation['total'] . "\n";
echo 'Grupe (rădăcini): ' . $validation['roots'] . "\n";
echo 'Frunze ART_NAME: ' . $validation['leaves'] . "\n";
echo 'Adâncime max: ' . $validation['max_depth'] . "\n";
echo 'Excel disponibil: ' . (BesoiuCategoryTreeExcelReader::isAvailable() ? 'da' : 'nu') . "\n";

if ($validation['duplicate_tree_ids'] !== []) {
    echo 'ATENȚIE duplicate tree_id: ' . implode(', ', $validation['duplicate_tree_ids']) . "\n";
    exit(1);
}

if ($action === 'preview') {
    echo "\nPrimele 5 frunze:\n";
    $shown = 0;
    foreach ($nodes as $node) {
        if (empty($node['is_leaf'])) {
            continue;
        }
        echo '  [' . $node['tree_id'] . '] ' . $node['name'] . "\n";
        $shown++;
        if ($shown >= 5) {
            break;
        }
    }
    exit(0);
}

if ($action === 'export') {
    $export = $service->exportParsedJson();
    echo "\nExport: " . ($export['path'] ?? '') . "\n";
    exit(0);
}

if ($action === 'import') {
    $result = $service->importFromBestSource(!$keepExisting);
    echo "\nImport OK (" . ($result['source'] ?? 'unknown') . ")\n";
    echo 'Noi: ' . ($result['imported'] ?? 0) . ', actualizate: ' . ($result['updated'] ?? 0) . ', șterse: ' . ($result['deleted'] ?? 0) . "\n";
    echo 'Sinonime: ' . ($result['synonyms']['imported'] ?? 0) . ', secundare: ' . ($result['secondary']['imported'] ?? 0) . "\n";
    exit(0);
}

if ($action === 'renew') {
    $result = $service->renewCatalogFromExcel();
    echo "\nReînnoire globală OK\n";
    echo 'Sursă: ' . ($result['source'] ?? 'unknown');
    if (!empty($result['fallback'])) {
        echo ' (fallback: ' . $result['fallback'] . ')';
    }
    echo "\n";
    echo 'Noi: ' . ($result['imported'] ?? 0) . ', actualizate: ' . ($result['updated'] ?? 0) . "\n";
    echo 'Frunze: ' . ($result['leaves'] ?? 0) . "\n";
    echo 'Sinonime: ' . ($result['synonyms']['imported'] ?? 0) . ', secundare: ' . ($result['secondary']['imported'] ?? 0) . "\n";
    exit(0);
}

if ($action === 'purge-alternate') {
    $result = $service->purgeAlternateCatalog();
    echo "\nCurățare catalog alternativ OK\n";
    echo 'Șterse: ' . ($result['deleted'] ?? 0) . ', rămase Besoiu: ' . ($result['remaining_besoiu'] ?? 0) . "\n";
    exit(0);
}

fwrite(STDERR, "Acțiune necunoscută: {$action}\n");
exit(1);
