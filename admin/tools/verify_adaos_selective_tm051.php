<?php

declare(strict_types=1);

/**
 * tm_051 — reguli adaos activabile selectiv (nu auto-pe toate).
 * Usage: php admin/tools/verify_adaos_selective_tm051.php
 */

require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
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
$failures = 0;

echo "=== verify_adaos_selective_tm051 ===\n\n";

$phpBin = admin_php_cli_binary();
$lintTargets = [
    'src/Controllers/AdaosComercial/AdaosComercialService.php',
    'src/Controllers/Produse/import_job_lib.php',
    'src/Controllers/Produse/importproduse.php',
    'Templates/admin/pages/import/import.php',
    'Templates/admin/pages/adaoscomercial/adaoscomercial.php',
    'Templates/admin/pages/produse/produse.php',
    'src/Controllers/Produse/crudu.php',
];

foreach ($lintTargets as $rel) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $out = [];
    $code = 0;
    exec('"' . $phpBin . '" -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    $line = trim(implode(' ', $out));
    if ($code !== 0 || !str_contains($line, 'No syntax errors')) {
        echo "FAIL php -l {$rel}: {$line}\n";
        $failures++;
    } else {
        echo "OK  php -l {$rel}\n";
    }
}

require_once $admin . '/src/Controllers/AdaosComercial/AdaosComercialService.php';

use Evasystem\Controllers\AdaosComercial\AdaosComercialService;

$service = new AdaosComercialService();
$serviceSrc = (string) file_get_contents($admin . '/src/Controllers/AdaosComercial/AdaosComercialService.php');

$staticChecks = [
    [$serviceSrc, 'matchConditionalRules', 'AdaosComercialService: matchConditionalRules'],
    [$serviceSrc, 'explicitRuleId', 'AdaosComercialService: explicitRuleId'],
    [(string) file_get_contents($admin . '/Templates/admin/pages/import/import.php'), 'id="importMarkupRuleId"', 'import.php: selector regulă import'],
    [(string) file_get_contents($admin . '/Templates/admin/pages/import/import.php'), 'markup_rule_id', 'import.php: trimite markup_rule_id'],
    [(string) file_get_contents($admin . '/src/Controllers/Produse/import_job_lib.php'), 'markup_rule_id', 'import_job_lib: markup_rule_id in job meta'],
    [(string) file_get_contents($admin . '/Templates/admin/pages/produse/produse.php'), 'apply_markup_rule', 'produse.php: apply_markup_rule'],
    [(string) file_get_contents($admin . '/src/Controllers/Produse/crudu.php'), 'applyRuleToProductIds', 'crudu.php: applyRuleToProductIds'],
];

foreach ($staticChecks as [$src, $needle, $label]) {
    if (!str_contains($src, $needle)) {
        echo "FAIL {$label}\n";
        $failures++;
    } else {
        echo "OK  {$label}\n";
    }
}

$adaosPage = (string) file_get_contents($admin . '/Templates/admin/pages/adaoscomercial/adaoscomercial.php');
if (str_contains($adaosPage, 'id="is_active" type="checkbox" class="h-4 w-4" checked')) {
    echo "FAIL adaoscomercial: is_active bifat implicit\n";
    $failures++;
} else {
    echo "OK  adaoscomercial: is_active debifat implicit\n";
}

$noAuto = $service->applyAutomaticMarkup([
    'pBrand' => 'BMW',
    'pBasePrice' => '2500',
], null, false, null);

if (($noAuto['rule'] ?? null) !== null) {
    echo "FAIL applyAutomaticMarkup: regula auto la save/import\n";
    $failures++;
} else {
    echo "OK  applyAutomaticMarkup fara regula auto\n";
}

$explicit = $service->applyAutomaticMarkup([
    'pBrand' => 'BMW',
    'pBasePrice' => '100',
], null, false, 0);

if (($explicit['rule'] ?? null) !== null) {
    echo "FAIL applyAutomaticMarkup(explicit 0): nu trebuie regula\n";
    $failures++;
} else {
    echo "OK  applyAutomaticMarkup explicitRuleId=0 ignorat\n";
}

if ($failures > 0) {
    echo "\nFAILED: {$failures}\n";
    exit(1);
}

echo "\nADAOS SELECTIV tm_051 OK\n";
exit(0);
