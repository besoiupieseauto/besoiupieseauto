<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';
if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
require_once BESOIU_LEGACY . '/shop-db.php';
require_once BESOIU_LEGACY . '/product-code-normalize.php';
$c = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($c['db_host'] ?? '127.0.0.1'),
    (string) ($c['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($c['db_user'] ?? 'root'),
    (string) ($c['db_pass'] ?? '')
);
require_once $root . '/app/Import/MatchingPro/api/lib/ImportCardStaging.php';

use Besoiu\Services\CategoryMatchService;

$m = new CategoryMatchService();

// Cod real din besoiu_tecdoc_base — denumire ascunsă forțează TecDoc
$probe = [
    'name' => 'ARTICOL GENERIC NECUNOSCUT',
    'brand' => 'ABAKUS',
    'oem' => '00202870',
    'code' => '00202870',
    'specs' => '',
];

echo "=== TEST LIVE TecDoc OEM (cod confirmat în DB) ===\n";
echo "OEM: {$probe['oem']} | Brand: {$probe['brand']}\n";
echo "Denumire ascunsă: {$probe['name']}\n\n";

echo "[1] Local only\n";
$r1 = $m->match(array_merge($probe, ['use_ollama' => false, 'use_tecdoc' => false]));
echo '  ' . ($r1['category'] ?: '—') . ' → ' . ($r1['subcategory'] ?: '—') . ' | ' . ($r1['method'] ?? '—') . "\n\n";

echo "[2] TecDoc only (fără Ollama)\n";
$r2 = $m->match(array_merge($probe, ['use_ollama' => false, 'use_tecdoc' => true]));
echo '  OK=' . (!empty($r2['ok']) ? 'DA' : 'NU') . ' | ' . ($r2['category'] ?: '—') . ' → ' . ($r2['subcategory'] ?: '—');
echo ' | metodă=' . ($r2['method'] ?? '—') . "\n";
if (is_array($r2['tecdoc'] ?? null)) {
    $t = $r2['tecdoc'];
    if (!empty($t['ok'])) {
        echo '  TecDoc art_name: ' . ($t['art_name'] ?? '—') . ' [' . ($t['source'] ?? '—') . "]\n";
        echo '  Reasoning: ' . ($r2['reasoning'] ?? '—') . "\n";
    } else {
        echo '  TecDoc error: ' . ($t['error'] ?? '—') . "\n";
    }
}

echo "\n[3] Pipeline complet + staging\n";
$r3 = $m->match(array_merge($probe, ['use_ollama' => true, 'use_tecdoc' => true]));
$card = [
    'title' => $probe['name'],
    'sku' => $probe['code'],
    'brand' => $probe['brand'],
    'sourceSupplier' => 'AUTONET',
    'matchStatus' => 'probable',
    'stock' => '1',
];
$staged = ImportCardStaging::cardToProduct($card, 'standard');
if ($staged) {
    echo '  Staging: ' . ($staged['pCategory'] ?: '—') . ' → ' . ($staged['pSubcategory'] ?: '—') . "\n";
    $raw = json_decode((string) $staged['raw_json'], true);
    echo '  category_match.method: ' . ($raw['category_match']['method'] ?? '—') . "\n";
}

echo "\nNorm test W712/75 = " . besoiu_normalize_product_code('W712/75') . "\n";
echo "Norm test P23088 = " . besoiu_normalize_product_code('P23088') . "\n";
