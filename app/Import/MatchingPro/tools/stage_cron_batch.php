<?php

declare(strict_types=1);

/**
 * CLI: stage produse din raport cron în coada import (batch).
 * php app/Import/MatchingPro/tools/stage_cron_batch.php --report=REPORT_ID
 */

$root = dirname(__DIR__, 4);

if (!defined('BESOIU_ROOT')) {
    define('BESOIU_ROOT', $root);
}

require_once $root . '/app/Import/bootstrap.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/lib/ImportCardStaging.php';
require_once dirname(__DIR__) . '/api/lib/CronStagingJournal.php';
require_once dirname(__DIR__) . '/api/lib/ImportFastCardPipeline.php';
require_once dirname(__DIR__) . '/api/lib/ImportTecdocMysqlEnrichment.php';
require_once dirname(__DIR__) . '/api/lib/ImportShowcaseConfig.php';
require_once dirname(__DIR__) . '/api/lib/ImportShowcaseTypeMatcher.php';

@ini_set('memory_limit', '768M');
@set_time_limit(600);

$reportId = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--report=')) {
        $reportId = trim(substr($arg, 9));
    }
}

if ($reportId === '') {
    fwrite(STDERR, "Lipsește --report=ID\n");
    exit(1);
}

$reportPath = IMPORT_REPORTS . DIRECTORY_SEPARATOR . $reportId . '.json';
if (!is_file($reportPath)) {
    fwrite(STDERR, "Raport inexistent: {$reportPath}\n");
    exit(1);
}

$payload = json_decode((string) file_get_contents($reportPath), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Raport JSON invalid\n");
    exit(1);
}

