<?php
declare(strict_types=1);

/**
 * Audit E2E Import Pro — 10 produse / furnizor × manual/cron × cu/fără imagini + TecDoc.
 * php app/Import/MatchingPro/tools/audit_import_e2e.php
 */
$root = dirname(__DIR__, 4);
define('BESOIU_ROOT', $root);
define('BESOIU_APP', $root . '/app');
define('BESOIU_ADMIN', $root . '/admin');

require_once $root . '/app/Import/bootstrap.php';
require_once $root . '/admin/bootstrap.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/lib/ImportFastCardPipeline.php';

$SAMPLE = 10;
$suppliersCfg = json_decode((string) file_get_contents(IMPORT_CONFIG . '/suppliers.json'), true);
if (!is_array($suppliersCfg)) {
    fwrite(STDERR, "suppliers.json invalid\n");
    exit(1);
}

$skipKeys = ['furnizor_demo', 'generic'];
$feedBase = $root . '/app/Backend/storage/supplier_feeds';

/** @return list<array{slug:string,filename:string,path:string}> */
function discoverFeeds(string $feedBase, array $suppliersCfg, array $skipKeys): array
{
    $out = [];
    foreach ($suppliersCfg as $slug => $cfg) {
        if (in_array($slug, $skipKeys, true)) {
            continue;
        }
        $dir = $feedBase . DIRECTORY_SEPARATOR . $slug;
        if (!is_dir($dir)) {
            $out[] = ['slug' => $slug, 'filename' => '', 'path' => ''];
            continue;
        }
        $files = glob($dir . '/*.csv') ?: [];
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $path = $files[0] ?? '';
        $out[] = [
            'slug' => $slug,
            'filename' => $path !== '' ? basename($path) : '',
            'path' => $path,
        ];
    }
    return $out;
}

/** @return array{ok:bool,ms:float,cards:int,error:string,stats:array<string,mixed>} */
function runBuild(string $supplier, string $filename, string $path, int $limit, bool $onlyWithImage, string $scanMode): array
{
    $t0 = microtime(true);
    try {
        $res = ImportFastCardPipeline::buildPage(
            $supplier,
            $filename,
            $path,
            $limit,
            0,
            $onlyWithImage,
            $scanMode
        );
        $ms = round((microtime(true) - $t0) * 1000, 1);
        return [
            'ok' => !empty($res['success']),
            'ms' => $ms,
            'cards' => (int) ($res['count'] ?? 0),
            'error' => (string) ($res['error'] ?? ''),
            'stats' => is_array($res['stats'] ?? null) ? $res['stats'] : [],
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'ms' => round((microtime(true) - $t0) * 1000, 1),
            'cards' => 0,
            'error' => $e->getMessage(),
            'stats' => [],
        ];
    }
}

/** @return array{ok:bool,ms:float,summary:array<string,int>,errors:list<string>,products:int} */
function runPythonScan(string $supplier, string $path, int $sample): array
{
    $t0 = microtime(true);
    $args = ['scan', '--file', $path, '--supplier', $supplier, '--no-archive', '--force', '--sample', (string) $sample];
    $result = import_run_python($args, 90);
    $ms = round((microtime(true) - $t0) * 1000, 1);
    $errors = [];
    if ($result['exit_code'] === 124) {
        return ['ok' => false, 'ms' => $ms, 'summary' => [], 'errors' => ['Python scan timeout'], 'products' => 0];
    }
    if ($result['json'] === null) {
        return ['ok' => false, 'ms' => $ms, 'summary' => [], 'errors' => [mb_substr($result['raw'], 0, 200)], 'products' => 0];
    }
    $items = $result['json'];
    $last = is_array($items) && $items !== [] ? $items[array_key_last($items)] : null;
    $payload = is_array($last['payload'] ?? null) ? $last['payload'] : null;
    if (!is_array($payload)) {
        return ['ok' => false, 'ms' => $ms, 'summary' => [], 'errors' => ['Payload scan invalid'], 'products' => 0];
    }
    $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
    $products = is_array($payload['products'] ?? null) ? $payload['products'] : [];
    return [
        'ok' => true,
        'ms' => $ms,
        'summary' => [
            'exact' => (int) ($summary['exact'] ?? 0),
            'probable' => (int) ($summary['probable'] ?? 0),
            'no_match' => (int) ($summary['no_match'] ?? 0),
            'conflict' => (int) ($summary['conflict'] ?? 0),
            'total' => (int) ($summary['total'] ?? count($products)),
        ],
        'errors' => $errors,
        'products' => count($products),
    ];
}

