<?php
declare(strict_types=1);

/**
 * Test live: Import Pro pipeline — local → Ollama → TecDoc OEM → coadă staging.
 * Usage: php app/Backend/tools/test_import_pipeline_live.php [supplier]
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

$importProBootstrap = $root . '/app/Import/MatchingPro/api/bootstrap.php';
if (is_file($importProBootstrap)) {
    require_once $importProBootstrap;
}
require_once $root . '/app/Import/MatchingPro/api/lib/ImportCardStaging.php';

use Besoiu\Services\CategoryMatchService;

$supplier = strtolower(trim($argv[1] ?? 'autonet'));
$feeds = [
    'autonet' => __DIR__ . '/../storage/supplier_feeds/autonet/Lista pret Autonet 27.01.2026.csv',
    'autototal' => __DIR__ . '/../storage/supplier_feeds/autototal/Lista pret Autototal 16.01.2026.csv',
    'elit' => __DIR__ . '/../storage/supplier_feeds/elit/Lista pret Elit 16.01.2026.csv',
];
$feedPath = $feeds[$supplier] ?? $feeds['autonet'];

echo "══════════════════════════════════════════════════════════\n";
echo " TEST LIVE — Pipeline Import Pro (local → Ollama → TecDoc)\n";
echo "══════════════════════════════════════════════════════════\n\n";

$matcher = new CategoryMatchService();
$ollama = $matcher->ollamaStatus();
echo 'Ollama: ' . ($ollama['message_ro'] ?? ($ollama['ready'] ? 'ready' : 'indisponibil')) . "\n";
echo 'OLLAMA_CATEGORY_MATCH: ' . (getenv('OLLAMA_CATEGORY_MATCH') ?: ($_ENV['OLLAMA_CATEGORY_MATCH'] ?? '1')) . "\n\n";

$cases = [];

if (is_file($feedPath)) {
    $fromFeed = pickProductsFromCsv($feedPath, 3);
    foreach ($fromFeed as $i => $p) {
        $cases[] = ['label' => "Feed {$supplier} #" . ($i + 1), 'product' => $p];
    }
} else {
    echo "⚠ Feed lipsă: {$feedPath}\n";
}

// Forțează fallback TecDoc: denumire generică + cod OEM real din feed
$tecdocProbe = findTecdocProbeFromFeed($feedPath);
if ($tecdocProbe !== null) {
    $cases[] = [
        'label' => 'Probe TecDoc OEM (denumire ascunsă)',
        'product' => [
            'name' => 'ARTICOL GENERIC TEST OEM',
            'brand' => $tecdocProbe['brand'],
            'oem' => $tecdocProbe['code'],
            'specs' => '',
            'code' => $tecdocProbe['code'],
        ],
    ];
}

$cases[] = [
    'label' => 'TecDoc OEM confirmat (Filtru ulei BLUEPRINT)',
    'product' => [
        'name' => 'ARTICOL GENERIC NECUNOSCUT',
        'brand' => 'BLUEPRINT',
        'oem' => 'ADA102101',
        'specs' => '',
        'code' => 'ADA102101',
    ],
];

$allOk = true;

foreach ($cases as $case) {
    $label = (string) $case['label'];
    $product = $case['product'];
    echo "──────────────────────────────────────────────────────────\n";
    echo "▶ {$label}\n";
    echo '  Cod OEM: ' . ($product['code'] ?? $product['oem'] ?? '—') . "\n";
    echo '  Brand:   ' . ($product['brand'] ?? '—') . "\n";
    echo '  Nume:    ' . mb_substr((string) ($product['name'] ?? ''), 0, 80) . "\n\n";

    echo "  [1] Match LOCAL only\n";
    $stepLocal = $matcher->match(array_merge($product, [
        'use_ollama' => false,
        'use_tecdoc' => false,
    ]));
    printStep($stepLocal);

    echo "\n  [2] Match LOCAL + Ollama (fără TecDoc)\n";
    $stepOllama = $matcher->match(array_merge($product, [
        'use_ollama' => true,
        'use_tecdoc' => false,
    ]));
    printStep($stepOllama);

    echo "\n  [3] Pipeline COMPLET (local → Ollama → TecDoc OEM)\n";
    $full = $matcher->match(array_merge($product, [
        'use_ollama' => true,
        'use_tecdoc' => true,
    ]));
    printStep($full);
    if (is_array($full['tecdoc'] ?? null)) {
        printTecdoc($full['tecdoc']);
    }

    echo "\n  [4] ImportCardStaging → coadă import\n";
    $card = buildImportProCard($product, $full);
    $staged = ImportCardStaging::cardToProduct($card, 'standard');
    if ($staged === null) {
        echo "  ✗ cardToProduct = null (lipsă titlu/cod)\n";
        $allOk = false;
    } else {
        echo '  ✓ pCategory:    ' . ($staged['pCategory'] ?: '—') . "\n";
        echo '  ✓ pSubcategory: ' . ($staged['pSubcategory'] ?: '—') . "\n";
        echo '  ✓ pOem:         ' . ($staged['pOem'] ?: '—') . "\n";
        echo '  ✓ pSupplier:    ' . ($staged['pSupplier'] ?: '—') . "\n";
        $raw = json_decode((string) ($staged['raw_json'] ?? '{}'), true);
        $cm = is_array($raw) ? ($raw['category_match'] ?? null) : null;
        if (is_array($cm)) {
            echo '  ✓ category_match.method: ' . ($cm['method'] ?? '—') . "\n";
        }
        if (($staged['pCategory'] ?? '') === '' || ($staged['pSubcategory'] ?? '') === '') {
            echo "  ⚠ Categorie incompletă în staging\n";
            $allOk = false;
        }
    }
    echo "\n";
}

echo "══════════════════════════════════════════════════════════\n";
echo $allOk ? "REZULTAT: pipeline OK\n" : "REZULTAT: unele pași incompleți — vezi detalii mai sus\n";
echo "══════════════════════════════════════════════════════════\n";

exit($allOk ? 0 : 2);

/** @return list<array{name:string,brand:string,oem:string,specs:string,code:string}> */
function pickProductsFromCsv(string $path, int $limit = 3): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }

    $header = null;
    $picked = [];
    $line = 0;

    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $line++;
        if ($line === 1) {
            $header = array_map(static fn ($h) => mb_strtolower(trim((string) $h), 'UTF-8'), $row);
            continue;
        }
        if (!is_array($header) || count($row) < 3 || count($picked) >= $limit) {
            continue;
        }

        $map = [];
        foreach ($header as $i => $col) {
            $map[$col] = trim((string) ($row[$i] ?? ''));
        }

        $name = firstNonEmpty($map, ['denumire', 'nume', 'name', 'descriere', 'description', 'articol']);
        $brand = firstNonEmpty($map, ['brand', 'marca', 'producator', 'manufacturer']);
        $code = firstNonEmpty($map, ['cod', 'code', 'sku', 'cod produs', 'cod_produs', 'cod articol']);

        if ($name === '' || $code === '') {
            continue;
        }

        $hay = mb_strtolower($name . ' ' . $brand, 'UTF-8');
        if (!preg_match('/filtr|ulei|fran|disc|placut|amortiz|buj|pompa|senzor/i', $hay)) {
            continue;
        }

        $picked[] = [
            'name' => $name,
            'brand' => $brand,
            'oem' => $code,
            'specs' => '',
            'code' => $code,
        ];
    }

    fclose($handle);

    return $picked;
}

