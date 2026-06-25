<?php
/**
 * robot/fb_parser.php — scanare grupuri Facebook (Apify) + validare OEM + răspuns sugerat AI.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/oem_lib.php';

header('Content-Type: application/json; charset=utf-8');

$statusFile = __DIR__ . '/data/status_fb.txt';
$currentStatus = is_file($statusFile) ? trim((string) file_get_contents($statusFile)) : 'stop';

if ($currentStatus !== 'run') {
    echo json_encode(['status' => 'stopped']);
    exit;
}

$token   = (string) env('APIFY_TOKEN', '');
$actorId = (string) env('APIFY_FB_ACTOR', 'apify/facebook-groups-scraper');
$groupUrl = (string) env('FB_GROUP_URL', 'https://www.facebook.com/groups/dezmebrari/');

if ($token === '') {
    echo json_encode(['status' => 'error', 'message' => 'APIFY_TOKEN lipsă din robot/.env']);
    exit;
}

$input = [
    'itemUrls' => [$groupUrl],
    'resultsLimit' => 8,
    'maxPostDate' => 'today',
];

$url = "https://api.apify.com/v2/acts/{$actorId}/run-sync-get-dataset-items?token=" . rawurlencode($token);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($input),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['status' => 'error', 'message' => 'Apify unreachable', 'http' => $httpCode]);
    exit;
}

$items = json_decode($response, true);
if (!is_array($items)) {
    echo json_encode(['status' => 'error', 'message' => 'Răspuns Apify invalid']);
    exit;
}

$output = [];

foreach ($items as $post) {
    if (!is_array($post)) {
        continue;
    }
    $text = trim((string) ($post['text'] ?? ''));
    if ($text === '' || !fb_is_parts_request($text)) {
        continue;
    }

    $oemCodes = fb_extract_oem_codes($text);
    $oemContext = fb_validate_oem_codes($oemCodes);
    $mesaj = fb_generate_reply($text, $oemContext);

    $firstHit = $oemContext['hits'][0] ?? null;
    $pret = '';
    if (is_array($firstHit)) {
        $pret = (string) ($firstHit['pPrice'] ?? $firstHit['price'] ?? '');
    }

    $output[] = [
        'id' => $post['id'] ?? md5($text),
        'ora' => isset($post['time']) ? date('H:i', strtotime((string) $post['time'])) : date('H:i'),
        'piesa' => mb_strimwidth($text, 0, 180, '...'),
        'url' => $post['url'] ?? '#',
        'autor' => $post['authorName'] ?? 'Utilizator FB',
        'oem_codes' => $oemCodes,
        'oem_found' => (bool) ($oemContext['any_found'] ?? false),
        'oem_hits' => count($oemContext['hits'] ?? []),
        'pret_hint' => $pret,
        'mesaj_sugerat' => $mesaj,
    ];
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
