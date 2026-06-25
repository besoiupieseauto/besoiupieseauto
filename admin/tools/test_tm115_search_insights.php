<?php
declare(strict_types=1);

/**
 * tm_115 — Îmbogățire BD auto din istoric căutări (insights + boost filtre).
 */

require_once dirname(__DIR__, 2) . '/system/search_logs.php';
require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

use Evasystem\Controllers\Produse\ProductFacetsService;

$errors = [];
$pdo = tecdoc_db();

echo "=== tm_115 search_logs_storefront_insights ===\n";
$insights = search_logs_storefront_insights($pdo, 8, 90);
$requiredInsightKeys = ['available', 'vehicles', 'categories', 'marci', 'queries', 'generated_at'];
foreach ($requiredInsightKeys as $key) {
    if (!array_key_exists($key, $insights)) {
        $errors[] = 'insights missing key: ' . $key;
    }
}

if (!is_array($insights['vehicles']) || !is_array($insights['categories']) || !is_array($insights['queries'])) {
    $errors[] = 'insights arrays invalid';
}

echo json_encode([
    'available' => $insights['available'] ?? false,
    'vehicles' => count($insights['vehicles'] ?? []),
    'categories' => count($insights['categories'] ?? []),
    'marci' => count($insights['marci'] ?? []),
    'queries' => count($insights['queries'] ?? []),
], JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== tm_115 search_logs_boost_facet_items ===\n";
$boosted = search_logs_boost_facet_items(
    [
        ['label' => 'ZZZ-TEST-LOW', 'count' => 1],
        ['label' => 'Filtre', 'count' => 5],
    ],
    [
        ['label' => 'Filtre', 'search_count' => 99],
        ['label' => 'From History Only', 'search_count' => 12],
    ]
);
if (($boosted[0]['label'] ?? '') !== 'Filtre') {
    $errors[] = 'boost sort failed — Filtre should be first';
}
$hasHistoryOnly = false;
foreach ($boosted as $row) {
    if (($row['label'] ?? '') === 'From History Only') {
        $hasHistoryOnly = true;
        break;
    }
}
if (!$hasHistoryOnly) {
    $errors[] = 'boost did not append history-only facet';
}
echo json_encode([
    'first_label' => $boosted[0]['label'] ?? null,
    'total' => count($boosted),
    'history_only_added' => $hasHistoryOnly,
], JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== tm_115 ProductFacetsService boost ===\n";
$facets = (new ProductFacetsService())->getAll();
if (!isset($facets['categories'], $facets['marci'], $facets['modele'])) {
    $errors[] = 'getAll missing facet keys';
}
echo json_encode([
    'categories' => count($facets['categories'] ?? []),
    'marci' => count($facets['marci'] ?? []),
    'modele' => count($facets['modele'] ?? []),
], JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== tm_115 HTTP insights endpoint ===\n";
$insightsUrl = 'http://besoiupieseauto.ro.test/api_categorii.php?action=insights&limit=5';
$raw = @file_get_contents($insightsUrl);
if (!is_string($raw) || $raw === '') {
    $errors[] = 'insights endpoint empty response';
} else {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || ($decoded['success'] ?? false) !== true) {
        $errors[] = 'insights endpoint invalid JSON or success=false';
    } elseif (!isset($decoded['insights']['available'])) {
        $errors[] = 'insights payload missing available key';
    }
    echo substr($raw, 0, 280) . "\n\n";
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "TM115_SEARCH_INSIGHTS_OK\n";
