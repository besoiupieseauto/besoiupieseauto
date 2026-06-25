<?php

declare(strict_types=1);

/**
 * tm_108 — URL permanent feed BaseLinker (XML preferat, JSON alternativ).
 * Exemplu: /api/baselinker-feed.php?token=XXX&format=xml&part=1
 */

require_once __DIR__ . '/../system/shop-db.php';
require_once __DIR__ . '/../system/baselinker-feed.php';

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$token = trim((string) ($_GET['token'] ?? ''));
$format = strtolower(trim((string) ($_GET['format'] ?? 'xml')));
$partRaw = trim((string) ($_GET['part'] ?? ''));

if ($token === '') {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Token feed lipsă.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = shop_db_bootstrap();
} catch (Throwable $exception) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Serviciu indisponibil.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!baselinker_feed_validate_token($token, $pdo)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Token feed invalid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$meta = baselinker_feed_load_meta();
if ((int) ($meta['product_count'] ?? 0) <= 0 && !is_readable(baselinker_feed_storage_dir() . '/catalog-001.xml')) {
    baselinker_feed_regenerate($pdo);
    $meta = baselinker_feed_load_meta();
}

$storageDir = baselinker_feed_storage_dir();
header('Cache-Control: public, max-age=300, must-revalidate');
header('X-Besoiu-Feed: baselinker-tm108');

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');

    if ($partRaw === 'index') {
        echo json_encode([
            'source' => 'besoiupieseauto',
            'generated_at' => $meta['generated_at'] ?? null,
            'product_count' => (int) ($meta['product_count'] ?? 0),
            'parts' => $meta['parts'] ?? [],
            'urls' => baselinker_feed_public_urls($token),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $jsonPath = $storageDir . '/catalog.json';
    if (!is_readable($jsonPath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Feed JSON indisponibil.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    readfile($jsonPath);
    exit;
}

// XML (implicit)
if ($partRaw === '' || $partRaw === 'index') {
    $parts = is_array($meta['parts'] ?? null) ? $meta['parts'] : [];
    if (count($parts) <= 1) {
        $partFile = $storageDir . '/catalog-001.xml';
    } else {
        $partFile = $storageDir . '/catalog-index.xml';
    }
} else {
    $partNumber = max(1, (int) $partRaw);
    $partFile = $storageDir . '/' . sprintf('catalog-%03d.xml', $partNumber);
}

if (!is_readable($partFile)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Fragment feed XML inexistent.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
readfile($partFile);
