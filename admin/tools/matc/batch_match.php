<?php
declare(strict_types=1);

/**
 * Match in lot — mii de linii brand;cod din fisier text/CSV.
 *
 * Format input (per linie):
 *   BRAND;COD
 *   BRAND,COD
 *   BRAND<TAB>COD
 *   COD                    (fara brand — va esua match)
 *
 * Usage:
 *   php admin/tools/matc/batch_match.php --file=coduri.txt
 *   php admin/tools/matc/batch_match.php --file=coduri.txt --out=rezultat.csv --json
 *   php admin/tools/matc/batch_match.php --file=coduri.txt --db=besoiu_tecdoc_matc_20260826
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/lib/MatcBatchMatcher.php';

$opts = getopt('', ['file:', 'out::', 'db::', 'json', 'matched-only']);
$file = trim((string) ($opts['file'] ?? ''));
$out = trim((string) ($opts['out'] ?? ''));
$database = trim((string) ($opts['db'] ?? matc_read_active_db()));
$asJson = isset($opts['json']);
$matchedOnly = isset($opts['matched-only']);

if ($file === '') {
    fwrite(STDERR, "Usage: php batch_match.php --file=coduri.txt [--out=rez.csv] [--db=...] [--json] [--matched-only]\n");
    exit(1);
}

echo "Batch match: {$file}\n";
echo "Database:    {$database}\n";

$rows = MatcBatchMatcher::readInputFile($file);
$result = MatcBatchMatcher::match($rows, $database);

$matchedCount = count(array_filter($result['results'], static fn(array $r): bool => !empty($r['matched'])));
$unmatchedCount = $result['total'] - $matchedCount;

echo "Total:    {$result['total']}\n";
echo "Matched:  {$matchedCount}\n";
echo "Unmatched: {$unmatchedCount}\n";
echo "Timp:     {$result['elapsed_ms']} ms\n";

$outputRows = $result['results'];
if ($matchedOnly) {
    $outputRows = array_values(array_filter($outputRows, static fn(array $r): bool => !empty($r['matched'])));
}

if ($asJson) {
    $payload = $result;
    $payload['results'] = $outputRows;
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($out !== '') {
        file_put_contents($out, $json . "\n");
        echo "JSON salvat: {$out}\n";
    } else {
        echo $json . "\n";
    }
    exit(0);
}

if ($out !== '') {
    $fh = fopen($out, 'wb');
    if ($fh === false) {
        fwrite(STDERR, "Nu pot scrie: {$out}\n");
        exit(1);
    }
    fputcsv($fh, [
        'line', 'brand', 'code', 'code_norm', 'matched', 'product_id',
        'art_code_1', 'art_code_2', 'art_name', 'ttc_art_id', 'compat_count', 'art_ean', 'error',
    ], ';');
    foreach ($outputRows as $row) {
        fputcsv($fh, [
            $row['line'],
            $row['brand'],
            $row['code'],
            $row['code_norm'],
            !empty($row['matched']) ? '1' : '0',
            $row['product_id'] ?? '',
            $row['art_code_1'],
            $row['art_code_2'],
            $row['art_name'],
            $row['ttc_art_id'],
            $row['compat_count'],
            $row['art_ean'],
            $row['error'] ?? '',
        ], ';');
    }
    fclose($fh);
    echo "CSV salvat: {$out}\n";
}
