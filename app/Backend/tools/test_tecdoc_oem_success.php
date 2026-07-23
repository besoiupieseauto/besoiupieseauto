<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';
if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
require_once BESOIU_LEGACY . '/shop-db.php';
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
$probe = [
    'name' => 'ARTICOL GENERIC NECUNOSCUT',
    'brand' => 'BLUEPRINT',
    'oem' => 'ADA102101',
    'code' => 'ADA102101',
];

echo "=== TecDoc OEM → clasificare locală (Filtru ulei) ===\n";
$r = $m->match(array_merge($probe, ['use_ollama' => false, 'use_tecdoc' => true]));
echo 'OK=' . (!empty($r['ok']) ? 'DA' : 'NU') . "\n";
echo 'Rezultat: ' . ($r['category'] ?: '—') . ' → ' . ($r['subcategory'] ?: '—') . "\n";
echo 'Metodă: ' . ($r['method'] ?? '—') . "\n";
if (is_array($r['tecdoc'] ?? null) && !empty($r['tecdoc']['ok'])) {
    echo 'TecDoc: ' . $r['tecdoc']['art_name'] . ' [' . $r['tecdoc']['source'] . "]\n";
}
echo 'Motiv: ' . ($r['reasoning'] ?? '—') . "\n\n";

$staged = ImportCardStaging::cardToProduct([
    'title' => $probe['name'],
    'sku' => $probe['code'],
    'brand' => $probe['brand'],
    'sourceSupplier' => 'AUTONET',
    'stock' => '1',
], 'standard');
echo "Staging: " . ($staged['pCategory'] ?? '—') . ' → ' . ($staged['pSubcategory'] ?? '—') . "\n";
