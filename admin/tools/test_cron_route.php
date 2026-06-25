<?php

declare(strict_types=1);

/**
 * Verifică normalizarea rutei /admin/cron (conflict folder fizic).
 * Usage: php admin/tools/test_cron_route.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Core\AdminUrl;

$failures = [];

$cases = [
    '/admin/cron' => '/admin/cron',
    '/admin/cron/' => '/admin/cron',
    '/admin/cron/index.php' => '/admin/cron',
    '/admin/public/cron' => '/admin/public/cron',
];

foreach ($cases as $input => $expected) {
    $got = AdminUrl::normalizeRequestPath($input);
    if ($got !== $expected) {
        $failures[] = "normalizeRequestPath({$input}) = {$got}, expected {$expected}";
    }
}

$alts = AdminUrl::alternatePaths('/admin/cron');
if (!in_array('/admin/cron', $alts, true) || !in_array('/admin/public/cron', $alts, true)) {
    $failures[] = 'alternatePaths(/admin/cron) missing expected variants: ' . implode(', ', $alts);
}

$siteBase = rtrim(getenv('EVASYSTEM_WEB_BASE') ?: 'https://besoiupieseauto.ro', '/');

foreach (['/admin/cron', '/admin/cron/'] as $cronPath) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: text/html\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($siteBase . $cronPath, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    if ($status === 301) {
        $failures[] = "{$cronPath} returns 301 (directory slash redirect) — verificați .htaccess";
    }
    if ($raw !== false && str_contains($raw, 'id="admin-login-form"') && !str_contains($raw, 'id="cron-sync-page"')) {
        // guest → login e OK fără sesiune
        echo "OK  {$cronPath} → login (fără sesiune, HTTP {$status})\n";
    } elseif ($raw !== false && str_contains($raw, 'id="cron-sync-page"')) {
        echo "OK  {$cronPath} → panou Cron Sync (HTTP {$status})\n";
    } else {
        echo "??  {$cronPath} HTTP {$status}, body=" . substr(strip_tags((string) $raw), 0, 80) . "\n";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "test_cron_route: normalize OK\n";
