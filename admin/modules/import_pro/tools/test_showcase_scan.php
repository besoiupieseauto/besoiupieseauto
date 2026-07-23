<?php
declare(strict_types=1);

/**
 * Test scan vitrină — 5 produse, tipuri ulei/lichid/adeziv/baterie.
 * php modules/import_pro/tools/test_showcase_scan.php [limit]
 */
$root = dirname(__DIR__, 3);
define('BESOIU_ROOT', $root);

require_once $root . '/app/Import/bootstrap.php';
require_once $root . '/app/Import/MatchingPro/api/bootstrap.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportCardStaging.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportShowcaseScanner.php';

$limit = max(1, min(20, (int) ($argv[1] ?? 5)));
$types = ['ulei', 'lichid', 'adeziv', 'baterie'];
$minScore = 72;

echo "=== TEST SHOWCASE SCAN (lightweight v2) ===\n";
echo "Types: " . implode(', ', $types) . " | minScore={$minScore} | limit={$limit}\n\n";

$feedBase = $root . '/admin/storage/supplier_feeds';
$testFiles = [];
foreach (glob($feedBase . '/*/*.csv') ?: [] as $path) {
    $testFiles[] = [
        'supplier' => basename(dirname($path)),
        'filename' => basename($path),
        'path' => $path,
    ];
}

if ($testFiles === []) {
    fwrite(STDERR, "ERROR: Niciun CSV în supplier_feeds\n");
    exit(1);
}

$allMatched = [];
$typeHits = array_fill_keys($types, 0);
$startedAt = microtime(true);

foreach ($testFiles as $file) {
    if (count($allMatched) >= $limit) {
        break;
    }
    $remaining = $limit - count($allMatched);
    echo "▶ {$file['supplier']}/{$file['filename']} (caut {$remaining})…\n";

    $t0 = microtime(true);
    try {
        [$cards, $report] = ImportShowcaseScanner::scanFile(
            $file['path'],
            $file['filename'],
            $types,
            $minScore,
            $remaining
        );
    } catch (Throwable $e) {
        echo "  ✗ EROARE: " . $e->getMessage() . "\n";
        continue;
    }
    $dur = round(microtime(true) - $t0, 2);

    echo "  mode=" . ($report['scan_mode'] ?? '?')
        . " rows=" . ($report['rows_scanned'] ?? 0)
        . " matched=" . count($cards)
        . " dur={$dur}s\n";
    echo "  " . ($report['message'] ?? '') . "\n";

    foreach ($cards as $card) {
        $card['sourceFile'] = $file['supplier'] . '/' . $file['filename'];
        $card['sourceSupplier'] = $file['supplier'];
        try {
            $normalized = import_motor_normalize_card_purchase_prices(
                import_normalize_card_image_fields($card)
            );
            $type = (string) ($normalized['showcaseType'] ?? '');
            if (isset($typeHits[$type])) {
                $typeHits[$type]++;
            }
            $allMatched[] = $normalized;
        } catch (Throwable $e) {
            echo "  ! normalize fail: " . $e->getMessage() . "\n";
        }
        if (count($allMatched) >= $limit) {
            break;
        }
    }
}

$totalDur = round(microtime(true) - $startedAt, 2);
echo "\n=== REZULTATE ({$totalDur}s total) ===\n";
echo "Produse găsite: " . count($allMatched) . " / {$limit}\n";
echo "Pe tip: " . json_encode($typeHits, JSON_UNESCAPED_UNICODE) . "\n\n";

foreach ($allMatched as $i => $c) {
    echo '[' . ($i + 1) . '] '
        . ($c['showcaseType'] ?? '?') . ' ' . ($c['showcaseScore'] ?? '?') . '%'
        . ' | ' . ($c['sourceSupplier'] ?? '')
        . ' | ' . mb_substr((string) ($c['title'] ?? ''), 0, 55) . "\n";
}

$uniqueTypes = count(array_filter($typeHits));
$passed = count($allMatched) >= min(3, $limit);
echo "\nVerificări:\n";
echo ($passed ? '[OK]' : '[FAIL]') . " Minim 3 produse găsite: " . count($allMatched) . "\n";
echo ($uniqueTypes >= 1 ? '[OK]' : '[FAIL]') . " Matching tip funcțional (tipuri distincte: {$uniqueTypes})\n";
echo ($totalDur < 120 ? '[OK]' : '[WARN]') . " Durată totală: {$totalDur}s (țintă < 120s)\n";

exit($passed ? 0 : 2);
