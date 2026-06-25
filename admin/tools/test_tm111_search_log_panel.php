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

$overview = (new DashboardService())->overview(true);
$search = $overview['search_logs'] ?? [];

$requiredKeys = ['available', 'total', 'found', 'not_found', 'top_oem', 'daily_trend'];
$missing = array_values(array_filter($requiredKeys, static fn (string $key): bool => !array_key_exists($key, $search)));

if ($missing !== []) {
    fwrite(STDERR, 'TM111_FAIL missing keys: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

if (!is_array($search['daily_trend'])) {
    fwrite(STDERR, 'TM111_FAIL daily_trend must be array' . PHP_EOL);
    exit(1);
}

if (!is_array($search['top_oem'])) {
    fwrite(STDERR, 'TM111_FAIL top_oem must be array' . PHP_EOL);
    exit(1);
}

$trendSample = $search['daily_trend'][0] ?? null;
if ($trendSample !== null) {
    foreach (['day', 'total', 'found', 'not_found'] as $field) {
        if (!array_key_exists($field, $trendSample)) {
            fwrite(STDERR, 'TM111_FAIL trend row missing ' . $field . PHP_EOL);
            exit(1);
        }
    }
}

echo 'TM111_SEARCH_LOG_PANEL_OK' . PHP_EOL;
echo json_encode([
    'total' => $search['total'],
    'found' => $search['found'],
    'not_found' => $search['not_found'],
    'trend_days' => count($search['daily_trend']),
    'top_oem_count' => count($search['top_oem']),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
