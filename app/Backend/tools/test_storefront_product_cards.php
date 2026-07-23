<?php
declare(strict_types=1);

/**
 * P7 — build magazin card HTML for 5 products (sale + badge) and assert critical bits.
 *
 * php app/Backend/tools/test_storefront_product_cards.php
 */

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

// Avoid rendering the full catalog grid when loading helpers from product.php.
if (!defined('BESOIU_SKIP_PRODUCT_GRID')) {
    define('BESOIU_SKIP_PRODUCT_GRID', true);
}
require_once BESOIU_LEGACY . '/product_dual_title.php';
require_once BESOIU_LEGACY . '/product.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$config = require BESOIU_CONFIG . '/config.php';
Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: {$msg}\n";
};

echo "══════════════════════════════════════════════════════════\n";
echo " P7 — test carduri storefront (preț / badge / Detalii)\n";
echo "══════════════════════════════════════════════════════════\n\n";

$samples = [
    [
        'randomn_id' => 'aaaaaaaaaaaaaaaa',
        'pName' => 'Plăcuțe frână Bosch TEST',
        'pCode' => '0986424268',
        'pBrand' => 'Bosch',
        'pPrice' => '189.90',
        'pBasePrice' => '140.00',
        'pPriceOld' => '249.00',
        'pBadge' => 'hot',
        'pStock' => '5',
        'pImages' => '[]',
        'pNote' => 'Brand::Bosch | Cod::0986424268',
        'pCategory' => 'Frâne',
        'pShipping' => '24h',
    ],
    [
        'randomn_id' => 'bbbbbbbbbbbbbbbb',
        'pName' => 'Filtru ulei Mann TEST',
        'pCode' => 'W71275',
        'pBrand' => 'Mann',
        'pPrice' => '', // empty final → fallback pBasePrice
        'pBasePrice' => '45.50',
        'pBadge' => 'promo',
        'pStock' => '12',
        'pImages' => '[]',
        'pCategory' => 'Filtre',
    ],
    [
        'randomn_id' => 'cccccccccccccccc',
        'pName' => 'Disc frână ATE TEST',
        'pCode' => '24.0128-0149.1',
        'pBrand' => 'ATE',
        'pPrice' => '1.234,56', // RO format
        'pBasePrice' => '900',
        'pBadge' => 'nou',
        'pStock' => '2',
        'pImages' => '[]',
        'pCategory' => 'Frâne',
    ],
    [
        'randomn_id' => 'dddddddddddddddd',
        'pName' => 'Amortizor Sachs TEST',
        'pCode' => '170987',
        'pBrand' => 'Sachs',
        'pPrice' => '320',
        'pBasePrice' => '250',
        'compare_at_price' => '399',
        'pBadge' => 'top',
        'pStock' => '1',
        'pImages' => '[]',
        'pCategory' => 'Suspensie',
    ],
    [
        'randomn_id' => 'eeeeeeeeeeeeeeee',
        'pName' => 'Antigel Castrol TEST',
        'pCode' => 'ANT-5L',
        'pBrand' => 'Castrol',
        'pPrice' => '89',
        'pBasePrice' => '60',
        'pBadge' => 'stoc',
        'pStock' => '20',
        'pImages' => '[]',
        'pCategory' => 'Consumabile',
    ],
];

// Also pull up to 2 live rows when available (empty pPrice + base).
$pdo = Config\Database::getDB();
$live = $pdo->query(
    "SELECT randomn_id, pName, pCode, pBrand, pPrice, pBasePrice, pBadge, pStock, pImages, pNote, pCategory, pShipping
     FROM produse
     WHERE COALESCE(status, 1) <> 0
     ORDER BY id DESC
     LIMIT 2"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($live as $row) {
    if (trim((string) ($row['randomn_id'] ?? '')) !== '') {
        $samples[] = $row;
    }
}

$checked = 0;
foreach (array_slice($samples, 0, 5) as $index => $product) {
    $n = $index + 1;
    ob_start();
    besoiu_render_magazin_card($product);
    $html = (string) ob_get_clean();

    $offer = besoiu_product_card_offer_price($product);
    $priceHtml = besoiu_product_card_price_html($product);
    $badgeKey = trim((string) ($product['pBadge'] ?? ''));
    $badgeHtml = besoiu_product_badge_html($badgeKey);
    $productId = trim((string) ($product['randomn_id'] ?? ''));
    $actions = besoiu_product_card_actions_html($productId);

    if ($offer <= 0) {
        $fail("Produs #{$n}: offer price <= 0 (pPrice/pBasePrice).");
    }
    if ($priceHtml === '' || !str_contains($priceHtml, '_product-price')) {
        $fail("Produs #{$n}: price HTML invalid.");
    }
    if (str_contains($priceHtml, 'La cerere')) {
        $fail("Produs #{$n}: price HTML arată „La cerere” deși offer={$offer}.");
    }
    if ($badgeKey !== '' && ($badgeHtml === '' || !str_contains($html, '_product-card-badge'))) {
        $fail("Produs #{$n}: badge „{$badgeKey}” lipsește din HTML.");
    }
    if ($badgeKey !== '' && !str_contains($html, '_product-card-image')) {
        $fail("Produs #{$n}: lipsește wrapper imagine pentru poziție badge.");
    }
    if (!preg_match('~class="[^"]*product_detal[^"]*"[^>]*href="(/produs\?id=[^"]+)"~', $actions, $m)
        && !preg_match('~href="(/produs\?id=[^"]+)"[^>]*class="[^"]*product_detal~', $actions, $m)) {
        $fail("Produs #{$n}: Detalii href lipsește/invalid în actions HTML.");
    }
    $href = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    if ($productId !== '' && !str_contains($href, rawurlencode($productId)) && !str_contains($href, $productId)) {
        $fail("Produs #{$n}: Detalii href pe ID greșit: {$href}");
    }
    if (!str_contains($html, 'Stoc:')) {
        $fail("Produs #{$n}: câmp stoc lipsă pe cartelă.");
    }
    if (!str_contains($html, $href) && !str_contains($html, besoiu_catalog_h($href))) {
        $fail("Produs #{$n}: href Detalii nu apare în card HTML.");
    }

    $ok("Card #{$n}: price={$offer} badge=" . ($badgeKey !== '' ? $badgeKey : '-') . " href={$href}");
    $checked++;
}

if ($checked < 5) {
    $fail("Au fost verificate doar {$checked} carduri (minim 5).");
}

echo "\nALL P7 CHECKS PASSED ({$checked} cards)\n";
exit(0);
