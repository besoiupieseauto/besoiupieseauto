<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

@set_time_limit(300);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    $data = $_POST;
}

/** @var list<array<string, mixed>> $products */
$products = $data['products'] ?? [];
if (!is_array($products) || $products === []) {
    import_json_response(['success' => false, 'error' => 'Lipsește lista products'], 400);
}

$matcherPath = import_motor_product_matcher_path();
if (!is_file($matcherPath)) {
    import_json_response(['success' => false, 'error' => 'ProductMatcher lipsă: ' . $matcherPath], 503);
}
require_once $matcherPath;
import_require_prelucrare_lib('BaseIndexLookup.php');
import_require_prelucrare_lib('CoreDbLookup.php');
import_require_fetch_product_lib('BrandCatalogResolver.php');

$allowed = ['exact' => true, 'probable' => true, 'conflict' => true];
$matcher = new ProductMatcher();
$brands = new BrandCatalogResolver();
$cards = [];
$skipped = [];

/**
 * @return list<string>
 */
function import_tecdoc_code_variants(array $p): array
{
    $sku = trim((string) ($p['sku_supplier'] ?? ''));
    $codeNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $sku) ?? '');
    $internalSku = trim((string) ($p['matched_internal_sku'] ?? ''));
    $internalNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $internalSku) ?? '');
    $variants = array_values(array_unique(array_filter([
        $sku,
        $codeNorm,
        ltrim($codeNorm, '0'),
        $internalSku,
        $internalNorm,
        ltrim($internalNorm, '0'),
    ])));

    $matchedCodes = is_array($p['matched_codes'] ?? null) ? $p['matched_codes'] : [];
    foreach ($matchedCodes as $mc) {
        $mc = trim((string) $mc);
        if ($mc === '') {
            continue;
        }
        $mcNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $mc) ?? '');
        $variants[] = $mc;
        if ($mcNorm !== '') {
            $variants[] = $mcNorm;
            $trimmed = ltrim($mcNorm, '0');
            if ($trimmed !== '' && $trimmed !== $mcNorm) {
                $variants[] = $trimmed;
            }
        }
    }

    return array_values(array_unique(array_filter($variants)));
}

