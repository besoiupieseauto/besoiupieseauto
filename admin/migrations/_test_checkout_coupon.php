<?php
declare(strict_types=1);

/**
 * Test checkout cupoane — validare BESOIU10 și endpoint.
 * Usage: php admin/migrations/_test_checkout_coupon.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

require_once dirname(__DIR__, 2) . '/system/shop-coupon.php';

$failures = 0;

function assertTrue(bool $ok, string $label): void
{
    global $failures;
    if ($ok) {
        echo "OK: {$label}\n";
        return;
    }
    echo "FAIL: {$label}\n";
    $failures++;
}

assertTrue(shop_coupon_table_exists($pdo), 'tabela coupons exista');

$invalid = shop_coupon_validate($pdo, 'INVALID_XYZ', 200.0);
assertTrue(!$invalid['valid'], 'cod invalid respins');

$belowMin = shop_coupon_validate($pdo, 'BESOIU10', 50.0);
assertTrue(!$belowMin['valid'], 'subtotal sub min_order respins');

$valid = shop_coupon_validate($pdo, 'BESOIU10', 200.0);
assertTrue($valid['valid'], 'BESOIU10 valid la 200 RON');
assertTrue(abs((float) ($valid['discount'] ?? 0) - 20.0) < 0.01, 'discount 10% = 20 RON');
assertTrue(abs((float) ($valid['total_after'] ?? 0) - 180.0) < 0.01, 'total_after = 180 RON');

$base = getenv('BESOIU_SITE_BASE') ?: 'https://besoiupieseauto.ro';
$url = rtrim($base, '/') . '/api/coupon_endpoint.php';
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => json_encode(['code' => 'BESOIU10', 'subtotal' => 200], JSON_THROW_ON_ERROR),
        'timeout' => 10,
        'ignore_errors' => true,
    ],
]);
$raw = @file_get_contents($url, false, $ctx);
$json = is_string($raw) ? json_decode($raw, true) : null;
assertTrue(is_array($json) && !empty($json['success']), 'coupon_endpoint POST BESOIU10 success');
assertTrue(abs((float) ($json['data']['discount'] ?? 0) - 20.0) < 0.01, 'coupon_endpoint discount corect');

$cartUrl = rtrim($base, '/') . '/cart.php';
$cartHtml = @file_get_contents($cartUrl, false, stream_context_create(['http' => ['timeout' => 10]]));
assertTrue(is_string($cartHtml) && str_contains($cartHtml, 'checkout-stepper'), 'cart.php contine stepper 3 pasi');
assertTrue(str_contains($cartHtml, 'cart-coupon-code'), 'cart.php contine camp cupon');
assertTrue(str_contains($cartHtml, 'checkout-success-panel'), 'cart.php contine panou confirmare pas 3');

if ($failures > 0) {
    echo "\nFAILED: {$failures} assertion(s)\n";
    exit(1);
}

echo "\nALL CHECKOUT COUPON TESTS PASSED\n";
