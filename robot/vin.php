<?php
/**
 * robot/vin.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\vin.php
 * Modificari: $rapidApiKey, $openAiApiKey, $exchangeRatePlnToRon, $markupPercent din .env.
 * $productUrl ramane hardcoded (era hardcoded si in original).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rapidApiKey  = (string) env('RAPIDAPI_TECDOC_KEY', '');
$openAiApiKey = (string) env('OPENAI_KEY_VIN', '');
$exchangeRatePlnToRon = (float) env('EXCHANGE_RATE_PLN_RON', '1.17');
$markupPercent        = (int) env('MARKUP_PERCENT', '30');
$openAiModel          = (string) env('OPENAI_MODEL_VIN', 'gpt-5.4');

$productUrl = "https://allegro.pl/oferta/silnik-audi-q7-3-0-tdi-cas-casa-bez-miski-olejowej-17757997079?dd_referrer=https%3A%2F%2Fallegro.pl%2Foferta%2Fsilnik-audi-q7-3-0-tdi-cas-casa-bez-miski-olejowej-17757997079%3Fdd_referrer%3D";

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getNested(array $arr, array $keys, $default = null)
{
    $current = $arr;
    foreach ($keys as $key) {
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return $default;
        }
        $current = $current[$key];
    }
    return $current;
}

function stripHtmlContent(?string $html): string
{
    return trim(html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function formatMoney(float $value, string $currency = 'RON'): string
{
    return number_format($value, 2, ',', ' ') . ' ' . $currency;
}

function parseFormattedPriceToFloat(?string $formattedPrice): float
{
    $value = (string)$formattedPrice;
    $value = preg_replace('/[^\d,\.]/u', '', $value);
    $value = str_replace(' ', '', $value);

    if (substr_count($value, ',') > 0 && substr_count($value, '.') === 0) {
        $value = str_replace(',', '.', $value);
    } elseif (substr_count($value, ',') > 0 && substr_count($value, '.') > 0) {
        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '', $value);
    }

    return (float)$value;
}

function translateWithOpenAI(array $payload, string $apiKey, string $model = 'gpt-5.4'): array
{
    $url = 'https://api.openai.com/v1/responses';

    $prompt = [
        'role' => 'user',
        'content' => [
            [
                'type' => 'input_text',
                'text' =>
                    "Traduci în limba română, natural și comercial, păstrând numerele, codurile de produs și denumirile tehnice. " .
                    "Returnează STRICT JSON valid cu aceeași structură ca inputul. Nu adăuga explicații.\n\n" .
                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]
        ]
    ];

    $body = [
        'model' => $model,
        'input' => [$prompt]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException('OpenAI cURL error: ' . $err);
    }

    if ($httpCode >= 400) {
        throw new RuntimeException('OpenAI HTTP error: ' . $httpCode . ' | ' . $response);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Răspuns OpenAI invalid.');
    }

    $text = '';

    if (!empty($decoded['output']) && is_array($decoded['output'])) {
        foreach ($decoded['output'] as $item) {
            if (!empty($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (($content['type'] ?? '') === 'output_text') {
                        $text .= $content['text'] ?? '';
                    }
                }
            }
        }
    }

    $translated = json_decode(trim($text), true);

    if (!is_array($translated)) {
        throw new RuntimeException('OpenAI nu a întors JSON valid: ' . $text);
    }

    return $translated;
}

$apiUrl = "https://allegro2.p.rapidapi.com/v2/allegro/product?query=" . urlencode($productUrl);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "x-rapidapi-host: allegro2.p.rapidapi.com",
        "x-rapidapi-key: " . $rapidApiKey
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($err) {
    die("RapidAPI cURL Error: " . e($err));
}

if ($httpCode >= 400) {
    die("RapidAPI HTTP Error: " . e((string)$httpCode));
}

$data = json_decode($response, true);

if (!$data || !is_array($data)) {
    die("Răspuns JSON invalid de la RapidAPI.");
}

$title         = $data['name'] ?? '';
$offerId       = $data['offer_id'] ?? ($data['schema']['sku'] ?? '');
$currentUrl    = $data['current_url'] ?? ($data['schema']['url'] ?? '');
$sellerName    = $data['seller_name'] ?? '';
$sellerUrl     = $data['seller_listing_url'] ?? '';
$priceFormatted = getNested($data, ['price', 'formatted_price'], '');
$currency      = $data['currency'] ?? 'PLN';
$mainImage     = getNested($data, ['image', 'url'], '');
$imageAlt      = getNested($data, ['image', 'alt'], $title);
$deliveryCost  = $data['delivery_cost'] ?? '';
$sellerRating  = $data['seller_rating'] ?? '';
$brand         = getNested($data, ['schema', 'brand'], '');
$stock         = getNested($data, ['schema', 'stock'], '');
$itemCondition = getNested($data, ['schema', 'item_condition'], '');
$breadcrumbs   = $data['breadcrumbs'] ?? [];
$galleryItems  = $data['gallery_items'] ?? [];
$keyParameters = getNested($data, ['table_specifications', 'parametry'], []);

$descriptionSections = getNested($data, ['standardized', 'sections'], []);
$descriptionParts = [];

if (is_array($descriptionSections)) {
    foreach ($descriptionSections as $section) {
        if (!isset($section['items']) || !is_array($section['items'])) {
            continue;
        }

        foreach ($section['items'] as $item) {
            if (($item['type'] ?? '') === 'TEXT' && !empty($item['content'])) {
                $cleanText = stripHtmlContent($item['content']);
                if ($cleanText !== '') {
                    $descriptionParts[] = $cleanText;
                }
            }
        }
    }
}

$fullDescription = implode("\n\n", array_unique($descriptionParts));

if (empty($keyParameters)) {
    $keyParameters = [];
    $groups = $data['groups'] ?? [];

    foreach ($groups as $group) {
        foreach (['first_sub_group', 'second_sub_group'] as $subGroupKey) {
            if (!isset($group[$subGroupKey]) || !is_array($group[$subGroupKey])) {
                continue;
            }

            foreach ($group[$subGroupKey] as $param) {
                $paramName = $param['name'] ?? '';
                $paramValue = $param['value']['name'] ?? '';
                if ($paramName !== '' && $paramValue !== '') {
                    $keyParameters[$paramName] = $paramValue;
                }
            }
        }
    }
}

$images = [];
if ($mainImage) {
    $images[] = ['url' => $mainImage, 'alt' => $imageAlt];
}

foreach ($galleryItems as $img) {
    $imgUrl = $img['original'] ?? ($img['embeded'] ?? '');
    $imgAlt = $img['alt'] ?? $title;

    if ($imgUrl !== '') {
        $images[] = ['url' => $imgUrl, 'alt' => $imgAlt];
    }
}

$tmp = [];
$seen = [];
foreach ($images as $img) {
    if (!in_array($img['url'], $seen, true)) {
        $seen[] = $img['url'];
        $tmp[] = $img;
    }
}
$images = $tmp;

$translatePayload = [
    'title' => $title,
    'breadcrumbs' => array_map(static function ($crumb) {
        return $crumb['name'] ?? '';
    }, $breadcrumbs),
    'parameters' => $keyParameters,
    'description' => $fullDescription
];

try {
    $translated = translateWithOpenAI($translatePayload, $openAiApiKey, $openAiModel);
} catch (Throwable $e) {
    $translated = [
        'title' => $title,
        'breadcrumbs' => array_map(static function ($crumb) {
            return $crumb['name'] ?? '';
        }, $breadcrumbs),
        'parameters' => $keyParameters,
        'description' => $fullDescription
    ];
}

$pricePln = parseFormattedPriceToFloat($priceFormatted);
$priceRonBase = $pricePln * $exchangeRatePlnToRon;
$priceRonFinal = $priceRonBase * (1 + ($markupPercent / 100));

$deliveryPln = parseFormattedPriceToFloat($deliveryCost);
$deliveryRon = $deliveryPln > 0 ? $deliveryPln * $exchangeRatePlnToRon : 0.0;

$translatedTitle = $translated['title'] ?? $title;
$translatedDescription = $translated['description'] ?? $fullDescription;
$translatedParameters = $translated['parameters'] ?? $keyParameters;
$translatedBreadcrumbs = $translated['breadcrumbs'] ?? [];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title><?= e($translatedTitle ?: 'Produs') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box}
        body{margin:0;padding:30px;font-family:Arial,Helvetica,sans-serif;background:#0f172a;color:#e5e7eb;}
        .wrap{width:min(1400px, 100%);margin:0 auto;}
        .card{background:linear-gradient(180deg,#111827 0%,#0b1220 100%);border:1px solid #23304a;border-radius:22px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.30);}
        .top{display:grid;grid-template-columns:430px 1fr;}
        @media(max-width:980px){.top{grid-template-columns:1fr}}
        .image-box{background:#0a0f1a;min-height:420px;display:flex;align-items:center;justify-content:center;overflow:hidden;}
        .image-box img{width:100%;height:100%;object-fit:cover;display:block;}
        .content{padding:26px;}
        .breadcrumbs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;font-size:13px;color:#93c5fd;}
        .breadcrumbs span{background:#172033;border:1px solid #2f3f5e;border-radius:999px;padding:6px 10px;}
        h1{margin:0 0 12px;font-size:30px;line-height:1.2;color:#fff;}
        .price-pln{font-size:18px;color:#cbd5e1;margin-bottom:6px;}
        .price-ron{font-size:38px;font-weight:800;color:#22c55e;margin:0 0 18px;}
        .badges{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
        .badge{padding:8px 12px;border-radius:999px;background:#172033;border:1px solid #2f3f5e;color:#dbeafe;font-size:13px;}
        .meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:20px;}
        @media(max-width:720px){.meta{grid-template-columns:1fr}}
        .meta-item{background:#0f172a;border:1px solid #1e293b;border-radius:14px;padding:12px;}
        .meta-label{font-size:12px;color:#94a3b8;margin-bottom:6px;text-transform:uppercase;}
        .meta-value{color:#fff;font-size:15px;word-break:break-word;}
        .section{margin-top:22px;}
        .section h2{margin:0 0 12px;color:#fff;font-size:20px;}
        .params{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;}
        @media(max-width:720px){.params{grid-template-columns:1fr}}
        .param{background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:12px;}
        .param-name{font-size:12px;color:#94a3b8;margin-bottom:5px;text-transform:uppercase;}
        .param-value{color:#fff;}
        .desc{background:#0f172a;border:1px solid #1e293b;border-radius:14px;padding:16px;line-height:1.65;white-space:pre-wrap;color:#e2e8f0;}
        .gallery{padding:24px;border-top:1px solid #1f2937;}
        .gallery h2{margin:0 0 14px;}
        .gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;}
        .gallery-grid img{width:100%;height:180px;object-fit:cover;border-radius:14px;border:1px solid #243041;background:#fff;}
        a{color:#93c5fd;text-decoration:none;}
        a:hover{text-decoration:underline;}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="top">
            <div class="image-box">
                <?php if ($mainImage): ?>
                    <img src="<?= e($mainImage) ?>" alt="<?= e($imageAlt) ?>">
                <?php else: ?>
                    <div>Fără imagine</div>
                <?php endif; ?>
            </div>

            <div class="content">
                <?php if (!empty($translatedBreadcrumbs)): ?>
                    <div class="breadcrumbs">
                        <?php foreach ($translatedBreadcrumbs as $crumb): ?>
                            <span><?= e((string)$crumb) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h1><?= e($translatedTitle) ?></h1>

                <div class="price-pln">Preț sursă: <?= e($priceFormatted) ?></div>
                <div class="price-ron">Preț final: <?= e(formatMoney($priceRonFinal, 'RON')) ?></div>

                <div class="badges">
                    <div class="badge">Curs PLN→RON: <?= e((string)$exchangeRatePlnToRon) ?></div>
                    <div class="badge">Adaos comercial: <?= e((string)$markupPercent) ?>%</div>
                    <?php if (!empty($translatedParameters['Stan'])): ?>
                        <div class="badge">Stare: <?= e($translatedParameters['Stan']) ?></div>
                    <?php elseif (!empty($translatedParameters['stan'])): ?>
                        <div class="badge">Stare: <?= e($translatedParameters['stan']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($brand)): ?>
                        <div class="badge">Brand: <?= e($brand) ?></div>
                    <?php endif; ?>
                </div>

                <div class="meta">
                    <div class="meta-item"><div class="meta-label">Offer ID</div><div class="meta-value"><?= e($offerId) ?></div></div>
                    <div class="meta-item"><div class="meta-label">Preț PLN</div><div class="meta-value"><?= e($priceFormatted) ?></div></div>
                    <div class="meta-item"><div class="meta-label">Preț RON fără adaos</div><div class="meta-value"><?= e(formatMoney($priceRonBase, 'RON')) ?></div></div>
                    <div class="meta-item"><div class="meta-label">Preț RON final</div><div class="meta-value"><?= e(formatMoney($priceRonFinal, 'RON')) ?></div></div>
                    <div class="meta-item"><div class="meta-label">Livrare estimată</div><div class="meta-value"><?= e($deliveryCost) ?><?php if ($deliveryRon > 0): ?> (≈ <?= e(formatMoney($deliveryRon, 'RON')) ?>)<?php endif; ?></div></div>
                    <div class="meta-item"><div class="meta-label">Vânzător</div><div class="meta-value"><?php if ($sellerUrl): ?><a href="<?= e($sellerUrl) ?>" target="_blank"><?= e($sellerName) ?></a><?php else: ?><?= e($sellerName) ?><?php endif; ?></div></div>
                    <div class="meta-item"><div class="meta-label">Rating seller</div><div class="meta-value"><?= e($sellerRating) ?></div></div>
                    <div class="meta-item"><div class="meta-label">Stock</div><div class="meta-value"><?= e($stock) ?></div></div>
                    <div class="meta-item" style="grid-column:1 / -1;"><div class="meta-label">Link ofertă</div><div class="meta-value"><a href="<?= e($currentUrl) ?>" target="_blank">Deschide oferta originală</a></div></div>
                </div>

                <?php if (!empty($translatedParameters) && is_array($translatedParameters)): ?>
                    <div class="section">
                        <h2>Parametri traduși</h2>
                        <div class="params">
                            <?php foreach ($translatedParameters as $name => $value): ?>
                                <div class="param">
                                    <div class="param-name"><?= e((string)$name) ?></div>
                                    <div class="param-value"><?= e((string)$value) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($translatedDescription !== ''): ?>
                    <div class="section">
                        <h2>Descriere tradusă</h2>
                        <div class="desc"><?= e($translatedDescription) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($images)): ?>
            <div class="gallery">
                <h2>Galerie</h2>
                <div class="gallery-grid">
                    <?php foreach ($images as $img): ?>
                        <img src="<?= e($img['url']) ?>" alt="<?= e($img['alt']) ?>">
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
