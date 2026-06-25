<?php
declare(strict_types=1);

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

use Evasystem\Controllers\Dashboard\DashboardService;
use Evasystem\Controllers\SearchLogs\SearchLogsService;

$overview = (new DashboardService())->overview(true);
$search = $overview['search_logs'] ?? [];

$requiredKeys = ['available', 'not_found', 'missing_codes_count', 'top_missing'];
$missing = array_values(array_filter($requiredKeys, static fn (string $key): bool => !array_key_exists($key, $search)));

if ($missing !== []) {
    fwrite(STDERR, 'TM113_FAIL missing dashboard keys: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$topMissing = (new SearchLogsService())->topMissing(50);
if (!isset($topMissing['items'], $topMissing['codes_count'], $topMissing['stats'])) {
    fwrite(STDERR, 'TM113_FAIL topMissing payload incomplete' . PHP_EOL);
    exit(1);
}

if (!is_array($topMissing['items'])) {
    fwrite(STDERR, 'TM113_FAIL topMissing items must be array' . PHP_EOL);
    exit(1);
}

$sample = $topMissing['items'][0] ?? null;
if ($sample !== null) {
    foreach (['query_type', 'query_value', 'attempts', 'last_seen'] as $field) {
        if (!array_key_exists($field, $sample)) {
            fwrite(STDERR, 'TM113_FAIL topMissing row missing ' . $field . PHP_EOL);
            exit(1);
        }
    }
}

$homePath = dirname(__DIR__) . '/Templates/admin/pages/homepages.php';
$homeHtml = is_file($homePath) ? (string) file_get_contents($homePath) : '';
$requiredDom = [
    'id="kpi-not-found-widget"',
    'id="search-analytics-not-found-widget"',
    'id="missing-searches-modal"',
    'type_product: \'top_missing\'',
];
foreach ($requiredDom as $needle) {
    if (!str_contains($homeHtml, $needle)) {
        fwrite(STDERR, 'TM113_FAIL homepages missing ' . $needle . PHP_EOL);
        exit(1);
    }
}

echo 'TM113_NOT_FOUND_WIDGET_OK' . PHP_EOL;
echo json_encode([
    'missing_codes_count' => $search['missing_codes_count'],
    'not_found' => $search['not_found'],
    'top_missing_sample' => count($topMissing['items']),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
