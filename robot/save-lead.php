<?php
/**
 * robot/save-lead.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\save-lead.php
 * Modificari: leads.json mutat in /data/leads.json. Restul = identic.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateDir = __DIR__ . '/data/rate_limits';
if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0775, true);
}
$rateFile = $rateDir . '/' . sha1($ip) . '.lead.json';
$now = time();
$rate = ['count' => 0, 'ts' => $now];
if (is_file($rateFile)) {
    $decoded = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($decoded)) {
        $rate = $decoded;
    }
}
if (($now - (int) ($rate['ts'] ?? 0)) > 3600) {
    $rate = ['count' => 0, 'ts' => $now];
}
if ((int) ($rate['count'] ?? 0) >= 10) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate_limit']);
    exit;
}
$rate['count'] = (int) ($rate['count'] ?? 0) + 1;
$rate['ts'] = $now;
@file_put_contents($rateFile, json_encode($rate), LOCK_EX);

$input = json_decode(file_get_contents('php://input'), true);

if (!empty($input['website'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

if (!$input || empty($input['name']) || empty($input['phone']) || empty($input['msg'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

$lead = [
    'name'  => strip_tags($input['name']),
    'phone' => strip_tags($input['phone']),
    'msg'   => strip_tags($input['msg']),
    'page'  => $input['page'] ?? '',
    'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
    'date'  => date('Y-m-d H:i:s')
];

$file = __DIR__ . '/data/leads.json';

if (!file_exists($file)) {
    file_put_contents($file, json_encode([], JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents($file), true);
if (!is_array($data)) $data = [];

$data[] = $lead;

file_put_contents(
    $file,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

echo json_encode(['ok' => true]);
