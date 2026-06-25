<?php

declare(strict_types=1);

/**
 * tm_110 — Endpoint public fișier integrare magazin BaseLinker (Shops API).
 * BaseLinker POST: action + bl_pass → JSON produse paginat (fără limită 30MB upload).
 */

require_once __DIR__ . '/../system/shop-db.php';
require_once __DIR__ . '/../system/baselinker-shop-integration.php';

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');
header('X-Besoiu-Integration: baselinker-shop-tm110');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
    echo json_encode([
        'error' => true,
        'error_code' => 'no_password',
        'error_text' => 'Besoiu Piese Auto — fișier integrare BaseLinker activ. Completați bl_pass din panoul BaseLinker.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = shop_db_bootstrap();
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode([
        'error' => true,
        'error_code' => 'db_unavailable',
        'error_text' => 'Serviciu indisponibil.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$post = $_POST;
if ($post === [] && ($raw = file_get_contents('php://input')) !== false && trim($raw) !== '') {
    parse_str($raw, $parsed);
    if (is_array($parsed)) {
        $post = $parsed;
    }
}

$result = baselinker_shop_dispatch($pdo, $post);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
