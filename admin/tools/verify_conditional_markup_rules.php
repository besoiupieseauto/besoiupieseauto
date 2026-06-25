<?php

declare(strict_types=1);

/**
 * tm_083 — verifică reguli adaos condiționale (brand/prag, activare selectivă).
 * Usage: php admin/tools/verify_conditional_markup_rules.php
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

echo "=== verify_conditional_markup_rules (tm_083) ===\n\n";

$phpBin = admin_php_cli_binary();
$lintTargets = [
    'src/Controllers/AdaosComercial/AdaosComercialService.php',
    'src/Controllers/AdaosComercial/AdaosComercial.php',
    'src/Controllers/Produse/crudu.php',
    'Templates/admin/pages/adaoscomercial/adaoscomercial.php',
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

$bmwRule = [
    'id' => 999,
    'name' => 'BMW test',
    'brand_filter' => 'BMW',
    'category_filter' => null,
    'price_min' => 2000,
    'price_max' => null,
    'adjustment_type' => 'fixed',
    'adjustment_value' => 3000,
    'round_to' => null,
    'priority' => 50,
    'is_active' => 1,
];

$productBmwHigh = [
    'pBrand' => 'BMW',
    'pMarca' => 'BMW',
    'pBasePrice' => '2500',
];

$productBmwLow = [
    'pBrand' => 'BMW',
    'pMarca' => 'BMW',
    'pBasePrice' => '2000',
];

$productAudi = [
    'pBrand' => 'AUDI',
    'pBasePrice' => '5000',
];

$ref = new ReflectionClass($service);
$matchesMethod = $ref->getMethod('matchesRule');
$matchesMethod->setAccessible(true);

if (!$matchesMethod->invoke($service, $productBmwHigh, $bmwRule, 2500.0)) {
    echo "FAIL matchesRule: BMW 2500 trebuie sa potriveasca regula\n";
    $failures++;
} else {
    echo "OK  matchesRule BMW 2500\n";
}

if ($matchesMethod->invoke($service, $productBmwLow, $bmwRule, 2000.0)) {
    echo "FAIL matchesRule: BMW exact 2000 nu trebuie sa potriveasca (peste 2000)\n";
    $failures++;
} else {
    echo "OK  matchesRule BMW 2000 exclus\n";
}

if ($matchesMethod->invoke($service, $productAudi, $bmwRule, 5000.0)) {
    echo "FAIL matchesRule: AUDI nu trebuie sa potriveasca regula BMW\n";
    $failures++;
} else {
    echo "OK  matchesRule AUDI exclus\n";
}

$baseOnly = $service->applyAutomaticMarkup([
    'pBrand' => 'BMW',
    'pBasePrice' => '2500',
], null, false);

if (($baseOnly['rule'] ?? null) !== null) {
    echo "FAIL applyAutomaticMarkup(selectiv=false): nu trebuie regula auto\n";
    $failures++;
} else {
    echo "OK  applyAutomaticMarkup fara regula auto la import/save\n";
}

$withRule = $service->applyAutomaticMarkup([
    'pBrand' => 'BMW',
    'pBasePrice' => '2500',
], null, true);

if (($withRule['rule'] ?? null) === null) {
    echo "SKIP applyAutomaticMarkup(selectiv=true): nici o regula activa in BD — OK daca lista e goala\n";
} else {
    echo "OK  applyAutomaticMarkup cu potrivire regula activa\n";
}

$calcMethod = $ref->getMethod('calculateFinalPrice');
$calcMethod->setAccessible(true);
$final = (float) $calcMethod->invoke($service, 2500.0, $bmwRule);
$expectedMarkup = 2500.0 + 3000.0;
$vatMethod = $ref->getMethod('applyCommercialVat');
$vatMethod->setAccessible(true);
$expectedFinal = (float) $vatMethod->invoke($service, $expectedMarkup);

if (abs($final - $expectedFinal) > 0.02) {
    echo "FAIL calculateFinalPrice: asteptat ~{$expectedFinal}, primit {$final}\n";
    $failures++;
} else {
    echo "OK  calculateFinalPrice BMW 2500 +3000 + TVA\n";
}

$serviceSrc = (string) file_get_contents($admin . '/src/Controllers/AdaosComercial/AdaosComercialService.php');
if (!str_contains($serviceSrc, 'matchConditionalRules')) {
    echo "FAIL AdaosComercialService: param matchConditionalRules lipseste\n";
    $failures++;
} else {
    echo "OK  param matchConditionalRules in service\n";
}

$adaosPage = (string) file_get_contents($admin . '/Templates/admin/pages/adaoscomercial/adaoscomercial.php');
if (!str_contains($adaosPage, 'peste, strict') || !str_contains($adaosPage, 'manual')) {
    echo "FAIL adaoscomercial.php: text UI tm_083 incomplet\n";
    $failures++;
} else {
    echo "OK  UI adaoscomercial tm_083\n";
}

if ($failures > 0) {
    echo "\nFAILED: {$failures}\n";
    exit(1);
}

echo "\nCONDITIONAL MARKUP RULES OK (tm_083)\n";
exit(0);
