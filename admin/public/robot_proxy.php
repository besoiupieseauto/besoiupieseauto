<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning');
    http_response_code(204);
    exit;
}

$targetBase = 'https://newton-candent-len.ngrok-free.dev';
$path = $_GET['path'] ?? '/';
if (!is_string($path) || $path === '' || $path[0] !== '/' || preg_match('#^//#', $path)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'mesaj' => 'Robot proxy path invalid.']);
    exit;
}

$url = $targetBase . $path;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = file_get_contents('php://input') ?: '';
$headers = [
    'ngrok-skip-browser-warning: 69420',
];

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (is_string($contentType) && $contentType !== '') {
    $headers[] = 'Content-Type: ' . $contentType;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => $headers,
]);

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$contentTypeResponse = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/json; charset=utf-8';
curl_close($ch);

header('Access-Control-Allow-Origin: *');
header('Content-Type: ' . $contentTypeResponse);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'mesaj' => 'Robot server indisponibil.', 'detalii' => $error]);
    exit;
}

http_response_code($status > 0 ? $status : 200);
echo $response;