/** @return array{code:string,brand:string}|null */
function findTecdocProbeFromFeed(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }

    $header = null;
    $line = 0;
    $candidate = null;

    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $line++;
        if ($line === 1) {
            $header = array_map(static fn ($h) => mb_strtolower(trim((string) $h), 'UTF-8'), $row);
            continue;
        }
        if (!is_array($header)) {
            continue;
        }

        $map = [];
        foreach ($header as $i => $col) {
            $map[$col] = trim((string) ($row[$i] ?? ''));
        }

        $brand = firstNonEmpty($map, ['brand', 'marca', 'producator']);
        $code = firstNonEmpty($map, ['cod', 'code', 'sku', 'cod produs']);
        if ($brand !== '' && $code !== '' && strlen($code) >= 5) {
            $candidate = ['code' => $code, 'brand' => $brand];
            if ($line > 50) {
                break;
            }
        }
    }

    fclose($handle);

    return $candidate;
}

/** @param array<string, mixed> $product @param array<string, mixed> $match */
function buildImportProCard(array $product, array $match): array
{
    return [
        'title' => (string) ($product['name'] ?? ''),
        'sku' => (string) ($product['code'] ?? $product['oem'] ?? ''),
        'brand' => (string) ($product['brand'] ?? ''),
        'description' => '',
        'specs' => (string) ($product['specs'] ?? ''),
        'category' => (string) ($match['category'] ?? ''),
        'subcategory' => (string) ($match['subcategory'] ?? ''),
        'sourceSupplier' => 'AUTONET',
        'matchStatus' => 'probable',
        'matchMethod' => (string) ($match['method'] ?? ''),
        'matchedTecdocSku' => (string) ($product['code'] ?? ''),
        'stock' => '1',
    ];
}

/** @param array<string, string> $map @param list<string> $keys */
function firstNonEmpty(array $map, array $keys): string
{
    foreach ($keys as $key) {
        foreach ($map as $col => $val) {
            if (str_contains($col, $key) && trim($val) !== '') {
                return trim($val);
            }
        }
    }

    return '';
}

/** @param array<string, mixed> $match */
function printStep(array $match): void
{
    $ok = !empty($match['ok']) ? 'DA' : 'NU';
    echo "      OK={$ok} | " . ($match['category'] ?: '—') . ' → ' . ($match['subcategory'] ?: '—');
    echo ' | metodă=' . ($match['method'] ?? '—');
    echo ' | scor=' . round(((float) ($match['confidence'] ?? 0)) * 100) . "%\n";
    if (!empty($match['reasoning'])) {
        echo '      Motiv: ' . mb_substr((string) $match['reasoning'], 0, 120) . "\n";
    }
}

/** @param array<string, mixed> $tecdoc */
function printTecdoc(array $tecdoc): void
{
    if (!empty($tecdoc['ok'])) {
        echo '      TecDoc: găsit — ' . ($tecdoc['art_name'] ?? '—');
        echo ' [' . ($tecdoc['source'] ?? '—') . "]\n";
        return;
    }
    if (!empty($tecdoc['error'])) {
        echo '      TecDoc: ' . $tecdoc['error'] . "\n";
    }
}
