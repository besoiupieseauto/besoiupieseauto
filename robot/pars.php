<?php
/**
 * robot/pars.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\pars.php
 * Modificari: APIFY_TOKEN + ACTOR_ID din .env. Restul = identic.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

date_default_timezone_set('Europe/Chisinau');
header('Content-Type: application/json; charset=utf-8');

$APIFY_TOKEN = (string) env('APIFY_TOKEN', '');
$ACTOR_ID    = (string) env('APIFY_FB_ACTOR', 'apify/facebook-groups-scraper');

if ($APIFY_TOKEN === '') {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Missing APIFY_TOKEN in robot/.env'], JSON_PRETTY_PRINT);
    exit;
}

$body = file_get_contents('php://input');
$req  = json_decode($body ?: '', true);
if (!is_array($req)) $req = [];

$groupUrl = is_string($req['groupUrl'] ?? null) && $req['groupUrl'] !== ''
    ? $req['groupUrl']
    : 'https://www.facebook.com/groups/workmd';

$resultsLimit = (int)($req['resultsLimit'] ?? 20);
$resultsLimit = max(1, min($resultsLimit, 200));

$input = [
    "startUrls" => [
        ["url" => $groupUrl]
    ],
    "resultsLimit" => $resultsLimit
];

$url = "https://api.apify.com/v2/acts/" . rawurlencode($ACTOR_ID) . "/runs?token=" . rawurlencode($APIFY_TOKEN);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json; charset=utf-8",
        "Accept: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

$raw  = curl_exec($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'curl','message'=>$err], JSON_PRETTY_PRINT);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'not_json','http'=>$code,'raw'=>$raw], JSON_PRETTY_PRINT);
    exit;
}

$runId     = $data['data']['id'] ?? null;
$status    = $data['data']['status'] ?? null;
$datasetId = $data['data']['defaultDatasetId'] ?? null;

echo json_encode([
    'ok' => ($code >= 200 && $code < 300 && is_string($runId) && $runId !== ''),
    'http' => $code,
    'runId' => $runId,
    'status' => $status,
    'datasetId' => $datasetId,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
