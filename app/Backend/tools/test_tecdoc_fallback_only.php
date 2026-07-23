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

use Besoiu\Services\CategoryMatchService;

$m = new CategoryMatchService();
$probes = [
    ['name' => 'ARTICOL GENERIC', 'brand' => 'MANN-FILTER', 'oem' => 'W712/75', 'code' => 'W712/75'],
    ['name' => 'XYZ123 OBFUSCAT', 'brand' => 'BOSCH', 'oem' => '0986479B51', 'code' => '0986479B51'],
    ['name' => 'PIESA NECUNOSCUTA', 'brand' => 'BREMBO', 'oem' => 'P23088', 'code' => 'P23088'],
];

echo "=== TEST FORȚAT: doar TecDoc OEM (fără Ollama) ===\n\n";

foreach ($probes as $p) {
    echo "OEM {$p['oem']} / {$p['brand']}\n";
    $r = $m->match(array_merge($p, ['use_ollama' => false, 'use_tecdoc' => true]));
    echo '  OK=' . (!empty($r['ok']) ? 'DA' : 'NU');
    echo ' | ' . ($r['category'] ?: '—') . ' → ' . ($r['subcategory'] ?: '—');
    echo ' | metodă=' . ($r['method'] ?? '—') . "\n";
    if (is_array($r['tecdoc'] ?? null)) {
        $t = $r['tecdoc'];
        if (!empty($t['ok'])) {
            echo '  TecDoc: ' . ($t['art_name'] ?? '—') . ' [' . ($t['source'] ?? '—') . "]\n";
        } elseif (!empty($t['error'])) {
            echo '  TecDoc: ' . $t['error'] . "\n";
        }
    }
    echo "\n";
}
