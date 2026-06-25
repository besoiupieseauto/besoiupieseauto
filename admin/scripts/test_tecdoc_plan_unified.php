<?php

declare(strict_types=1);

/**
 * Verifică Plan 2 TecDoc — nu mai returnează „Mod necunoscut”.
 */

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/admin/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($root . '/admin');
$dotenv->safeLoad();

require_once $root . '/lib/Scraper/ImageSearchService.php';

$product = [
    'pName' => 'Cap Bara TRW 5034724',
    'pCode' => '5034724',
    'pBrand' => 'TRW',
    'pCategory' => 'Suspensie',
    'pSubcategory' => 'Cap bara',
    '__skip_iam_lookup' => true,
];

$plan = [
    'tier' => 2,
    'source_id' => 'tecdoc_api',
    'label' => 'Plan 5 — TecDoc RapidAPI',
    'enabled' => true,
];

$step = ImageSearchService::resolvePlan($plan, $product, false, [
    'test_mode' => true,
    'pipeline_test_query' => 'Cap Bara TRW 5034724',
]);

$msg = (string) ($step['tried']['message'] ?? '');
$url = trim((string) ($step['hit']['url'] ?? ''));

echo "Status: " . ($step['tried']['status'] ?? '?') . PHP_EOL;
echo "Message: " . $msg . PHP_EOL;
echo "URL: " . ($url !== '' ? $url : '(gol)') . PHP_EOL;

if (str_contains($msg, 'Mod necunoscut')) {
    fwrite(STDERR, "FAIL: bug HTTP import încă activ\n");
    exit(1);
}

echo "OK: TecDoc plan nu declanșează handler HTTP import\n";
exit(0);
