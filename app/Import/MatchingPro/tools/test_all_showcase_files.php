<?php
declare(strict_types=1);

/**
 * Test scan vitrină pe toate fișierele CSV furnizor.
 * Usage: php tools/test_all_showcase_files.php [--limit=10] [--no-ollama]
 */
$root = dirname(__DIR__);
require_once $root . '/api/bootstrap.php';
require_once $root . '/api/lib/ImportShowcaseScanner.php';
require_once $root . '/api/lib/ImportShowcaseConfig.php';

$limit = 10;
$useOllama = true;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(50, (int) substr($arg, 8)));
    }
    if ($arg === '--no-ollama') {
        $useOllama = false;
    }
}

$types = ImportShowcaseConfig::scanTypes();
if ($types === []) {
    $types = ['ulei', 'lichid', 'baterie', 'adeziv'];
}
$minScore = (int) (ImportShowcaseConfig::load()['match_min_score'] ?? 72);

$feedBase = import_motor_canonical_feed_base_dir();
if (!is_dir($feedBase)) {
    fwrite(STDERR, "Feed dir missing: {$feedBase}\n");
    exit(1);
}

/** @var list<array{supplier:string,filename:string,path:string}> $files */
$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($feedBase, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.csv')) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($feedBase) + 1));
    $parts = explode('/', $rel, 2);
    if (count($parts) < 2) {
        continue;
    }
    $files[] = [
        'supplier' => strtolower($parts[0]),
        'filename' => $parts[1],
        'path' => $file->getPathname(),
    ];
}

usort($files, static fn (array $a, array $b): int => strcmp($a['supplier'] . $a['filename'], $b['supplier'] . $b['filename']));

$summary = [
    'files_total' => count($files),
    'files_ok' => 0,
    'files_error' => 0,
    'files_skipped' => 0,
    'cards_total' => 0,
    'errors' => [],
];

echo "Scan vitrină — " . count($files) . " fișiere, tipuri: " . implode(', ', $types)
    . ", limit={$limit}/fișier, ollama=" . ($useOllama ? 'yes' : 'no') . PHP_EOL . PHP_EOL;

foreach ($files as $file) {
    $label = $file['supplier'] . '/' . $file['filename'];
    echo "=== {$label} ===" . PHP_EOL;

    try {
        import_motor_require_allowed_supplier($file['supplier']);
    } catch (Throwable $e) {
        echo "  SKIP furnizor nepermis: " . $e->getMessage() . PHP_EOL;
        $summary['files_skipped']++;
        $summary['errors'][] = ['file' => $label, 'error' => 'supplier_rejected'];
        continue;
    }

    $started = microtime(true);
    try {
        [$cards, $report] = ImportShowcaseScanner::scanFile(
            $file['path'],
            $file['filename'],
            $types,
            $minScore,
            $limit,
            ImportShowcaseScanner::DEFAULT_MAX_ROWS,
            $useOllama
        );
    } catch (Throwable $e) {
        echo "  ERROR exception: " . $e->getMessage() . PHP_EOL;
        $summary['files_error']++;
        $summary['errors'][] = ['file' => $label, 'error' => $e->getMessage()];
        continue;
    }

    $dur = round(microtime(true) - $started, 2);
    $status = (string) ($report['status'] ?? 'unknown');
    $cardsCount = count($cards);
    $summary['cards_total'] += $cardsCount;

    echo "  status: {$status}" . PHP_EOL;
    echo "  delimiter: " . ($report['csv_delimiter'] ?? '?') . PHP_EOL;
    echo "  rows_scanned: " . ($report['rows_scanned'] ?? 0) . PHP_EOL;
    echo "  keyword_hits: " . ($report['rows_keyword_hit'] ?? 0) . PHP_EOL;
    echo "  cards: {$cardsCount} (lightweight: " . ($report['cards_lightweight'] ?? 0) . ')' . PHP_EOL;
    echo "  message: " . ($report['message'] ?? '') . PHP_EOL;
    if (!empty($report['ollama_batch_errors'])) {
        echo "  ollama_errors: " . count($report['ollama_batch_errors']) . PHP_EOL;
    }
    echo "  duration: {$dur}s" . PHP_EOL;

    if ($cardsCount > 0) {
        echo "  sample:" . PHP_EOL;
        foreach (array_slice($cards, 0, 3) as $c) {
            echo '    - [' . ($c['showcaseType'] ?? '?') . '] '
                . mb_substr((string) ($c['name'] ?? $c['title'] ?? ''), 0, 70) . PHP_EOL;
        }
    }

    if ($status === 'error') {
        $summary['files_error']++;
        $summary['errors'][] = ['file' => $label, 'error' => (string) ($report['message'] ?? 'error')];
    } elseif ($status === 'skipped') {
        $summary['files_skipped']++;
        if (($report['format'] ?? '') !== 'csv_crossref') {
            $summary['errors'][] = ['file' => $label, 'error' => (string) ($report['message'] ?? 'skipped')];
        }
    } else {
        $summary['files_ok']++;
        if ($cardsCount === 0) {
            $summary['errors'][] = ['file' => $label, 'error' => '0 cards — ' . ($report['message'] ?? '')];
        }
    }

    echo PHP_EOL;
}

echo str_repeat('=', 60) . PHP_EOL;
echo "REZUMAT: {$summary['files_ok']} OK, {$summary['files_error']} erori, {$summary['files_skipped']} skip"
    . ", {$summary['cards_total']} carduri total" . PHP_EOL;

if ($summary['errors'] !== []) {
    echo PHP_EOL . "PROBLEME:" . PHP_EOL;
    foreach ($summary['errors'] as $err) {
        echo "  - {$err['file']}: {$err['error']}" . PHP_EOL;
    }
}

$outPath = IMPORT_REPORTS . DIRECTORY_SEPARATOR . 'showcase_all_files_test_' . date('Ymd_His') . '.json';
if (!is_dir(IMPORT_REPORTS)) {
    mkdir(IMPORT_REPORTS, 0775, true);
}
file_put_contents($outPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo PHP_EOL . "Raport: {$outPath}" . PHP_EOL;
exit($summary['files_error'] > 0 ? 1 : 0);
