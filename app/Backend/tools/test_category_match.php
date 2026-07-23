<?php
declare(strict_types=1);

/**
 * Test rapid: produs furnizor → match categorie/subcategorie.
 * Usage: php app/Backend/tools/test_category_match.php [supplier]
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

use Besoiu\Services\CategoryMatchService;
use Besoiu\Services\PieseAutoCategoryCatalog;

$supplier = strtolower(trim($argv[1] ?? 'autonet'));

$feeds = [
    'autonet' => __DIR__ . '/../storage/supplier_feeds/autonet/Lista pret Autonet 27.01.2026.csv',
    'autototal' => __DIR__ . '/../storage/supplier_feeds/autototal/Lista pret Autototal 16.01.2026.csv',
    'elit' => __DIR__ . '/../storage/supplier_feeds/elit/Lista pret Elit 16.01.2026.csv',
    'materom' => __DIR__ . '/../storage/supplier_feeds/materom/Lista pret Materom 16.01.2026.csv',
];

$feedPath = $feeds[$supplier] ?? $feeds['autonet'];
if (!is_file($feedPath)) {
    fwrite(STDERR, "Feed lipsă: {$feedPath}\n");
    exit(1);
}

$product = pickProductFromCsv($feedPath);
if ($product === null) {
    fwrite(STDERR, "Nu s-a găsit produs valid în CSV.\n");
    exit(1);
}

$catalog = new PieseAutoCategoryCatalog();
$dbStats = $catalog->stats();
if ((int) ($dbStats['db_pieseauto_subcategories'] ?? 0) < 10) {
    echo "Import categorii PieseAuto în DB (subcategorii lipsă)…\n";
    $import = $catalog->importToDatabase(true);
    echo sprintf(
        "Import: +%d cat, +%d sub, %d actualizate, %d sărite\n",
        $import['imported_categories'],
        $import['imported_subcategories'],
        $import['updated'],
        $import['skipped']
    );
}

$matcher = new CategoryMatchService();

echo "\n=== PRODUS FURNIZOR ({$supplier}) ===\n";
echo 'Cod: ' . $product['code'] . "\n";
echo 'Brand: ' . $product['brand'] . "\n";
echo 'Denumire: ' . $product['name'] . "\n\n";

echo "--- Match LOCAL (fără Ollama) ---\n";
$local = $matcher->match(array_merge($product, ['use_ollama' => false]));
printMatch($local);

echo "\n--- Match CU Ollama (dacă local e incomplet) ---\n";
$full = $matcher->match(array_merge($product, ['use_ollama' => true]));
printMatch($full);

$ollamaStatus = $matcher->ollamaStatus();
echo "\nOllama: " . ($ollamaStatus['message_ro'] ?? '—') . "\n";

exit($full['ok'] ? 0 : 1);

/** @return array{name:string,brand:string,oem:string,specs:string,code:string}|null */
function pickProductFromCsv(string $path): ?array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }

    $header = null;
    $picked = null;
    $line = 0;

    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $line++;
        if ($line === 1) {
            $header = array_map(static fn ($h) => mb_strtolower(trim((string) $h), 'UTF-8'), $row);
            continue;
        }
        if (!is_array($header) || count($row) < 3) {
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

        // Preferă piese uzuale pentru test (filtru, frână, ulei)
        $hay = mb_strtolower($name . ' ' . $brand, 'UTF-8');
        if (preg_match('/filtr|ulei|fran|disc|placut|amortiz|buj/i', $hay)) {
            $picked = compactRow($name, $brand, $code);
            break;
        }

        if ($picked === null && $line <= 500) {
            $picked = compactRow($name, $brand, $code);
        }
    }

    fclose($handle);

    return $picked;
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

/** @return array{name:string,brand:string,oem:string,specs:string,code:string} */
function compactRow(string $name, string $brand, string $code): array
{
    return [
        'name' => $name,
        'brand' => $brand,
        'oem' => $code,
        'specs' => '',
        'code' => $code,
    ];
}

/** @param array<string, mixed> $match */
function printMatch(array $match): void
{
    echo 'OK: ' . (!empty($match['ok']) ? 'da' : 'nu') . "\n";
    echo 'Categorie: ' . ($match['category'] ?: '—') . "\n";
    echo 'Subcategorie: ' . ($match['subcategory'] ?: '—') . "\n";
    echo 'Metodă: ' . ($match['method'] ?? '—') . "\n";
    echo 'Confidence: ' . round(((float) ($match['confidence'] ?? 0)) * 100) . "%\n";
    if (!empty($match['reasoning'])) {
        echo 'Motiv: ' . $match['reasoning'] . "\n";
    }
    if (is_array($match['ollama'] ?? null) && !empty($match['ollama']['error'])) {
        echo 'Ollama eroare: ' . $match['ollama']['error'] . "\n";
    }
}
