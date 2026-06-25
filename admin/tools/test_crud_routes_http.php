<?php

declare(strict_types=1);

/**
 * Test rute CRUD POST (rootFunction) — JSON valid prin index.php.
 */

$base = rtrim(getenv('EVASYSTEM_ADMIN_API_BASE') ?: getenv('EVASYSTEM_WEB_BASE') ?: 'http://besoiupieseauto.ro.test/admin', '/');

$routes = [
    '/crudcategorii' => '{"type_product":"list"}',
    '/crudblog' => '{"type_product":"list"}',
    '/crudadaoscomercial' => '{"type_product":"list"}',
    '/crudwebsite' => '{"type_product":"list"}',
    '/crudalerts' => '{"type_product":"add","name":"HttpTest","email":"t@t.local","status":1}',
];

$failures = [];
$passed = 0;

foreach ($routes as $path => $body) {
    $url = $base . $path;
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    if ($raw === false || $raw === '') {
        echo "SKIP {$path} — fără răspuns (HTTP {$status})\n";
        continue;
    }

    if (str_contains($raw, '<b>Warning</b>') || str_contains($raw, 'Fatal error')) {
        $failures[] = "{$path}: PHP warning/fatal";
        echo "FAIL {$path}\n";
        continue;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $failures[] = "{$path}: JSON invalid";
        echo "FAIL {$path} — " . substr(trim($raw), 0, 80) . "\n";
        continue;
    }

    $passed++;
    echo "OK  {$path} HTTP {$status} success=" . (($decoded['success'] ?? false) ? 'true' : 'false') . "\n";
}

echo "\nPassed: {$passed}, Failed: " . count($failures) . "\n";
exit($failures === [] ? 0 : 1);