foreach ($products as $p) {
    if (!is_array($p)) {
        continue;
    }
    $status = (string) ($p['status'] ?? '');
    if (!isset($allowed[$status])) {
        continue;
    }

    $sku = trim((string) ($p['sku_supplier'] ?? ''));
    $csvBrand = trim((string) ($p['brand'] ?? ''));
    $tecdocBrand = trim((string) ($p['matched_brand'] ?? ''));
    $productId = (int) ($p['matched_product_id'] ?? 0);

    if ($productId <= 0 && $tecdocBrand === '' && $sku === '') {
        $skipped[] = ['sku' => $sku, 'reason' => 'Lipsă match TecDoc (fără product_id)'];
        continue;
    }

    $sourceFile = (string) ($p['source_file'] ?? '');
    $supplierKey = strtolower(explode('/', $sourceFile)[0] ?? 'generic');
    if (!import_motor_supplier_is_allowed($supplierKey)) {
        $skipped[] = ['sku' => (string) ($p['sku_supplier'] ?? ''), 'reason' => 'Furnizor neînregistrat/blocat în modulul Furnizori'];
        continue;
    }

    $supplierType = import_motor_supplier_code_from_slug($supplierKey);
    if ($supplierType === '') {
        $supplierType = match ($supplierKey) {
            'elit' => 'ELIT',
            'autonet' => 'AUTONET',
            'autopartner' => 'AUTOPARTNER',
            'autototal' => 'AUTOTOTAL',
            'materom' => 'MATEROM',
            'intercars' => 'INTERCARS',
            default => strtoupper($supplierKey),
        };
    }

    // Brand TecDoc din match are prioritate; BrandCatalogResolver doar când lipsește matched_brand.
    $canonicalBrand = $tecdocBrand !== '' ? $tecdocBrand : $csvBrand;
    if ($canonicalBrand === '' && $csvBrand !== '') {
        $catalog = $brands->resolve($csvBrand);
        if ($catalog !== null) {
            $canonicalBrand = $catalog['brand'];
        }
    }

    $variants = import_tecdoc_code_variants($p);
    $entries = null;

    // 1) Prioritate: product_id din matching Python (sursa de adevăr TecDoc)
    if ($productId > 0 && CoreDbLookup::isAvailable()) {
        $entries = CoreDbLookup::findEntriesByProductId($productId);
        if ($entries !== null && $entries !== []) {
            $entryBrand = trim((string) ($entries[0]['ART_BRAND'] ?? ''));
            if ($entryBrand !== '') {
                $canonicalBrand = $entryBrand;
            }
        }
    }

    // 2) Fallback: brand TecDoc + variante cod
    if (($entries === null || $entries === []) && $canonicalBrand !== '' && $variants !== []) {
        $entries = BaseIndexLookup::findEntries($canonicalBrand, $variants);
    }

    if ($entries === null || $entries === []) {
        $skipped[] = ['sku' => $sku, 'reason' => 'Produs negăsit în besoiu_tecdoc_base pentru enrichment'];
        continue;
    }

    $entryBrand = trim((string) ($entries[0]['ART_BRAND'] ?? ''));
    if ($entryBrand !== '') {
        $canonicalBrand = $entryBrand;
    }

    $code1 = (string) ($entries[0]['ART_CODE_1'] ?? $sku);
    $priceNet = isset($p['price_purchase_net']) && is_numeric($p['price_purchase_net'])
        ? (float) $p['price_purchase_net']
        : (import_motor_apply_feed_price($p['price'] ?? null, $supplierKey)
            ?? (isset($p['price']) && is_numeric($p['price']) ? (float) $p['price'] : 0.0));

    $card = $matcher->buildTecdocImportCard(
        $canonicalBrand,
        $code1,
        $entries,
        $priceNet,
        $supplierType
    );

    if ($card === null) {
        $card = $matcher->buildEnrichedSupplierCard(
            $canonicalBrand,
            $code1,
            $entries,
            $priceNet,
            $supplierType,
            true
        );
    }

    if ($card === null) {
        require_once __DIR__ . '/lib/ImportTecdocMysqlCardBuilder.php';
        $card = ImportTecdocMysqlCardBuilder::fromEntries(
            $entries,
            $canonicalBrand,
            $code1,
            $priceNet,
            $supplierType,
            $p
        );
    }

    if ($card === null) {
        $skipped[] = ['sku' => $sku, 'reason' => 'Produs negăsit / incomplet în TecDoc MySQL'];
        continue;
    }

    if (function_exists('import_attach_local_image_to_card')) {
        $card = import_attach_local_image_to_card($card);
    }

    $card = import_normalize_card_image_fields($card);

    $card['matchStatus'] = $status;
    $card['matchMethod'] = (string) ($p['match_method'] ?? '');
    $card['matchedName'] = (string) ($p['matched_name'] ?? '');
    $card['matchedTecdocSku'] = (string) ($p['matched_internal_sku'] ?? '');
    $card['sourceFile'] = $sourceFile;
    $card['sourceSupplier'] = $supplierKey;
    $card['sku'] = $sku !== '' ? $sku : $card['sku'];
    $card['cardBuild'] = 'mysql_besoiu_tecdoc_base';
    if (!empty($card['parameters']) || (int) ($card['compatCount'] ?? 0) > 0
        || mb_strlen((string) ($card['description'] ?? '')) > 40) {
        $card['tecdocDataComplete'] = true;
    }
    $card['tecdocAudit'] = import_build_tecdoc_audit($p, [
        'matchStatus' => $status,
        'matchMethod' => $card['matchMethod'],
        'matchedName' => $card['matchedName'],
        'matchedTecdocSku' => $card['matchedTecdocSku'],
        'ttcArtId' => (string) ($card['ttcArtId'] ?? ''),
        'cardBuild' => 'mysql_besoiu_tecdoc_base',
        'titleFrom' => 'ART_NAME + product_compatibilities (TecDoc)',
        'compatCount' => (int) ($card['compatCount'] ?? 0),
        'imageFrom' => (string) ($card['imageSource'] ?? 'Poze/Autopartner'),
        'supplierSku' => $sku,
        'supplierBrand' => $csvBrand,
        'supplierName' => (string) ($p['name'] ?? ''),
    ]);
    if (isset($p['price_csv']) && is_numeric($p['price_csv'])) {
        $card['priceCsv'] = round((float) $p['price_csv'], 2);
    }
    $cards[] = import_motor_normalize_card_purchase_prices($card);
}

import_json_response([
    'success' => true,
    'count' => count($cards),
    'skipped' => count($skipped),
    'skippedDetails' => array_slice($skipped, 0, 20),
    'cards' => $cards,
]);
