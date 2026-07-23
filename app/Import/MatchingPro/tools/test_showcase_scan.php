<?php
declare(strict_types=1);

/**
 * CLI: test scan vitrină pe fișiere locale.
 * Usage: php tools/test_showcase_scan.php [supplier] [filename]
 */
$root = dirname(__DIR__);
require_once $root . '/api/bootstrap.php';
require_once $root . '/api/lib/ImportShowcaseScanner.php';

$supplier = $argv[1] ?? 'materom';
$filename = $argv[2] ?? 'Lista pret Materom 16.01.2026.csv';
$types = ['ulei'];
$minScore = 72;
$target = 5;
$useOllama = in_array('--no-ollama', $argv, true) ? false : true;

$paths = [
    import_resolve_stored_file($supplier, $filename),
    dirname(__DIR__, 3) . '/app/Backend/storage/supplier_feeds/' . $supplier . '/' . $filename,
    'F:/laragon/www/besoiupieseimport/admin/storage/supplier_feeds/' . $supplier . '/' . $filename,
];

$path = null;
foreach ($paths as $candidate) {
    if ($candidate !== null && is_file($candidate)) {
        $path = $candidate;
        break;
    }
}

if ($path === null) {
    fwrite(STDERR, "Fișier negăsit pentru {$supplier}/{$filename}\n");
    exit(1);
}

echo "File: {$path}\n";
echo "Ollama: " . (import_ollama_available() ? 'yes' : 'no') . " | use_ollama: " . ($useOllama ? 'yes' : 'no') . "\n\n";

try {
    [$cards, $report] = ImportShowcaseScanner::scanFile(
        $path,
        $filename,
        $types,
        $minScore,
        $target,
        500000,
        $useOllama
    );
    echo json_encode([
        'cards' => count($cards),
        'report' => $report,
        'sample' => array_slice(array_map(static fn (array $c): array => [
            'name' => $c['name'] ?? '',
            'type' => $c['showcaseType'] ?? '',
            'score' => $c['showcaseScore'] ?? null,
            'method' => $c['showcaseMatchMethod'] ?? '',
        ], $cards), 0, 5),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(2);
}
