<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\PieseAuto\PieseAutoRobotConfig;

ApiBootstrap::boot(false);
ApiBootstrap::requireAuthenticatedSession();

$channelId = PieseAutoRobotConfig::channelId();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning, X-Robot-Channel');
    http_response_code(204);
    exit;
}

$targetBase = PieseAutoRobotConfig::resolveLiveRobotBaseUrl();
$path = $_GET['path'] ?? '/';
if (!is_string($path) || $path === '' || $path[0] !== '/' || preg_match('#^//#', $path)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'mesaj' => 'Robot proxy path invalid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$path = PieseAutoRobotConfig::rewriteRobotPath($path);
$url = $targetBase . $path;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = file_get_contents('php://input') ?: '';
$headers = [
    'ngrok-skip-browser-warning: 69420',
    'X-Robot-Channel: ' . $channelId,
];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (is_string($contentType) && $contentType !== '') {
    $headers[] = 'Content-Type: ' . $contentType;
}

if ($body !== '' && str_starts_with(ltrim($body), '{')) {
    $json = json_decode($body, true);
    if (is_array($json)) {
        $body = json_encode(
            PieseAutoRobotConfig::rewriteRobotJsonBody($json),
            JSON_UNESCAPED_UNICODE
        );
    }
}

$ch = curl_init($url);
$quickPath = in_array($path, ['/verificare_sesiune', '/get_status', '/este_ocupat', '/stare_completa', '/reset_sesiune'], true)
    || str_starts_with($path, '/get_status')
    || str_starts_with($path, '/este_ocupat')
    || str_starts_with($path, '/stare_completa')
    || str_starts_with($path, '/reset_sesiune');
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => $quickPath ? 6 : 180,
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
    echo json_encode([
        'status' => 'error',
        'mesaj' => 'Robot PieseAuto indisponibil. Pornește robot\\start_pieseauto_visible.bat.',
        'detalii' => $error,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code($status > 0 ? $status : 200);
echo $response;
