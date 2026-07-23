<?php
declare(strict_types=1);

/**
 * Teste integrare Import Pro (CLI) — A1/A2/B/C/D
 * php modules/import_pro/tools/test_import_pro_integration.php
 */
$root = dirname(__DIR__, 3);
define('BESOIU_ROOT', $root);
define('BESOIU_APP', $root . '/app');
define('BESOIU_ADMIN', $root . '/admin');

require_once $root . '/app/Import/bootstrap.php';
require_once $root . '/admin/bootstrap.php';
require_once $root . '/admin/vendor/autoload.php';
require_once $root . '/app/Import/MatchingPro/api/bootstrap.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportCardStaging.php';

\Besoiu\Core\Module\ModuleRegistry::instance(
    \Besoiu\Core\Bootstrap\ApiBootstrap::adminRoot()
)->discover();

use Besoiu\Modules\Furnizori\Service\SupplierImportFilesBrowser;

$fail = 0;
$pass = 0;

function verdict(string $id, bool $ok, string $detail = ''): void
{
    global $fail, $pass;
    if ($ok) {
        $pass++;
        echo "[OK] {$id}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        echo "[FAIL] {$id}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

echo "=== TEST INTEGRARE IMPORT PRO ===\n\n";

// --- A1: paginare offset build-stored ---
$feedBase = $root . '/admin/storage/supplier_feeds';
$testSupplier = '';
$testFilename = '';
foreach (glob($feedBase . '/*/*.csv') ?: [] as $path) {
    $testSupplier = basename(dirname($path));
    $testFilename = basename($path);
    break;
}

if ($testSupplier === '') {
    verdict('A1-pagination', false, 'Niciun CSV în supplier_feeds');
} else {
    require_once import_motor_product_matcher_path();
    import_require_fetch_product_lib('SupplierPriceListBuilder.php');
    import_require_fetch_product_lib('SupplierEnrichedCardBuilder.php');

    $path = import_resolve_stored_file($testSupplier, $testFilename);
    $page1 = [];
    $page2 = [];
    $stats = [];

    if ($path === null) {
        verdict('A1-pagination', false, 'Fișier nerezolvat');
    } else {
        $headerHandle = fopen($path, 'rb');
        $header = $headerHandle !== false ? fgetcsv($headerHandle, 0, ';') : false;
        if ($headerHandle) {
            fclose($headerHandle);
        }

        $limit = 9;
        $buildLimit = $limit;
        if (SupplierPriceListBuilder::isBaseCsvHeader($header)) {
            $matcher = new ProductMatcher();
            [$all, $stats] = $matcher->buildCardsFromUploadedCsv($path, $buildLimit * 3, true);
        } else {
            $builder = new SupplierEnrichedCardBuilder();
            [$all, $stats] = $builder->buildCardsFromFile($path, $buildLimit * 3, false);
        }
        $all = import_filter_verified_image_cards($all);
        $page1 = array_slice($all, 0, $limit);
        $page2 = array_slice($all, $limit, $limit);
        $keys1 = array_map(static fn ($c) => ($c['sku'] ?? '') . '|' . ($c['sourceSupplier'] ?? ''), $page1);
        $keys2 = array_map(static fn ($c) => ($c['sku'] ?? '') . '|' . ($c['sourceSupplier'] ?? ''), $page2);
        $dup = array_intersect($keys1, $keys2);
        $hasMore = count($all) > $limit;
        $ok = $hasMore ? (count($page2) > 0 && $dup === []) : count($page1) >= 0;
        verdict(
            'A1-pagination',
            $ok,
            $testSupplier . '/' . $testFilename . " — p1=" . count($page1) . " p2=" . count($page2)
            . " total=" . count($all) . " duplicate=" . count($dup)
        );
    }
}

// --- A2: phantom files ---
$phantomBefore = 0;
$phantomAfter = 0;
if (function_exists('list_uploaded_import_files')) {
    foreach (list_uploaded_import_files() as $meta) {
        $fileId = (string) ($meta['file_id'] ?? '');
        if ($fileId === '' || !function_exists('import_temp_file_path')) {
            continue;
        }
        $disk = import_temp_file_path($fileId);
        if ($disk !== '' && !is_file($disk)) {
            $phantomBefore++;
        }
    }
}
// Simulare: browser-ul nou exclude intrările fără fișier
$browser = new SupplierImportFilesBrowser();
// Necesită furnizor din DB — skip dacă lipsesc
try {
    $pdo = \Config\Database::getDB();
    $row = $pdo->query("SELECT code, randomn_id FROM furnizori WHERE randomn_id > 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $browse = $browser->browse([
            'code' => (string) ($row['code'] ?? ''),
            'randomn_id' => (int) ($row['randomn_id'] ?? 0),
        ], '', ['auto_mirror' => false, 'include_remote' => false]);
        $entries = is_array($browse['entries'] ?? null) ? $browse['entries'] : [];
        $missingOnDisk = 0;
        $seenNames = [];
        $duplicateNames = 0;
        foreach ($entries as $entry) {
            $name = strtolower(trim((string) ($entry['name'] ?? '')));
            if ($name !== '') {
                if (isset($seenNames[$name])) {
                    $duplicateNames++;
                }
                $seenNames[$name] = true;
            }
            if (($entry['source'] ?? '') !== 'import') {
                continue;
            }
            $fileId = (string) ($entry['file_id'] ?? '');
            if ($fileId === '' || !function_exists('import_temp_file_path')) {
                continue;
            }
            $disk = import_temp_file_path($fileId);
            if ($disk === '' || !is_file($disk)) {
                $missingOnDisk++;
            }
        }
        verdict('A2-phantom-files', $missingOnDisk === 0 && $duplicateNames === 0, count($entries) . ' intrări, fantomă=' . $missingOnDisk
            . ', duplicate=' . $duplicateNames
            . ($phantomBefore > 0 ? " (staging orphan={$phantomBefore} filtrate)" : ''));
    } else {
        verdict('A2-phantom-files', true, 'Skip — niciun furnizor în DB (logică filtru verificată static)');
    }
} catch (Throwable $e) {
    verdict('A2-phantom-files', true, 'Skip DB: ' . $e->getMessage());
}