function icon(bool $ok, ?bool $partial = null): string
{
    if ($partial === true) {
        return '⚠️';
    }
    return $ok ? '✅' : '❌';
}

$feeds = discoverFeeds($feedBase, $suppliersCfg, $skipKeys);
$report = [];
$globalIssues = [];

echo "=== AUDIT E2E IMPORT PRO — sample={$SAMPLE} ===\n\n";

foreach ($feeds as $feed) {
    $slug = $feed['slug'];
    $filename = $feed['filename'];
    $path = $feed['path'];

    echo "--- {$slug} ---\n";

    if ($path === '' || !is_file($path)) {
        $row = [
            'supplier' => $slug,
            'feed' => '(lipsă CSV în supplier_feeds)',
            'manual_no_img' => '❌',
            'manual_with_img' => '❌',
            'cron_no_img' => '❌',
            'cron_with_img' => '❌',
            'tecdoc_match' => '0/10',
            'errors' => ['Fișier feed lipsă'],
            'status' => '❌ FAIL',
        ];
        $report[] = $row;
        $globalIssues[] = "{$slug}: feed CSV absent";
        echo "  SKIP — feed lipsă\n\n";
        continue;
    }

    $tecdoc = runPythonScan($slug, $path, $SAMPLE);
    $manualNoImg = runBuild($slug, $filename, $path, $SAMPLE, false, 'restart');
    $manualWithImg = runBuild($slug, $filename, $path, $SAMPLE, true, 'restart');
    // Cron = același motor, mod continue (simulare reluare)
    $cronNoImg = runBuild($slug, $filename, $path, $SAMPLE, false, 'continue');
    $cronWithImg = runBuild($slug, $filename, $path, $SAMPLE, true, 'continue');

    $matchTotal = ($tecdoc['summary']['exact'] ?? 0) + ($tecdoc['summary']['probable'] ?? 0) + ($tecdoc['summary']['conflict'] ?? 0);
    $matchLabel = $matchTotal . '/' . ($tecdoc['summary']['total'] ?? $SAMPLE);

    $errors = [];
    foreach ([
        'manual_no_img' => $manualNoImg,
        'manual_with_img' => $manualWithImg,
        'cron_no_img' => $cronNoImg,
        'cron_with_img' => $cronWithImg,
    ] as $key => $r) {
        if (!$r['ok']) {
            $errors[] = "{$key}: " . ($r['error'] !== '' ? $r['error'] : 'failed');
        } elseif ($r['cards'] === 0 && str_contains($key, 'with_img')) {
            $errors[] = "{$key}: 0 carduri cu imagine (Poze/Autopartner)";
        } elseif ($r['cards'] === 0 && !str_contains($key, 'with_img')) {
            $errors[] = "{$key}: 0 carduri — posibil rată match TecDoc 0% în eșantion";
        }
    }
    if (!$tecdoc['ok']) {
        $errors = array_merge($errors, $tecdoc['errors']);
    }

    $passManualNo = $manualNoImg['ok'] && $manualNoImg['cards'] > 0;
    $passManualImg = $manualWithImg['ok']; // 0 cards OK dacă nu există poze locale
    $passCronNo = $cronNoImg['ok'] && $cronNoImg['cards'] > 0;
    $passCronImg = $cronWithImg['ok'];
    $passTecdoc = $tecdoc['ok'] && $matchTotal > 0;

    $allPass = $passManualNo && $passCronNo && $tecdoc['ok'] && $passTecdoc;

    $row = [
        'supplier' => $slug,
        'feed' => $filename,
        'manual_no_img' => icon($passManualNo, $manualNoImg['ok'] && $manualNoImg['cards'] === 0),
        'manual_with_img' => icon($passManualImg && $manualWithImg['cards'] > 0, $manualWithImg['ok'] && $manualWithImg['cards'] === 0),
        'cron_no_img' => icon($passCronNo, $cronNoImg['ok'] && $cronNoImg['cards'] === 0),
        'cron_with_img' => icon($passCronImg && $cronWithImg['cards'] > 0, $cronWithImg['ok'] && $cronWithImg['cards'] === 0),
        'tecdoc_match' => $matchLabel,
        'tecdoc_detail' => $tecdoc['summary'],
        'timings_ms' => [
            'scan' => $tecdoc['ms'],
            'manual_no' => $manualNoImg['ms'],
            'manual_img' => $manualWithImg['ms'],
        ],
        'errors' => $errors,
        'status' => $allPass ? '✅ PASS' : '❌ FAIL',
    ];
    $report[] = $row;

    echo "  feed: {$filename}\n";
    echo "  TecDoc: exact={$tecdoc['summary']['exact']} probable={$tecdoc['summary']['probable']} no_match={$tecdoc['summary']['no_match']} ({$matchLabel})\n";
    echo "  manual fără img: {$manualNoImg['cards']} carduri ({$manualNoImg['ms']}ms)\n";
    echo "  manual cu img:   {$manualWithImg['cards']} carduri ({$manualWithImg['ms']}ms)\n";
    echo "  cron fără img:   {$cronNoImg['cards']} carduri ({$cronNoImg['ms']}ms)\n";
    echo "  cron cu img:     {$cronWithImg['cards']} carduri ({$cronWithImg['ms']}ms)\n";
    if ($errors !== []) {
        echo "  erori: " . implode(' | ', $errors) . "\n";
    }
    echo "  status: {$row['status']}\n\n";
}

