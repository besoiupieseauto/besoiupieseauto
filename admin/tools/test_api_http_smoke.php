<?php

declare(strict_types=1);

/**
 * Verifică endpoint-uri public/api via HTTP localhost — JSON valid, fără warning PHP.
 * Rulează: php tools/test_api_http_smoke.php
 */

$base = getenv('EVASYSTEM_API_BASE') ?: 'http://localhost/besoiupieseauto.ro/admin/api';

$endpoints = [
    'dashboard_endpoint.php' => [401, false], // auth required — JSON cu success false
    'search_logs_endpoint.php' => [401, false],
    'admin_hub_endpoint.php' => [401, false],
];

$failures = [];
$passed = 0;

foreach ($endpoints as $file => [$expectedStatus, $expectSuccessTrue]) {
    $url = rtrim($base, '/') . '/' . $file;
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    if ($raw === false) {
        $failures[] = "{$file}: request failed (server oprit sau URL greșit: {$url})";
        echo "SKIP {$file} — server indisponibil\n";
        continue;
    }

    if (str_contains($raw, '<b>Warning</b>') || str_contains($raw, 'Fatal error')) {
        $failures[] = "{$file}: output conține warning/fatal PHP";
        echo "FAIL {$file} — PHP warning/fatal în output\n";
        continue;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $failures[] = "{$file}: JSON invalid — " . substr(trim($raw), 0, 120);
        echo "FAIL {$file} — JSON invalid (HTTP {$status})\n";
        continue;
    }

    if ($status !== $expectedStatus) {
        $failures[] = "{$file}: HTTP {$status}, așteptat {$expectedStatus}";
        echo "FAIL {$file} — HTTP {$status} != {$expectedStatus}\n";
        continue;
    }

    $success = $decoded['success'] ?? null;
    if ($expectSuccessTrue && $success !== true) {
        $failures[] = "{$file}: success != true";
        echo "FAIL {$file} — success != true\n";
        continue;
    }
    if (!$expectSuccessTrue && $success !== false) {
        $failures[] = "{$file}: success != false (auth guard)";
        echo "FAIL {$file} — success != false\n";
        continue;
    }

    $passed++;
    echo "OK  {$file} HTTP {$status} JSON valid\n";
}

echo "\nPassed: {$passed}, Failed: " . count($failures) . "\n";

if ($failures !== []) {
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(count($failures) > 0 && $passed === 0 ? 2 : 1);
}

echo "HTTP API smoke test passed.\n";
