<?php
declare(strict_types=1);

/**
 * Simulează panoul admin „Test clasificare produs” (/admin/categorii).
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

use Besoiu\Controllers\Categorii\Categorii;
use Besoiu\Services\CategoriiService;

$controller = new Categorii();
$service = new CategoriiService();

echo "══════════════════════════════════════════════════════════\n";
echo " TEST UI — /admin/categorii → Test clasificare produs\n";
echo "══════════════════════════════════════════════════════════\n\n";

$ollama = $service->categoryMatchOllamaStatus();
echo 'Badge Ollama: ' . ($ollama['ready'] ?? false ? 'Ollama OK · ' . ($ollama['text_model'] ?? 'model') : ($ollama['message_ro'] ?? 'indisponibil')) . "\n\n";

$cases = [
    [
        'label' => 'Produs clar (match local)',
        'payload' => [
            'name' => 'Set placute frana, fata',
            'brand' => 'BREMBO',
            'oem' => 'P23088',
            'specs' => '',
            'use_ollama' => true,
        ],
    ],
    [
        'label' => 'Denumire generică + OEM (Ollama / TecDoc)',
        'payload' => [
            'name' => 'ARTICOL GENERIC TEST OEM',
            'brand' => 'RENAULT',
            'oem' => 'RE7700855603',
            'specs' => '',
            'use_ollama' => true,
        ],
    ],
    [
        'label' => 'TecDoc OEM forțat (Ollama OFF)',
        'payload' => [
            'name' => 'ARTICOL GENERIC NECUNOSCUT',
            'brand' => 'BLUEPRINT',
            'oem' => 'ADA102101',
            'specs' => '',
            'use_ollama' => false,
        ],
    ],
];

foreach ($cases as $case) {
    echo "──────────────────────────────────────────────────────────\n";
    echo '▶ ' . $case['label'] . "\n";
    $p = $case['payload'];
    echo '  Denumire: ' . $p['name'] . "\n";
    echo '  Brand:    ' . $p['brand'] . "\n";
    echo '  OEM:      ' . $p['oem'] . "\n";
    echo '  Ollama:   ' . (!empty($p['use_ollama']) ? 'DA' : 'NU') . "\n\n";

    $json = $controller->matchCategory($p);
    if (empty($json['success']) || !is_array($json['match'] ?? null)) {
        echo '  ✗ Eroare: ' . ($json['message'] ?? 'răspuns invalid') . "\n\n";
        continue;
    }

    $m = $json['match'];
    echo '  Rezultat final: ' . ($m['category'] ?: '—') . ' → ' . ($m['subcategory'] ?: '—') . "\n";
    echo '  Metodă: ' . ($m['method'] ?? '—') . ' | scor ' . round(((float) ($m['confidence'] ?? 0)) * 100) . "%\n";

    $local = $m['local'] ?? [];
    echo '  Local:  ' . ($local['category'] ?? '—') . ' / ' . ($local['subcategory'] ?? '—') . "\n";

    if (is_array($m['ollama'] ?? null)) {
        $o = $m['ollama'];
        if (!empty($o['ok'])) {
            echo '  Ollama: ' . ($o['category'] ?? '—') . ' / ' . ($o['subcategory'] ?? '—') . "\n";
        } elseif (!empty($o['error'])) {
            echo '  Ollama: ' . $o['error'] . "\n";
        }
    }

    if (is_array($m['tecdoc'] ?? null)) {
        $t = $m['tecdoc'];
        if (!empty($t['ok'])) {
            echo '  TecDoc: ' . ($t['art_name'] ?? '—') . ' [' . ($t['source'] ?? '—') . "]\n";
        } elseif (!empty($t['error'])) {
            echo '  TecDoc: ' . $t['error'] . "\n";
        }
    }

    if (!empty($m['reasoning'])) {
        echo '  Motiv: ' . mb_substr((string) $m['reasoning'], 0, 100) . "\n";
    }
    echo "\n";
}

echo "══════════════════════════════════════════════════════════\n";
echo "Deschide /admin/categorii și apasă „Rulează test match” cu aceleași valori.\n";
echo "══════════════════════════════════════════════════════════\n";
