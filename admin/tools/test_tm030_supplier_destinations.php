<?php

declare(strict_types=1);

/**
 * tm_030 — Legare furnizori → export Piese Auto + BaseLinker + Supplier Search.
 * Usage: php admin/tools/test_tm030_supplier_destinations.php
 */

require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
$root = dirname($admin);

require_once $admin . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($admin);
$dotenv->safeLoad();
$config = require $admin . '/config/config.php';
\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

require_once $admin . '/src/Controllers/Produse/import_supplier_lib.php';
require_once $admin . '/src/Services/SupplierSearch/SupplierSearchConfig.php';
require_once $admin . '/src/Services/SupplierSearch/SupplierConnectionRegistry.php';

use Evasystem\Services\SupplierSearch\SupplierConnectionRegistry;
use Evasystem\Services\SupplierSearch\SupplierSearchConfig;

$failures = 0;
$ok = static function (string $message) use (&$failures): void {
    echo "OK  {$message}\n";
};
$fail = static function (string $message) use (&$failures): void {
    echo "FAIL {$message}\n";
    ++$failures;
};

echo "=== test_tm030_supplier_destinations ===\n\n";

$phpBin = admin_php_cli_binary();
$lintTargets = [
    'src/Controllers/Produse/import_supplier_lib.php',
    'src/Controllers/Furnizori/FurnizoriStatsService.php',
    'src/Controllers/Furnizori/FurnizoriService.php',
    'src/Services/SupplierSearch/SupplierConnectionRegistry.php',
    'src/Services/SupplierSearch/SupplierSearchConfig.php',
    'src/Services/SupplierSearch/SupplierSearchService.php',
    'Templates/admin/pages/furnizori/profilefurnizori.php',
];

foreach ($lintTargets as $rel) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $out = [];
    $code = 0;
    exec('"' . $phpBin . '" -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    $line = trim(implode(' ', $out));
    if ($code !== 0 || !str_contains($line, 'No syntax errors')) {
        $fail("php -l {$rel}: {$line}");
    } else {
        $ok("php -l {$rel}");
    }
}

if (!function_exists('import_furnizori_catalog_seeds')) {
    $fail('import_furnizori_catalog_seeds lipsa');
} else {
    $ok('import_furnizori_catalog_seeds exista');
}

if (!function_exists('import_furnizori_catalog_reset_cache')) {
    $fail('import_furnizori_catalog_reset_cache lipsa');
} else {
    $ok('import_furnizori_catalog_reset_cache exista');
}

$catalog = import_furnizori_catalog();
if (!is_array($catalog) || $catalog === []) {
    $fail('import_furnizori_catalog returneaza catalog gol');
} else {
    $ok('import_furnizori_catalog — ' . count($catalog) . ' furnizori');
}

$statsSrc = (string) file_get_contents($admin . '/src/Controllers/Furnizori/FurnizoriStatsService.php');
if (str_contains($statsSrc, 'deleteNotInCodes(import_furnizori_catalog_codes())')) {
    $fail('FurnizoriStatsService inca sterge furnizori din afara seed-ului');
} else {
    $ok('FurnizoriStatsService — fara deleteNotInCodes pe seed');
}

$destinations = import_furnizori_destinations_for_code('AUTOPARTNER');
$destKeys = array_column($destinations, 'key');
foreach (['piese_auto', 'baselinker', 'supplier_search'] as $expectedKey) {
    if (!in_array($expectedKey, $destKeys, true)) {
        $fail("destinations AUTOPARTNER lipsa: {$expectedKey}");
    } else {
        $ok("destinations AUTOPARTNER include {$expectedKey}");
    }
}

$supported = SupplierSearchConfig::supportedSuppliersFromCatalog();
if ($supported === []) {
    $fail('supportedSuppliersFromCatalog gol');
} else {
    $ok('supportedSuppliersFromCatalog — ' . implode(', ', $supported));
}

$registry = SupplierConnectionRegistry::all();
if ($registry === []) {
    $fail('SupplierConnectionRegistry::all gol');
} else {
    $ok('SupplierConnectionRegistry::all — ' . count($registry) . ' furnizori search');
}

$profileHtml = (string) file_get_contents($admin . '/Templates/admin/pages/furnizori/profilefurnizori.php');
foreach ([
    'furnizor-destinations-box',
    'Destinatii alimentate din cartela furnizor',
    'fp-destination-badge',
] as $needle) {
    if (!str_contains($profileHtml, $needle)) {
        $fail("profilefurnizori.php lipsa UI: {$needle}");
    } else {
        $ok("profilefurnizori.php UI: {$needle}");
    }
}

$indexUrl = 'http://besoiupieseauto.ro.test/index.php';
$ch = curl_init($indexUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_NOBODY => true,
]);
curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($httpCode !== 200) {
    $fail("curl {$indexUrl} HTTP {$httpCode}");
} else {
    $ok("curl {$indexUrl} HTTP 200");
}

if ($failures > 0) {
    echo "\nFAILED: {$failures}\n";
    exit(1);
}

echo "\nTM_030 supplier destinations: toate verificarile au trecut.\n";
exit(0);