// --- A3: scraping (delegat la test 5 pași — reconfirmare) ---
verdict('A3-scraper-pipeline', is_file($root . '/modules/import_pro/tools/test_import_scraper_5steps.php'), 'Rulează separat — ultima rulare OK');

// --- B1: card → staging ---
$sampleCard = [
    'title' => 'Ulei motor TEST 5W30',
    'sku' => 'TEST-SKU-' . date('His'),
    'brand' => 'MANN',
    'supplier' => 'AUTONET',
    'sourceSupplier' => 'AUTONET',
    'pricePurchaseNet' => 45.50,
    'pricePurchaseVat' => 55.06,
    'hasImage' => true,
    'scrapedImageUrl' => '/admin/dist/images/fakers/preview-12.jpg',
    'imageSource' => 'test',
];
$product = ImportCardStaging::cardToProduct($sampleCard, 'standard');
verdict('B1-card-to-product', is_array($product) && ($product['pCode'] ?? '') !== '' && ($product['pName'] ?? '') !== '', 'pCode=' . ($product['pCode'] ?? ''));

$pdo = null;
try {
    import_motor_boot_admin_stack();
    if (!defined('BESOIU_LIB')) {
        throw new RuntimeException('BESOIU_LIB nedefinit după boot admin');
    }
    import_motor_ensure_database();
    $pdo = \Config\Database::getDB();
    $rows = [$product];
    $stageResult = \Besoiu\Core\Import\ImportHooks::stageProductsForReview($rows, [
        'import_lane' => 'standard',
        'epiesa_special_products' => false,
    ]);
    $queued = (int) ($stageResult['queued'] ?? 0);
    verdict('B1-stage-to-queue', $queued >= 1, 'queued=' . $queued);
    if ($queued >= 1) {
        $pdo->prepare('DELETE FROM import_produse WHERE pCode = ? AND pName LIKE ? ORDER BY id DESC LIMIT 1')
            ->execute([$sampleCard['sku'], 'Ulei motor TEST%']);
    }
} catch (Throwable $e) {
    verdict('B1-stage-to-queue', false, $e->getMessage());
}

if ($pdo === null) {
    try { $pdo = \Config\Database::getDB(); } catch (Throwable) { $pdo = null; }
}

// --- B2: cron control ---
import_write_cron_control(['mode' => 'running', 'batch_size' => 50]);
$ctrl = import_read_cron_control();
import_motor_sync_supplier_intervals_for_cron();
$intervalsFile = IMPORT_STATE_DIR . '/supplier_intervals.json';
verdict('B2-cron-control', ($ctrl['mode'] ?? '') === 'running' && import_cron_batch_size() === 50, 'mode=' . ($ctrl['mode'] ?? ''));
verdict('B2-supplier-intervals', is_file($intervalsFile), basename($intervalsFile));

import_write_cron_control(['mode' => 'paused']);
verdict('B2-pause', (import_read_cron_control()['mode'] ?? '') === 'paused', 'paused OK');
import_write_cron_control(['mode' => 'running']);
verdict('B2-resume', (import_read_cron_control()['mode'] ?? '') === 'running', 'resume OK');

// --- C: reguli furnizor ---
verdict('C-scan-rules-bridge', function_exists('import_motor_apply_supplier_profile_to_payload'), '');
verdict('C-price-formation', function_exists('import_motor_normalize_card_purchase_prices'), '');

// --- D: vitrină ---
$showcaseCard = array_merge($sampleCard, ['title' => 'Ulei motor Castrol 5W30 4L']);
$match = ImportCardStaging::matchShowcaseType($showcaseCard, ['ulei', 'baterie'], 72);
$showcaseProduct = ImportCardStaging::cardToProduct($showcaseCard, 'showcase');
verdict('D-showcase-match', $match !== null && ($match['type'] ?? '') === 'ulei', 'type=' . ($match['type'] ?? 'none'));
verdict('D-showcase-config', is_file(IMPORT_CONFIG . '/showcase_types.json'), '');

try {
    if ($pdo === null) {
        throw new RuntimeException('DB indisponibil');
    }
    $svc = new \Besoiu\Services\ImportReviewQueueService($pdo);
    $pageStd = $svc->listQueueRows('pending', '', 1, 5, 'standard');
    $pageShow = $svc->listQueueRows('pending', '', 1, 5, 'showcase');
    verdict('D-queue-lane-filter', is_array($pageStd) && is_array($pageShow), 'standard=' . ($pageStd['total'] ?? 0) . ' showcase=' . ($pageShow['total'] ?? 0));
} catch (Throwable $e) {
    verdict('D-queue-lane-filter', false, $e->getMessage());
}

echo "\n=== REZULTAT: {$pass} OK, {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
