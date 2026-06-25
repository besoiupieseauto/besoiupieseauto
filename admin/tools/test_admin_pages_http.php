<?php

declare(strict_types=1);

/**
 * Verifică pagini admin via HTTP — nu trebuie să conțină „Conținut indisponibil” sau suppliers.php lipsă.
 * Usage: php tools/test_admin_pages_http.php
 */

$base = rtrim(getenv('EVASYSTEM_WEB_BASE') ?: 'https://besoiupieseauto.ro/admin', '/');

/** @var list<string> $slugs */
$slugs = require dirname(__DIR__) . '/config/admin_nav_routes.php';

$failures = [];
$ok = 0;

function http_get(string $url): array
{
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'header'  => "Accept: text/html\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    return ['status' => $status, 'raw' => $raw === false ? '' : $raw];
}

$badNeedles = [
    'Conținut indisponibil',
    'fișier lipsă: <code>suppliers.php</code>',
    'fișier lipsă: <code>importproduse.php</code>',
    '<b>Warning</b>',
    'Fatal error',
    'Undefined variable',
];

foreach ($slugs as $slug) {
    if (in_array($slug, ['reg', 'addusers', 'reset-password', 'help', 'profileusers'], true)) {
        continue;
    }

    $url = $base . '/' . $slug;
    $res = http_get($url);

    if ($res['status'] < 200 || $res['status'] >= 500) {
        $failures[] = "{$slug}: HTTP {$res['status']}";
        continue;
    }

    foreach ($badNeedles as $needle) {
        if (str_contains($res['raw'], $needle)) {
            $failures[] = "{$slug}: conține «{$needle}»";
            continue 2;
        }
    }

    $ok++;
}

echo "HTTP pages: {$ok} OK, " . count($failures) . " FAIL\n";
foreach ($failures as $line) {
    echo "  FAIL {$line}\n";
}

exit($failures === [] ? 0 : 1);