$products = is_array($payload['products'] ?? null) ? $payload['products'] : [];
if ($products === []) {
    echo json_encode(['success' => true, 'queued' => 0, 'stats' => CronStagingJournal::empty()['stats']], JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

import_motor_boot_furnizori_libs();

if (!import_motor_ensure_database()) {
    fwrite(STDERR, "Baza de date indisponibilă — verifică .env / MySQL\n");
    exit(2);
}

if (!function_exists('import_stage_products_for_review')) {
    \Besoiu\Services\Import\ImportLibLoader::bootFull(skipHttp: true);
}

if (!function_exists('import_stage_products_for_review')) {
    fwrite(STDERR, "Funcția import_stage_products_for_review lipsește după boot\n");
    exit(3);
}

$pdo = \Config\Database::getDB();
$markup = new \Besoiu\Services\AdaosComercial\AdaosComercialService();
// Loturi mai mici la staging HTTP/CLI — fiecare produs trece prin insert + formare card.
$batchSize = min(import_cron_batch_size(), 100);
$meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
$stagedOffset = max(0, (int) ($meta['staged_count'] ?? 0));
$remaining = array_slice($products, $stagedOffset);
$cardStagingOpts = ['use_ollama' => false];

if ($remaining === []) {
    echo json_encode(['success' => true, 'queued' => 0, 'staged_count' => $stagedOffset, 'complete' => true], JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

$configPath = IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'showcase_types.json';
$showcaseTypes = ImportShowcaseConfig::scanTypes();
$showcaseMinScore = (int) (ImportShowcaseConfig::load()['match_min_score'] ?? 72);

/** @var list<string> */
$matchStatuses = ['exact', 'probable', 'conflict'];

$runStats = [
    'scanned' => 0,
    'matched' => 0,
    'skipped_no_match' => 0,
    'showcase_candidates' => 0,
    'standard_candidates' => 0,
    'queued' => 0,
    'skipped_no_image' => 0,
    'skipped_incomplete' => 0,
    'with_image' => 0,
    'price_stock_only' => 0,
    'tecdoc_rematch' => 0,
];

if (function_exists('import_ensure_tecdoc_matched_columns')) {
    import_ensure_tecdoc_matched_columns($pdo);
}

/** @var list<array<string, mixed>> */
$journalItems = [];

import_motor_append_log(
    'Migrare coadă import — ' . count($remaining) . ' produs(e) de procesat din raport ' . $reportId,
    'INFO'
);

$totalQueued = 0;
$processedInRun = 0;
$chunkIndex = 0;

while ($processedInRun < count($remaining)) {
    $chunkIndex++;
    $slice = array_slice($remaining, $processedInRun, $batchSize);
    if ($slice === []) {
        break;
    }

    $standard = [];
    $showcase = [];
    /** @var list<array<string, mixed>> $matchedChunk */
    $matchedChunk = [];

    foreach ($slice as $product) {
        if (!is_array($product)) {
            continue;
        }
        ++$runStats['scanned'];

        $status = (string) ($product['status'] ?? '');
        $sku = trim((string) ($product['sku_supplier'] ?? ''));
        $supplier = (string) ($product['supplier'] ?? $payload['supplier'] ?? '');
        $displayName = trim((string) ($product['matched_name'] ?? $product['name'] ?? $sku));

        if (!in_array($status, $matchStatuses, true)) {
            ++$runStats['skipped_no_match'];
            $journalItems[] = [
                'sku' => $sku,
                'name' => $displayName,
                'supplier' => $supplier,
                'match' => $status !== '' ? $status : 'no_match',
                'lane' => '—',
                'image' => '—',
                'action' => 'skipped',
                'reason' => 'Fără match TecDoc',
                'report_id' => $reportId,
            ];
            import_motor_append_log(
                "  ✗ [{$supplier}] " . ($displayName !== '' ? $displayName : $sku)
                . ' (cod ' . ($sku !== '' ? $sku : '—') . ') — FĂRĂ MATCH în TecDoc, nu e trimis în coadă',
                'WARN'
            );
            continue;
        }

        ++$runStats['matched'];
        // Raportul cron poate omite supplier/source_file pe produs — aliniază cheile
        // cu ImportFastCardPipeline::cardResultKey (sku|supplier|source_file).
        if ($supplier !== '' && trim((string) ($product['supplier'] ?? '')) === '') {
            $product['supplier'] = $supplier;
        }
        $reportFile = trim((string) ($payload['filename'] ?? $payload['file'] ?? ''));
        $sourceFile = trim((string) ($product['source_file'] ?? ''));
        if ($sourceFile === '') {
            $sourceFile = $reportFile;
        }
        if ($sourceFile !== '' && !str_contains($sourceFile, '/') && $supplier !== '') {
            $sourceFile = strtolower($supplier) . '/' . $sourceFile;
        } elseif ($sourceFile === '' && $supplier !== '') {
            $sourceFile = strtolower($supplier) . '/';
        }
        $product['source_file'] = $sourceFile;
        $matchedChunk[] = $product;
    }

    $cardsByKey = [];
    $needsEnrich = [];
    foreach ($matchedChunk as $product) {
        if (function_exists('import_cron_should_skip_tecdoc_rematch')
            && import_cron_should_skip_tecdoc_rematch($pdo, $product)
        ) {
            $code = trim((string) ($product['sku_supplier'] ?? $product['art_nr'] ?? ''));
            $brand = trim((string) ($product['matched_brand'] ?? $product['force_brand'] ?? $product['brand'] ?? ''));
            $existing = function_exists('import_lookup_tecdoc_matched_record')
                ? import_lookup_tecdoc_matched_record($pdo, $code, $brand)
                : null;
            $updated = $existing !== null
                && function_exists('import_cron_update_price_stock_only')
                && import_cron_update_price_stock_only($pdo, $product, $existing);
            ++$runStats['price_stock_only'];
            $sku = $code;
            $supplier = (string) ($product['supplier'] ?? $payload['supplier'] ?? '');
            $displayName = trim((string) ($product['matched_name'] ?? $product['name'] ?? $sku));
            $journalItems[] = [
                'sku' => $sku,
                'name' => $displayName,
                'supplier' => $supplier,
                'match' => (string) ($product['status'] ?? ''),
                'lane' => (string) ($existing['table'] ?? '—'),
                'image' => '—',
                'action' => 'price_stock_only',
                'reason' => $updated
                    ? 'Deja matchuit TecDoc — actualizat doar preț/stoc (fără rematch)'
                    : 'Deja matchuit TecDoc — fără preț/stoc nou de actualizat',
                'report_id' => $reportId,
            ];
            import_motor_append_log(
                "  ↻ [{$supplier}] {$displayName} (cod {$sku}) — deja matchuit TecDoc → doar preț/stoc"
                . ($updated ? ' (updated)' : ' (no price/stock change)'),
                'INFO'
            );
            continue;
        }
        $needsEnrich[] = $product;
    }

    if ($needsEnrich !== []) {
        $runStats['tecdoc_rematch'] += count($needsEnrich);
        $enriched = ImportFastCardPipeline::enrichProductsToCards($needsEnrich, false);
        foreach ($enriched['cards'] as $builtCard) {
            if (!is_array($builtCard) || !ImportTecdocMysqlEnrichment::cardHasTecdocBody($builtCard)) {
                continue;
            }
            $cardsByKey[ImportTecdocMysqlEnrichment::cardResultKey($builtCard)] = $builtCard;
        }
    }

    foreach ($needsEnrich as $product) {
        $status = (string) ($product['status'] ?? '');
        $sku = trim((string) ($product['sku_supplier'] ?? ''));
        $supplier = (string) ($product['supplier'] ?? $payload['supplier'] ?? '');
        $displayName = trim((string) ($product['matched_name'] ?? $product['name'] ?? $sku));
        $lookupKey = ImportTecdocMysqlEnrichment::cardLookupKey($product);
        $card = $cardsByKey[$lookupKey] ?? null;

        if ($card === null) {
            ++$runStats['skipped_incomplete'];
            $journalItems[] = [
                'sku' => $sku,
                'name' => $displayName,
                'supplier' => $supplier,
                'match' => $status,
                'lane' => '—',
                'image' => '—',
                'action' => 'skipped',
                'reason' => 'Negăsit / incomplet în TecDoc MySQL',
                'report_id' => $reportId,
            ];
            import_motor_append_log(
                "  ⚠ [{$supplier}] {$displayName} (cod {$sku}) — match scan OK dar fără date complete în TecDoc MySQL, omis",
                'WARN'
            );
            continue;
        }

        $card['matchStatus'] = $status;
        $card['matchMethod'] = (string) ($product['match_method'] ?? $card['matchMethod'] ?? '');
        $card['matchedName'] = (string) ($product['matched_name'] ?? $card['matchedName'] ?? '');
        $card['matchedTecdocSku'] = (string) ($product['matched_internal_sku'] ?? $card['matchedTecdocSku'] ?? '');
        if (isset($product['price_csv']) && is_numeric($product['price_csv'])) {
            $card['priceCsv'] = round((float) $product['price_csv'], 2);
        }

        $showcaseMatch = ImportShowcaseTypeMatcher::matchKeyword($card, $showcaseTypes, $showcaseMinScore, true);
        if ($showcaseMatch !== null) {
            $card['showcaseType'] = $showcaseMatch['type'];
            $converted = ImportCardStaging::cardToProduct($card, 'showcase', $cardStagingOpts);
            if ($converted === null) {
                ++$runStats['skipped_incomplete'];
                $journalItems[] = [
                    'sku' => $sku,
                    'name' => $displayName,
                    'supplier' => $supplier,
                    'match' => $status,
                    'lane' => 'showcase',
                    'image' => '—',
                    'action' => 'skipped',
                    'reason' => 'Card vitrină incomplet',
                    'report_id' => $reportId,
                ];
                import_motor_append_log(
                    "  ⚠ [{$supplier}] {$displayName} (cod {$sku}) — TecDoc OK dar card vitrină incomplet, sărit",
                    'WARN'
                );
                continue;
            }
            ++$runStats['showcase_candidates'];
            $showcase[] = $converted;
            $hasImg = !empty($card['hasImage']) || !empty($card['imageDisplayUrl']);
            $journalItems[] = [
                'sku' => $sku,
                'name' => $displayName,
                'supplier' => $supplier,
                'match' => $status,
                'lane' => $hasImg ? 'showcase' : 'no_image',
                'image' => $hasImg ? 'da' : 'nu',
                'has_image' => $hasImg,
                'action' => 'candidate',
                'reason' => $hasImg
                    ? ('TecDoc MySQL → vitrină (' . (string) ($showcaseMatch['type'] ?? '') . ')')
                    : 'TecDoc MySQL (vitrină) fără imagine → coadă „Produse fără imagine”',
                'report_id' => $reportId,
            ];
            import_motor_append_log(
                "  ✓ [{$supplier}] {$displayName} (cod {$sku}) — TecDoc MySQL → vitrină ({$showcaseMatch['type']}) · "
                . ($hasImg ? 'CU imagine' : 'FĂRĂ imagine → coadă fără imagine'),
                'OK'
            );
        } else {
            $converted = ImportCardStaging::cardToProduct($card, 'standard', $cardStagingOpts);
            if ($converted === null) {
                ++$runStats['skipped_incomplete'];
                $journalItems[] = [
                    'sku' => $sku,
                    'name' => $displayName,
                    'supplier' => $supplier,
                    'match' => $status,
                    'lane' => 'standard',
                    'image' => '—',
                    'action' => 'skipped',
                    'reason' => 'Card incomplet (nume/cod)',
                    'report_id' => $reportId,
                ];
                import_motor_append_log(
                    "  ⚠ [{$supplier}] {$displayName} (cod {$sku}) — TecDoc OK dar card incomplet, sărit",
                    'WARN'
                );
                continue;
            }
            ++$runStats['standard_candidates'];
            $standard[] = $converted;
            $hasImg = !empty($card['hasImage']) || !empty($card['imageDisplayUrl']);
            $journalItems[] = [
                'sku' => $sku,
                'name' => $displayName,
                'supplier' => $supplier,
                'match' => $status,
                'lane' => $hasImg ? 'standard' : 'no_image',
                'image' => $hasImg ? 'da' : 'nu',
                'has_image' => $hasImg,
                'action' => 'candidate',
                'reason' => $hasImg
                    ? 'TecDoc MySQL → import standard'
                    : 'TecDoc MySQL fără imagine → coadă „Produse fără imagine”',
                'report_id' => $reportId,
            ];
            import_motor_append_log(
                "  ✓ [{$supplier}] {$displayName} (cod {$sku}) — TecDoc MySQL → standard · "
                . ($hasImg ? 'CU imagine' : 'FĂRĂ imagine → coadă fără imagine'),
                'OK'
            );
        }
    }

    $stageOpts = [
        'cron_sync' => true,
        'require_image' => true,
        'epiesa_special_products' => false,
        'ollama_normalize' => false,
        // Cardurile vin deja din ImportFastCardPipeline + TecDoc MySQL.
        'skip_tecdoc_enrich' => true,
        // Imaginea locală (Poze/Autopartner) e deja atașată pe card.
        // Fără skip, import_find_image_for_product blochează cronul minute/produs.
        // Produsele fără poză merg în coada no_image; worker-ul de imagini le completează ulterior.
        'skip_image_fetch' => true,
        'matching_pro_fast' => true,
    ];

    if ($standard !== []) {
        $attemptedStandard = count($standard);
        $s = import_stage_products_for_review($pdo, $standard, $markup, array_merge($stageOpts, [
            'import_lane' => 'standard',
        ]));
        $q = (int) ($s['queued'] ?? 0);
        $totalQueued += $q;
        $runStats['queued'] += $q;
        $runStats['with_image'] += (int) ($s['with_image'] ?? 0);
        $skippedImg = (int) ($s['skipped_no_image'] ?? 0);
        $runStats['skipped_no_image'] += $skippedImg;
        $journalItems[] = [
            'sku' => '—',
            'name' => "Standard: {$attemptedStandard} candidat(e) → {$q} în coadă import",
            'supplier' => (string) ($payload['supplier'] ?? ''),
            'match' => '—',
            'lane' => 'standard',
            'image' => $skippedImg > 0 ? (string) $skippedImg . ' fără imagine' : 'OK',
            'action' => $q > 0 ? 'queued' : 'skipped',
            'reason' => $skippedImg > 0
                ? "{$skippedImg} mutate în coada „Produse fără imagine” (import_lane=no_image)"
                : ($q > 0 ? 'Migrate în import_produse' : 'Niciun produs de migrat'),
            'report_id' => $reportId,
        ];
    }

    if ($showcase !== []) {
        $attemptedShowcase = count($showcase);
        $s = import_stage_products_for_review($pdo, array_values(array_filter($showcase)), $markup, array_merge($stageOpts, [
            'import_lane' => 'showcase',
            'epiesa_special_products' => true,
        ]));
        $q = (int) ($s['queued'] ?? 0);
        $totalQueued += $q;
        $runStats['queued'] += $q;
        $runStats['with_image'] += (int) ($s['with_image'] ?? 0);
        $skippedImg = (int) ($s['skipped_no_image'] ?? 0);
        $runStats['skipped_no_image'] += $skippedImg;
        $journalItems[] = [
            'sku' => '—',
            'name' => "Vitrină: {$attemptedShowcase} candidat(e) → {$q} în coadă import",
            'supplier' => (string) ($payload['supplier'] ?? ''),
            'match' => '—',
            'lane' => 'showcase',
            'image' => $skippedImg > 0 ? (string) $skippedImg . ' fără imagine' : 'OK',
            'action' => $q > 0 ? 'queued' : 'skipped',
            'reason' => $skippedImg > 0
                ? "{$skippedImg} mutate în coada „Produse fără imagine” (import_lane=no_image)"
                : ($q > 0 ? 'Migrate în import_produse (vitrină)' : 'Niciun produs de migrat'),
            'report_id' => $reportId,
        ];
    }

    $processedInRun += count($slice);

    if ($chunkIndex >= 20 && $processedInRun < count($remaining)) {
        break;
    }
}

CronStagingJournal::append($reportId, $runStats, $journalItems);

$newStagedCount = $stagedOffset + $processedInRun;
$meta['staged_count'] = $newStagedCount;
$meta['staged_at'] = date('c');
$meta['staging_stats'] = $runStats;
$payload['meta'] = $meta;
file_put_contents(
    $reportPath,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
    LOCK_EX
);

$complete = $newStagedCount >= count($products);

echo json_encode([
    'success' => true,
    'queued' => $totalQueued,
    'staged_count' => $newStagedCount,
    'total_products' => count($products),
    'complete' => $complete,
    'chunks' => $chunkIndex,
    'stats' => $runStats,
    'items' => array_slice($journalItems, 0, 80),
], JSON_UNESCAPED_UNICODE) . "\n";
