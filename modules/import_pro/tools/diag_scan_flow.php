<?php
declare(strict_types=1);

/**
 * Simulează fluxul JS: build-stored paginat + filtru isCompleteTecdocCard (aprox PHP).
 * php modules/import_pro/tools/diag_scan_flow.php elit "Lista pret Elit 16.01.2026.csv" 20
 */
$root = dirname(__DIR__, 3);
require $root . '/app/Import/bootstrap.php';
require $root . '/app/Import/MatchingPro/api/bootstrap.php';
require_once import_motor_product_matcher_path();
import_require_fetch_product_lib('SupplierEnrichedCardBuilder.php');

$supplier = $argv[1] ?? 'elit';
$filename = $argv[2] ?? 'Lista pret Elit 16.01.2026.csv';
$limit = (int) ($argv[3] ?? 20);
$path = import_resolve_stored_file($supplier, $filename);

function php_is_complete_card(array $card): bool
{
    if (!empty($card['isPreview'])) {
        return false;
    }
    $cardBuild = (string) ($card['cardBuild'] ?? '');
    if ($cardBuild !== '' && $cardBuild !== 'mysql_besoiu_tecdoc_base') {
        return false;
    }
    $hasImg = !empty($card['hasImage']) || !empty($card['scrapedImageUrl']) || !empty($card['scrapedImagePath']);
    if (!$hasImg) {
        return false;
    }
    $title = trim((string) ($card['title'] ?? ''));
    if (strlen($title) < 8) {
        return false;
    }
    $params = is_array($card['parameters'] ?? null) ? $card['parameters'] : [];
    $desc = (string) ($card['description'] ?? '');
    $compat = (int) ($card['compatCount'] ?? 0);
    return count($params) > 0 || strlen($desc) > 40 || $compat > 0;
}

function simulate_build_page(string $path, string $supplier, string $filename, int $limit, int $offset, bool $onlyWithImage): array
{
    $headerHandle = fopen($path, 'rb');
    $header = $headerHandle !== false ? fgetcsv($headerHandle, 0, ';') : false;
    if ($headerHandle) {
        fclose($headerHandle);
    }
    $buildLimit = $onlyWithImage ? min(200, max(($offset + $limit) * 10, 80)) : ($offset + $limit);
    $builder = new SupplierEnrichedCardBuilder();
    [$cards, $stats] = $builder->buildCardsFromFile($path, $buildLimit, $onlyWithImage);
    $cards = import_filter_verified_image_cards($cards);
    $totalBuilt = count($cards);
    if ($offset > 0) {
        $cards = array_slice($cards, $offset);
    }
    $pageCards = array_slice($cards, 0, $limit);
    $pageCount = count($pageCards);
    $hasMore = import_build_stored_page_has_more(
        $path,
        $header,
        $filename,
        $offset,
        $limit,
        $pageCount,
        $totalBuilt,
        $buildLimit,
        $onlyWithImage
    );

    return [
        'pageCards' => $pageCards,
        'hasMore' => $hasMore,
        'nextOffset' => $offset + count($pageCards),
        'totalBuilt' => $totalBuilt,
        'rowsScanned' => $stats['rowsScanned'] ?? 0,
        'buildLimit' => $buildLimit,
    ];
}

echo "=== SIMULARE build-stored paginat: {$supplier}/{$filename} limit={$limit} ===\n\n";

$offset = 0;
$allCards = [];
$page = 0;
$lastHasMore = false;
while ($page < 8) {
    $res = simulate_build_page($path, $supplier, $filename, $limit, $offset, false);
    $complete = array_values(array_filter($res['pageCards'], 'php_is_complete_card'));
    echo "Page {$page}: offset={$offset} raw_page=" . count($res['pageCards'])
        . " complete=" . count($complete)
        . " hasMore=" . ($res['hasMore'] ? 'YES' : 'NO')
        . " totalBuilt={$res['totalBuilt']} rowsScanned={$res['rowsScanned']} buildLimit={$res['buildLimit']}\n";
    foreach ($complete as $c) {
        $key = ($c['sku'] ?? '') . '|' . ($c['sourceSupplier'] ?? $supplier);
        $allCards[$key] = $c;
    }
    $lastHasMore = $res['hasMore'];
    if (!$res['hasMore'] || count($res['pageCards']) === 0) {
        break;
    }
    $offset = $res['nextOffset'];
    $page++;
    if (count($allCards) >= $limit) {
        echo "→ Target {$limit} atins la " . count($allCards) . " carduri complete\n";
        break;
    }
}

echo "\nTotal carduri complete acumulate: " . count($allCards) . " / limit={$limit}\n";
if ($lastHasMore && count($allCards) < $limit) {
    echo "BUG: hasMore=true dar scroll-ul nu mai aduce carduri noi!\n";
}

// Matching sample
$args = ['scan', '--file', $path, '--supplier', $supplier, '--no-archive', '--force', '--sample', (string) $limit];
$result = import_run_python($args, 540);
if ($result['json']) {
    $items = $result['json'];
    $last = $items[array_key_last($items)] ?? null;
    $payload = is_array($last) ? ($last['payload'] ?? $last) : null;
    if (is_array($payload)) {
        $exact = array_filter($payload['products'] ?? [], static fn ($p) => in_array($p['status'] ?? '', ['exact', 'probable', 'conflict'], true));
        echo "\nMatching sample={$limit}: " . count($exact) . " cu status TecDoc (exact/probable/conflict)\n";
        echo "Summary: " . json_encode($payload['summary'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        echo "BUG potențial: cu alsoMatch=checked (default), max carduri din matching ≈ " . count($exact)
            . ", dar build-stored poate avea " . count($allCards) . " — pipeline-urile diverg!\n";
    }
}
