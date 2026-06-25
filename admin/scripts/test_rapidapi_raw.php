<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';

$code = $argv[1] ?? '012800025mm';
$brand = $argv[2] ?? 'GLYCO';

tecdoc_clear_api_error();
foreach (['IAMNumber', 'ArticleNumber', 'TradeNumber', 'OENumber'] as $type) {
    $url = 'https://auto-parts-catalog.p.rapidapi.com/artlookup/search-for-cross-numbers/lang-id/4/article-type/'
        . $type . '/article-no/' . rawurlencode($code);
    echo "\n=== $type ===\n";
    $raw = tecdoc_cached_response($url, 0);
    echo substr($raw, 0, 800) . "\n";
    $payload = tecdoc_find_image_payload($code, $brand);
    if (($payload['url'] ?? '') !== '') {
        echo "FOUND IMAGE: {$payload['url']}\n";
        break;
    }
}

$err = tecdoc_last_api_error();
if ($err) {
    echo "\nLast error: " . json_encode($err, JSON_UNESCAPED_UNICODE) . "\n";
}