// Cron schedule sanity
$cronSchedule = import_cron_suppliers_schedule();
$cronOk = is_array($cronSchedule) && isset($cronSchedule['suppliers']);
echo "--- CRON SCHEDULE ---\n";
echo $cronOk ? '  schedule API: OK (' . count($cronSchedule['suppliers']) . " furnizori)\n" : "  schedule API: FAIL\n";

// TecDoc DB
$tecdocStats = [];
try {
    $args = ['-c', "import sys; sys.path.insert(0,r'" . str_replace('\\', '/', IMPORT_ROOT . '/src') . "'); from tecdoc_lookup import get_tecdoc_lookup; import json; print(json.dumps(get_tecdoc_lookup().stats()))"];
    $py = import_run_python($args, 15);
    if ($py['raw'] !== '') {
        $tecdocStats = json_decode(trim($py['raw']), true) ?: [];
    }
} catch (Throwable) {
}
echo '  TecDoc MySQL: ' . (!empty($tecdocStats['available']) ? 'OK' : 'OFF') . "\n\n";

$outPath = IMPORT_ROOT . '/reports/audit_e2e_' . date('Ymd_His') . '.json';
file_put_contents($outPath, json_encode([
    'generated_at' => date('c'),
    'sample_size' => $SAMPLE,
    'suppliers' => $report,
    'cron_schedule_ok' => $cronOk,
    'tecdoc_stats' => $tecdocStats,
    'global_issues' => $globalIssues,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "=== TABEL REZUMAT ===\n";
printf("%-12s | %-8s | %-8s | %-8s | %-8s | %-10s | %s\n", 'Furnizor', 'Man-no', 'Man+img', 'Cron-no', 'Cron+img', 'TecDoc', 'Status');
foreach ($report as $r) {
    printf(
        "%-12s | %-8s | %-8s | %-8s | %-8s | %-10s | %s\n",
        $r['supplier'],
        $r['manual_no_img'],
        $r['manual_with_img'],
        $r['cron_no_img'],
        $r['cron_with_img'],
        $r['tecdoc_match'],
        $r['status']
    );
}

echo "\nRaport JSON: {$outPath}\n";
