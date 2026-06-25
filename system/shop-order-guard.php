<?php
declare(strict_types=1);

require_once __DIR__ . '/shop-auth.php';

/**
 * CSRF + rate limit pentru plasarea comenzilor din magazin.
 */

function shop_order_guard_session_start(): void
{
    shop_auth_session_start();
}

function shop_order_csrf_token(): string
{
    shop_order_guard_session_start();

    if (empty($_SESSION['shop_order_csrf']) || !is_string($_SESSION['shop_order_csrf'])) {
        $_SESSION['shop_order_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['shop_order_csrf'];
}

function shop_order_csrf_validate(?string $token): bool
{
    shop_order_guard_session_start();

    $expected = (string) ($_SESSION['shop_order_csrf'] ?? '');
    if ($expected === '' || $token === null || $token === '') {
        return false;
    }

    return hash_equals($expected, trim($token));
}

function shop_order_csrf_rotate(): string
{
    shop_order_guard_session_start();
    $_SESSION['shop_order_csrf'] = bin2hex(random_bytes(32));

    return (string) $_SESSION['shop_order_csrf'];
}

function shop_order_rate_limit_check(int $maxPerHour = 15): bool
{
    $ip = shop_order_client_ip();
    if ($ip === '') {
        return true;
    }

    $storageDir = dirname(__DIR__) . '/admin/storage/rate_limits';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        return true;
    }

    $file = $storageDir . '/shop_orders_' . hash('sha256', $ip) . '.json';
    $now = time();
    $windowStart = $now - 3600;
    $state = ['count' => 0, 'started_at' => $now];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }

    $startedAt = (int) ($state['started_at'] ?? $now);
    $count = (int) ($state['count'] ?? 0);

    if ($startedAt < $windowStart) {
        $startedAt = $now;
        $count = 0;
    }

    if ($count >= $maxPerHour) {
        return false;
    }

    $state = [
        'count' => $count + 1,
        'started_at' => $startedAt,
        'last_at' => $now,
    ];

    file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE));

    return true;
}

function shop_order_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim(explode(',', (string) $candidate)[0]);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '';
}
