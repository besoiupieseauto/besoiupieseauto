<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/system/tecdoc_description.php';

$code = $argv[1] ?? '012800STD';
$brand = $argv[2] ?? 'GLYCO';
$articleId = (int)($argv[3] ?? 0);

$result = tecdoc_build_product_description($code, $brand, '', $articleId);

echo json_encode([
    'code' => $code,
    'brand' => $brand,
    'article_id' => $result['article_id'] ?? 0,
    'source' => $result['source'] ?? '',
    'error' => $result['error'] ?? '',
    'html_length' => strlen((string)($result['html'] ?? '')),
    'html_preview' => mb_substr((string)($result['html'] ?? ''), 0, 500),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
