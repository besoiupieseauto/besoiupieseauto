<?php
declare(strict_types=1);

/**
 * Regresie: mapare titluri după template — fără FRANA/COD și fără Specificații în motorizare.
 */
$root = dirname(__DIR__, 3);
$adminRoot = $root . '/admin';
if (is_file($adminRoot . '/bootstrap.php')) {
    require_once $adminRoot . '/bootstrap.php';
}
if (is_file($adminRoot . '/vendor/autoload.php')) {
    require_once $adminRoot . '/vendor/autoload.php';
} elseif (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}
require_once $root . '/app/Backend/src/Controllers/Produse/import_base_lib.php';

use Besoiu\Services\ProductCardFormationService;

$svc = new ProductCardFormationService();

$product = [
    'pName' => 'Furtun Frana',
    'pBrand' => 'FEBIBILSTEIN',
    'pCode' => '107308756',
    'pMarca' => 'LEXUS, SUZUKI, TOYOTA',
    'pModel' => 'C-HR, COROLLA, PRIUS',
    'pMotorizare' => "180228|Specificatii tehnice:Atentie la informatii de service\n1.8 Hybrid",
    'pCategory' => 'Furtun Frana',
    'pCompatibilitati' => "TOYOTA C-HR 1.8 Hybrid (2016 - Prezent)\nLEXUS UX 2.0 (2018 - Prezent)",
];

$raw = [
    '__tecdoc_art_name' => 'Furtun Frana',
    'import_pro_card' => true,
    'product_summary' => [
        'tecdoc_art_name' => 'Furtun Frana',
        'description' => '<p><b>Specificații tehnice (TecDoc):</b></p><ul>'
            . '<li>FRANA: disc</li><li>COD: X</li><li>FILET: M10</li><li>AXA: FATA</li></ul>'
            . '<p><b>Compatibil cu:</b></p><ul>'
            . '<li>TOYOTA C-HR 1.8 Hybrid (2016 - Prezent)</li>'
            . '<li>LEXUS UX 2.0 (2018 - Prezent)</li></ul>',
    ],
];

$applied = $svc->applyToImportProduct($product, $raw);
$pf = is_array($applied['product_formation'] ?? null) ? $applied['product_formation'] : [];
$web = (string) ($applied['product']['pName'] ?? '');
$mp = (string) ($applied['product']['pNameMarketplace'] ?? '');
$motor = (string) ($applied['product']['pMotorizare'] ?? '');

echo 'applied_flag: ' . (!empty($pf['applied']) ? 'yes' : 'no') . "\n";
echo "website: {$web}\n";
echo "marketplace: {$mp}\n";
echo "motorizare: {$motor}\n";
echo 'segments_web: ' . json_encode($svc->segmentValues('website', [
    'piece_name' => 'Furtun Frana',
    'brand' => 'FEBIBILSTEIN',
    'code' => '107308756',
    'pMarca' => 'LEXUS, SUZUKI, TOYOTA',
    'pModel' => 'C-HR, COROLLA, PRIUS',
    'pMotorizare' => '1.8 Hybrid',
]), JSON_UNESCAPED_UNICODE) . "\n";

$fail = [];
if (preg_match('/\bFILET\b|\bAXA\b|Specificat|FRANA\/COD/i', $web . ' ' . $mp)) {
    $fail[] = 'titluri conțin token-uri din specificații';
}
if (str_contains($motor, '|') || preg_match('/Specificat|Atentie/i', $motor)) {
    $fail[] = 'motorizare contaminată';
}
if (!str_contains(mb_strtoupper($mp, 'UTF-8'), 'TOYOTA') && !str_contains(mb_strtoupper($mp, 'UTF-8'), 'LEXUS')) {
    $fail[] = 'marketplace fără mărci auto reale';
}
if (!str_contains($web, 'pentru')) {
    $fail[] = 'website fără segment vehicul (pentru…)';
}
if (empty($pf['applied'])) {
    $fail[] = 'formation applied=false (exception internă?)';
}

if ($fail === []) {
    echo "overall: OK\n";
    exit(0);
}

echo "overall: FAIL\n- " . implode("\n- ", $fail) . "\n";
exit(1);
