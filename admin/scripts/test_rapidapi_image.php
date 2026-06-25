<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

define('IMPORT_PRODUCE_SKIP_HTTP', true);
require_once dirname(__DIR__) . '/src/Controllers/Produse/importproduse.php';

$code = trim((string)($argv[1] ?? '012800025mm'));
$brand = trim((string)($argv[2] ?? 'GLYCO'));

echo "Code: $code\n";
echo "Brand: $brand\n";
echo 'User RapidAPI key: ' . (function_exists('tecdoc_rapidapi_is_user_key') && tecdoc_rapidapi_is_user_key() ? 'yes' : 'no (fallback key)') . "\n\n";

if (function_exists('tecdoc_clear_api_error')) {
    tecdoc_clear_api_error();
}

$product = [
    'pCode' => $code,
    'pBrand' => $brand,
    'pImages' => '[]',
    'raw_json' => json_encode(['rows' => [['art code 1' => $code, 'art brand' => $brand, 'ttc art id' => '']]], JSON_UNESCAPED_UNICODE),
];

$found = import_find_image_for_product($product, [], []);
echo 'Source: ' . ($found['source'] ?? '—') . "\n";
echo 'URL: ' . ($found['url'] ?? '—') . "\n";
if (!empty($found['api_error'])) {
    echo 'Error: ' . $found['api_error'] . "\n";
}

$err = function_exists('tecdoc_last_api_error') ? tecdoc_last_api_error() : null;
if (is_array($err) && !empty($err['message'])) {
    echo 'API log: ' . $err['message'] . "\n";
}
