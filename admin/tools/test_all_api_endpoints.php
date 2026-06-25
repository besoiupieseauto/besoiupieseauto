<?php

declare(strict_types=1);

/**
 * Audit HTTP — toate endpoint-urile public/api/ trebuie JSON valid (fără warning PHP).
 */

$base = rtrim(getenv('EVASYSTEM_API_BASE') ?: 'http://besoiupieseauto.ro.test/admin/api', '/');

$apiDir = dirname(__DIR__) . '/public/api';
$files = glob($apiDir . '/*_endpoint.php') ?: [];
$files[] = $apiDir . '/dashboard_snapshot_cron.php';
$files = array_unique($files);

/** @var array<string, array{method:string, body:?string, query?:string, expectAuth:bool}> */
$profiles = [
    'admin_hub_endpoint.php' => ['method' => 'GET', 'body' => null, 'query' => '?action=overview', 'expectAuth' => true],
    'backup_endpoint.php' => ['method' => 'POST', 'body' => '{"action":"status"}', 'expectAuth' => true],
    'bots_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'caiet_comenzi_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"stats"}', 'expectAuth' => true],
    'categorii_endpoint.php' => ['method' => 'GET', 'body' => null, 'query' => '?action=popup', 'expectAuth' => false],
    'clienti_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'comenzi_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'dashboard_endpoint.php' => ['method' => 'GET', 'body' => null, 'expectAuth' => true],
    'dashboard_snapshot_cron.php' => ['method' => 'GET', 'body' => null, 'query' => '?key=invalid', 'expectAuth' => false],
    'facturi_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'furnizori_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'internal_order_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'legacy_orders_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'livrare_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'marketplace_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'messages_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'order_tmp_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'search_logs_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'system_errors_endpoint.php' => ['method' => 'POST', 'body' => '{"type_product":"list"}', 'expectAuth' => true],
    'supplier_cart_endpoint.php' => ['method' => 'POST', 'body' => '{"action":"list"}', 'expectAuth' => true],
    'supplier_search_endpoint.php' => ['method' => 'POST', 'body' => '{"action":"ping"}', 'expectAuth' => true],
    'supplier_sync_endpoint.php' => ['method' => 'POST', 'body' => '{"action":"status"}', 'expectAuth' => false],
    'tecdoc_endpoint.php' => ['method' => 'GET', 'body' => null, 'query' => '?action=status', 'expectAuth' => false],
];

$failures = [];
$passed = 0;

function httpProbe(string $url, string $method = 'GET', ?string $body = null): array
{
    $headers = "Accept: application/json\r\n";
    if ($body !== null) {
        $headers .= "Content-Type: application/json\r\n";
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $body ?? '',
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    return ['status' => $status, 'raw' => $raw === false ? '' : $raw];
}

foreach ($files as $filePath) {
    $name = basename($filePath);
    if ($name === '_autoload.php' || !isset($profiles[$name])) {
        continue;
    }

    $profile = $profiles[$name];
    $url = $base . '/' . $name . ($profile['query'] ?? '');
    $probe = httpProbe($url, $profile['method'], $profile['body']);
    $raw = $probe['raw'];
    $status = $probe['status'];

    if ($raw === '' && $status === 0) {
        echo "SKIP {$name}\n";
        continue;
    }

    if (str_contains($raw, '<b>Warning</b>') || str_contains($raw, '<b>Fatal error</b>') || str_contains($raw, 'Uncaught Error')) {
        $failures[] = "{$name}: PHP invalid output";
        echo "FAIL {$name} — PHP warning/fatal\n";
        continue;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !array_key_exists('success', $decoded)) {
        $failures[] = "{$name}: JSON invalid";
        echo "FAIL {$name} — JSON invalid\n";
        continue;
    }

    if ($profile['expectAuth'] && $status !== 401) {
        $failures[] = "{$name}: expected HTTP 401 without session, got {$status}";
        echo "FAIL {$name} — expected 401, got {$status}\n";
        continue;
    }

    if (!$profile['expectAuth'] && $name === 'dashboard_snapshot_cron.php' && !in_array($status, [403, 503], true)) {
        $failures[] = "{$name}: expected 403 (bad key) or 503 (unconfigured), got {$status}";
        echo "FAIL {$name} — expected 403/503, got {$status}\n";
        continue;
    }

    if (!$profile['expectAuth'] && $name === 'categorii_endpoint.php' && !$decoded['success']) {
        $failures[] = "{$name}: public read failed";
        echo "FAIL {$name} — public read\n";
        continue;
    }

    if (!$profile['expectAuth'] && $name === 'tecdoc_endpoint.php' && !$decoded['success']) {
        $failures[] = "{$name}: tecdoc cache status failed";
        echo "FAIL {$name} — cache status\n";
        continue;
    }

    $passed++;
    echo "OK  {$name} HTTP {$status}\n";
}

echo "\nPassed: {$passed}, Failed: " . count($failures) . "\n";
exit($failures === [] ? 0 : 1);
