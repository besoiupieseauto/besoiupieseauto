<?php

declare(strict_types=1);

/**
 * Verificare HTTP login admin — pagină + API addusersadd (fără credențiale reale în repo).
 * Usage: php tools/test_admin_login_http.php
 */

$siteBase = rtrim(getenv('EVASYSTEM_WEB_BASE') ?: 'http://besoiupieseauto.ro.test', '/');
$base = rtrim(getenv('EVASYSTEM_ADMIN_PUBLIC_BASE') ?: $siteBase . '/admin/public', '/');
$apiBase = rtrim(getenv('EVASYSTEM_ADMIN_API_BASE') ?: $siteBase . '/admin', '/');
$failures = [];

function http_request(string $url, string $method = 'GET', ?string $body = null): array
{
    $headers = "Accept: text/html,application/json\r\n";
    if ($body !== null) {
        $headers .= "Content-Type: application/json\r\n";
    }

    $ctx = stream_context_create([
        'http' => [
            'method'  => $method,
            'header'  => $headers,
            'content' => $body ?? '',
            'timeout' => 12,
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

$loginUrl = $base . '/login';
$page = http_request($loginUrl);
if ($page['status'] < 200 || $page['status'] >= 400) {
    $failures[] = "GET /login HTTP {$page['status']}";
} else {
    $needles = [
        'id="admin-login-form"',
        'id="admin-login-user"',
        'id="admin-login-password"',
        'id="admin-login-toggle-pass"',
        'data-action="toggle-password"',
        'id="admin-login-submit"',
        'data-endpoint="/admin/addusersadd"',
        'admin-login.js',
    ];
    foreach ($needles as $needle) {
        if (!str_contains($page['raw'], $needle)) {
            $failures[] = "login HTML missing: {$needle}";
        }
    }
}

$badUserBody = json_encode([
    'type_product' => 'login',
    'login'        => '__boon_invalid_user__',
    'password'     => 'WrongPass1',
], JSON_UNESCAPED_UNICODE);

$badUser = http_request($apiBase . '/addusersadd', 'POST', $badUserBody);
$badUserJson = json_decode($badUser['raw'], true);
if (!is_array($badUserJson) || ($badUserJson['success'] ?? null) !== false) {
    $failures[] = 'POST login unknown user must return success=false';
}

$knownLogin = trim((string) (getenv('BOON_ADMIN_LOGIN_PROBE') ?: 'admin'));
$wrongPwBody = json_encode([
    'type_product' => 'login',
    'login'        => $knownLogin,
    'password'     => 'WrongPass9x',
], JSON_UNESCAPED_UNICODE);

$wrongPw = http_request($apiBase . '/addusersadd', 'POST', $wrongPwBody);
$wrongPwJson = json_decode($wrongPw['raw'], true);
if (!is_array($wrongPwJson)) {
    $failures[] = 'POST login wrong password: JSON invalid — ' . substr($wrongPw['raw'], 0, 120);
} elseif (($wrongPwJson['success'] ?? null) === true) {
    $failures[] = 'POST login wrong password must return success=false (regresie securitate)';
}

$validLogin = getenv('BOON_ADMIN_LOGIN');
$validPass = getenv('BOON_ADMIN_PASSWORD');
if (is_string($validLogin) && $validLogin !== '' && is_string($validPass) && $validPass !== '') {
    $okBody = json_encode([
        'type_product' => 'login',
        'login'        => $validLogin,
        'password'     => $validPass,
    ], JSON_UNESCAPED_UNICODE);
    $okRes = http_request($apiBase . '/addusersadd', 'POST', $okBody);
    $okJson = json_decode($okRes['raw'], true);
    if (!is_array($okJson) || ($okJson['success'] ?? null) !== true) {
        $failures[] = 'POST login valid credentials failed (env BOON_ADMIN_*)';
    } elseif (empty($okJson['redirect'])) {
        $failures[] = 'POST login success must include redirect';
    }
}

if ($failures !== []) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: {$f}\n");
    }
    exit(1);
}

echo "test_admin_login_http OK\n";
exit(0);
