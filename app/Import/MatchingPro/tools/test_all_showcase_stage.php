<?php
declare(strict_types=1);

/**
 * Test conversie carduri vitrină → produs coadă (fără DB insert).
 * Usage: php tools/test_all_showcase_stage.php
 */
$root = dirname(__DIR__);
require_once $root . '/api/bootstrap.php';
require_once $root . '/api/lib/ImportShowcaseScanner.php';
require_once $root . '/api/lib/ImportShowcaseConfig.php';
require_once $root . '/api/lib/ImportCardStaging.php';

$types = ImportShowcaseConfig::scanTypes();
$minScore = (int) (ImportShowcaseConfig::load()['match_min_score'] ?? 72);
$feedBase = import_motor_canonical_feed_base_dir();

$files = [
    ['materom', 'Lista pret Materom 16.01.2026.csv'],
    ['elit', 'Lista pret Elit 16.01.2026.csv'],
    ['autototal', 'Lista pret Autototal 16.01.2026.csv'],
    ['autonet', 'Lista pret Autonet 27.01.2026.csv'],
    ['autopartner', 'Lista pret Autopartner 16.01.2026.csv'],
];

$failed = 0;
foreach ($files as [$supplier, $filename]) {
    $path = $feedBase . DIRECTORY_SEPARATOR . $supplier . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        echo "SKIP missing {$supplier}/{$filename}\n";
        continue;
    }

    [$cards] = ImportShowcaseScanner::scanFile($path, $filename, $types, $minScore, 5, 80000, false);
    $converted = ImportCardStaging::cardsToProducts($cards, 'showcase', [
        'use_ollama' => false,
        'skip_category_match' => true,
    ]);

    $ok = count($cards) > 0 && count($converted) === count($cards);
    echo ($ok ? 'OK  ' : 'FAIL') . " | {$supplier}/{$filename} | cards=" . count($cards)
        . ' converted=' . count($converted) . PHP_EOL;

    if (!$ok) {
        $failed++;
        foreach ($cards as $i => $card) {
            $prod = ImportCardStaging::cardToProduct($card, 'showcase', ['skip_category_match' => true]);
            if ($prod === null) {
                echo '    card #' . $i . ' FAIL: sku=' . ($card['sku'] ?? '')
                    . ' title=' . mb_substr((string) ($card['title'] ?? ''), 0, 50) . PHP_EOL;
            }
        }
    }
}

// QWP must skip
$qwp = $feedBase . DIRECTORY_SEPARATOR . 'autonet' . DIRECTORY_SEPARATOR . 'Autonet QWP.csv';
if (is_file($qwp)) {
    [, $report] = ImportShowcaseScanner::scanFile($qwp, 'Autonet QWP.csv', $types, $minScore, 5, 1000, false);
    $skipped = ($report['status'] ?? '') === 'skipped';
    echo ($skipped ? 'OK  ' : 'FAIL') . ' | autonet/QWP skipped=' . ($report['status'] ?? '') . PHP_EOL;
    if (!$skipped) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? 'STAGE CONVERT OK' : "STAGE FAIL: {$failed}") . PHP_EOL;
exit($failed > 0 ? 1 : 0);
