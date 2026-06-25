<?php

declare(strict_types=1);

/**
 * Agent audit imagini.
 *
 * Pregătire lot Cursor (implicit):
 *   php admin/tools/audit_product_images.php --prepare --limit=10
 *
 * OpenAI Vision (doar dacă IMAGE_AUDIT_ENGINE=openai):
 *   php admin/tools/audit_product_images.php --analyze --limit=10
 */

$root = dirname(__DIR__, 2);

require_once $root . '/admin/vendor/autoload.php';
if (is_file($root . '/admin/.env')) {
    Dotenv\Dotenv::createImmutable($root . '/admin')->safeLoad();
}

require_once $root . '/admin/config/Database.php';
$config = require $root . '/admin/config/config.php';
Config\Database::getInstance(
    (string) $config['db_host'],
    (string) $config['db_name'],
    (string) $config['db_user'],
    (string) $config['db_pass']
);

require_once $root . '/admin/src/Services/ProductImageAuditService.php';

use Config\Database;
use Evasystem\Services\ProductImageAuditService;

$opts = parseCliArgs($argv);
$service = new ProductImageAuditService($root);
$pdo = Database::getDB();

$loadOpts = [
    'limit' => (int) ($opts['limit'] ?? 20),
    'randomn_id' => (string) ($opts['id'] ?? ''),
    'category' => (string) ($opts['category'] ?? ''),
    'vitrina_only' => !empty($opts['vitrina']),
    'include_without_image' => !empty($opts['all']),
];

$products = $service->loadProductsForAudit($pdo, $loadOpts);

if ($products === []) {
    fwrite(STDERR, "Niciun produs găsit pentru audit.\n");
    exit(1);
}

$meta = [
    'mode' => !empty($opts['analyze']) ? 'analyze' : 'prepare',
    'limit' => $loadOpts['limit'],
    'count' => count($products),
    'filters' => array_filter([
        'id' => $loadOpts['randomn_id'] ?: null,
        'category' => $loadOpts['category'] ?: null,
        'vitrina' => $loadOpts['vitrina_only'] ?: null,
    ]),
];

echo '=== Agent audit imagini (besoiupieseauto.ro) ===' . PHP_EOL;
echo 'Produse în lot: ' . count($products) . PHP_EOL;

if (!empty($opts['prepare']) || empty($opts['analyze'])) {
    $manifest = $service->buildManifest($products, $meta);
    $path = $service->saveManifest($manifest);
    echo PHP_EOL . 'Lot pregătit pentru Composer:' . PHP_EOL;
    echo $path . PHP_EOL;
    echo PHP_EOL . 'Următorul pas în Cursor (Composer 2.5):' . PHP_EOL;
    echo '  @product-image-audit Analizează lotul: ' . basename($path) . PHP_EOL;
    exit(0);
}

$apiKey = trim((string) ($_ENV['OPENAI_KEY'] ?? getenv('OPENAI_KEY') ?: ''));
$engine = \Evasystem\Services\ProductImageAuditService::auditEngine();
if ($engine !== 'openai') {
    fwrite(STDERR, "Mod cursor activ (implicit). Pentru --analyze cu OpenAI: IMAGE_AUDIT_ENGINE=openai în admin/.env\n");
    fwrite(STDERR, "În admin folosește butonul «Audit Cursor» + @product-image-audit în Composer.\n");
    exit(1);
}
if ($apiKey === '') {
    fwrite(STDERR, "OPENAI_KEY lipsește din admin/.env\n");
    exit(1);
}

$model = trim((string) ($_ENV['IMAGE_AUDIT_MODEL'] ?? getenv('IMAGE_AUDIT_MODEL') ?: 'gpt-4o-mini'));
echo 'Model vision: ' . $model . PHP_EOL . PHP_EOL;

$results = [];
foreach ($products as $i => $product) {
    $n = $i + 1;
    $title = mb_substr((string) ($product['title'] ?? ''), 0, 50, 'UTF-8');
    echo '[' . $n . '/' . count($products) . '] ' . $title . '…' . PHP_EOL;

    $result = $service->analyzeProductWithVision($product, $apiKey, $model);
    $results[] = $result;

    echo '  → ' . ($result['verdict'] ?? '?')
        . ' | scor ' . (int) ($result['match_score'] ?? 0)
        . ' | ' . ($result['summary_ro'] ?? '') . PHP_EOL;
}

$reportPath = $service->writeMarkdownReport($results, $meta);
$jsonPath = $service->saveManifest([
    'schema' => 'product_image_audit_results_v1',
    'created_at' => date('c'),
    'meta' => $meta,
    'results' => $results,
], 'results_' . date('Ymd_His') . '.json');

echo PHP_EOL . 'Raport Markdown: ' . $reportPath . PHP_EOL;
echo 'JSON rezultate: ' . $jsonPath . PHP_EOL;

/** @return array<string, mixed> */
function parseCliArgs(array $argv): array
{
    $out = ['limit' => 20];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--prepare') {
            $out['prepare'] = true;
        } elseif ($arg === '--analyze') {
            $out['analyze'] = true;
        } elseif ($arg === '--vitrina') {
            $out['vitrina'] = true;
        } elseif ($arg === '--all') {
            $out['all'] = true;
        } elseif (str_starts_with($arg, '--limit=')) {
            $out['limit'] = (int) substr($arg, 8);
        } elseif (str_starts_with($arg, '--id=')) {
            $out['id'] = substr($arg, 5);
        } elseif (str_starts_with($arg, '--category=')) {
            $out['category'] = substr($arg, 11);
        }
    }

    return $out;
}
