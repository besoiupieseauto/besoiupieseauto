<?php
declare(strict_types=1);

/**
 * Audit mapare + segmente titlu pe eșantion Import Pro (rapoarte JSON) sau import_produse.
 *
 * php modules/product_formation/tools/audit_queue_segments.php [--limit=100] [--source=reports|queue] [--status=pending|all]
 */
$root = dirname(__DIR__, 3);
$adminRoot = $root . '/admin';

if (!defined('BESOIU_ROOT')) {
    define('BESOIU_ROOT', $root);
}

require_once $adminRoot . '/bootstrap.php';

$autoload = $adminRoot . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} elseif (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

use Config\Database;
use Dotenv\Dotenv;

$envDir = is_file(BESOIU_APP . '/Config/.env') ? BESOIU_APP . '/Config' : $adminRoot;
Dotenv::createImmutable($envDir)->safeLoad();

$config = require $adminRoot . '/config/config.php';
Database::getInstance(
    (string) $config['db_host'],
    (string) $config['db_name'],
    (string) $config['db_user'],
    (string) $config['db_pass']
);

require_once $root . '/app/Import/MatchingPro/api/bootstrap.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportCardStaging.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportTecdocMysqlEnrichment.php';
require_once $root . '/app/Backend/src/Controllers/Produse/import_base_lib.php';
require_once $root . '/app/Backend/src/Services/ProductCardFormationService.php';

use Besoiu\Services\ProductCardFormationService;

$limit = 100;
$status = 'pending';
$source = 'reports';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(500, (int) substr($arg, 8)));
    }
    if (str_starts_with($arg, '--status=')) {
        $status = trim(substr($arg, 9));
    }
    if (str_starts_with($arg, '--source=')) {
        $source = trim(substr($arg, 9));
    }
}

$segments = ['denumire', 'brand_piesa', 'cod', 'pozitie', 'vehicul_manual', 'compat_marci', 'compat_serii'];
$svc = new ProductCardFormationService();

/** @return list<array<string, mixed>> */
function audit_collect_from_reports(int $limit): array
{
    $dir = IMPORT_REPORTS;
    $files = glob($dir . '/*.json') ?: [];
    rsort($files);

    $matchStats = ['exact' => 0, 'probable' => 0, 'conflict' => 0, 'no_match' => 0, 'other' => 0, 'rows' => 0];
    $picked = [];
    $seenSku = [];

    foreach (array_slice($files, 0, 400) as $file) {
        $payload = json_decode((string) file_get_contents($file), true);
        if (!is_array($payload)) {
            continue;
        }
        $products = is_array($payload['products'] ?? null) ? $payload['products'] : [];
        if ($products === []) {
            continue;
        }

        foreach ($products as $p) {
            if (!is_array($p)) {
                continue;
            }
            $matchStats['rows']++;
            $st = (string) ($p['status'] ?? 'other');
            if (isset($matchStats[$st])) {
                $matchStats[$st]++;
            } else {
                $matchStats['other']++;
            }

            if (!in_array($st, ['exact', 'probable', 'conflict'], true)) {
                continue;
            }
            if (count($picked) >= $limit) {
                break 2;
            }

            $sku = trim((string) ($p['sku_supplier'] ?? ''));
            $key = strtolower($sku . '|' . ($p['brand'] ?? '') . '|' . ($payload['supplier'] ?? ''));
            if ($sku === '' || isset($seenSku[$key])) {
                continue;
            }
            $seenSku[$key] = true;

            $p['_report_supplier'] = (string) ($payload['supplier'] ?? '');
            $p['_report_file'] = basename($file);
            $picked[] = $p;
        }
    }

    return [$picked, $matchStats];
}

/** @param array<string, mixed> $scanRow */
function audit_build_staging_product(array $scanRow): ?array
{
    $brand = trim((string) ($scanRow['matched_brand'] ?? $scanRow['brand'] ?? ''));
    $code = trim((string) ($scanRow['sku_supplier'] ?? ''));
    $productId = (int) ($scanRow['matched_product_id'] ?? 0);
    if ($code === '') {
        return null;
    }

    $entries = ImportTecdocMysqlEnrichment::lookupEntries($brand, $code, $productId);
    if ($entries === null || $entries === []) {
        return null;
    }

    $entryBrand = trim((string) ($entries[0]['ART_BRAND'] ?? $brand));
    $code1 = trim((string) ($entries[0]['ART_CODE_1'] ?? $code));
    $priceNet = isset($scanRow['price']) && is_numeric($scanRow['price']) ? (float) $scanRow['price'] : 0.0;
    $supplierKey = strtolower((string) ($scanRow['_report_supplier'] ?? 'generic'));
    $supplierType = match ($supplierKey) {
        'elit' => 'ELIT',
        'autonet' => 'AUTONET',
        'autopartner' => 'AUTOPARTNER',
        'autototal' => 'AUTOTOTAL',
        'materom' => 'MATEROM',
        'intercars' => 'INTERCARS',
        default => strtoupper($supplierKey),
    };

    require_once dirname(__DIR__, 3) . '/app/Import/MatchingPro/api/lib/ImportTecdocMysqlCardBuilder.php';
    $card = ImportTecdocMysqlCardBuilder::fromEntries(
        $entries,
        $entryBrand !== '' ? $entryBrand : $brand,
        $code1 !== '' ? $code1 : $code,
        $priceNet,
        $supplierType,
        [
            'status' => (string) ($scanRow['status'] ?? ''),
            'match_method' => (string) ($scanRow['match_method'] ?? ''),
            'sku_supplier' => $code,
            'matched_name' => (string) ($scanRow['matched_name'] ?? $scanRow['name'] ?? ''),
            'name' => (string) ($scanRow['name'] ?? ''),
            'source_file' => $supplierKey . '/' . (string) ($scanRow['_report_file'] ?? ''),
        ]
    );
    if ($card === null) {
        return null;
    }

    $card['sourceSupplier'] = $supplierKey;
    $card['sourceFile'] = (string) ($card['sourceFile'] ?? $supplierKey);
    $card['tecdocAudit'] = [
        'found' => true,
        'entries' => $entries,
        'name' => trim((string) ($entries[0]['ART_NAME'] ?? '')),
    ];

    $product = ImportCardStaging::cardToProduct($card);
    if ($product === null) {
        return null;
    }

    $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $raw['entries'] = $entries;
    $product['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $product;
}

/** @param list<array<string, mixed>> $rows */
function audit_analyze_products(array $rows, ProductCardFormationService $svc, array $segments): array
{
    $total = count($rows);
    $segFilled = array_fill_keys($segments, 0);
    $meta = [
        'tecdoc_entries' => 0,
        'formation_applied_live' => 0,
        'tecdoc_art_name' => 0,
        'p_marca' => 0,
        'p_model' => 0,
        'p_motorizare' => 0,
        'p_subcategory' => 0,
        'title_has_pentru' => 0,
        'title_has_position' => 0,
        'enrichment_ok' => 0,
    ];
    $issues = [
        'enrichment_failed' => 0,
        'no_tecdoc_no_vehicle' => 0,
        'motorizare_contaminated' => 0,
        'empty_website_title' => 0,
    ];
    $matchStatus = ['exact' => 0, 'probable' => 0, 'conflict' => 0];
    $examples = ['good' => [], 'weak' => []];

    foreach ($rows as $row) {
        $st = (string) ($row['status'] ?? ($row['_scan_status'] ?? ''));
        if (isset($matchStatus[$st])) {
            $matchStatus[$st]++;
        }

        $product = null;
        $raw = [];
        if (!empty($row['_from_queue'])) {
            $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
            if (!is_array($raw)) {
                $raw = [];
            }
            $product = $row;
            unset($product['raw_json'], $product['status'], $product['_from_queue'], $product['_scan_status']);
        } else {
            $product = audit_build_staging_product($row);
            if ($product === null) {
                $issues['enrichment_failed']++;
                continue;
            }
            $meta['enrichment_ok']++;
            $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
            if (!is_array($raw)) {
                $raw = [];
            }
        }

        $hasEntries = !empty($raw['entries']) || !empty($raw['tecdoc_audit']['entries']);
        if ($hasEntries) {
            $meta['tecdoc_entries']++;
        }
        if (!empty($raw['__tecdoc_art_name'])) {
            $meta['tecdoc_art_name']++;
        }
        if (trim((string) ($product['pMarca'] ?? '')) !== '') {
            $meta['p_marca']++;
        }
        if (trim((string) ($product['pModel'] ?? '')) !== '') {
            $meta['p_model']++;
        }
        if (trim((string) ($product['pMotorizare'] ?? '')) !== '') {
            $meta['p_motorizare']++;
        }
        if (trim((string) ($product['pSubcategory'] ?? '')) !== '') {
            $meta['p_subcategory']++;
        }

        try {
            $applied = $svc->applyToImportProduct($product, $raw);
            $product = is_array($applied['product'] ?? null) ? $applied['product'] : $product;
            if (!empty($applied['product_formation']['applied'])) {
                $meta['formation_applied_live']++;
            }

            $entries = is_array($raw['entries'] ?? null) ? $raw['entries'] : [];
            if ($entries === [] && is_array($raw['tecdoc_audit']['entries'] ?? null)) {
                $entries = $raw['tecdoc_audit']['entries'];
            }
            $ctx = $svc->contextFromProduct($product, $entries);
            $seg = $svc->segmentValues('website', $ctx);

            $filledCount = 0;
            foreach ($segments as $sid) {
                if (trim((string) ($seg[$sid] ?? '')) !== '') {
                    $segFilled[$sid]++;
                    $filledCount++;
                }
            }

            $webTitle = trim((string) ($product['pName'] ?? ''));
            if ($webTitle === '') {
                $issues['empty_website_title']++;
            }
            if (str_contains(mb_strtolower($webTitle, 'UTF-8'), 'pentru')) {
                $meta['title_has_pentru']++;
            }
            if (preg_match('/\b(față|fata|spate|stânga|stanga|dreapta)\b/iu', $webTitle)) {
                $meta['title_has_position']++;
            }

            $motor = trim((string) ($product['pMotorizare'] ?? ''));
            if ($motor !== '' && (str_contains($motor, '|') || preg_match('/Specificat|Atentie|FRANA|COD:/iu', $motor))) {
                $issues['motorizare_contaminated']++;
            }
            if (!$hasEntries && trim((string) ($product['pMarca'] ?? '')) === '') {
                $issues['no_tecdoc_no_vehicle']++;
            }

            $score = $filledCount / max(1, count($segments));
            $item = [
                'id' => (string) ($row['sku_supplier'] ?? $row['id'] ?? '?'),
                'supplier' => (string) ($row['_report_supplier'] ?? $product['pSupplier'] ?? ''),
                'status' => $st,
                'title' => mb_substr($webTitle, 0, 120, 'UTF-8'),
                'segments' => $seg,
                'score' => (int) round($score * 100),
            ];
            if ($score >= 0.85 && count($examples['good']) < 4) {
                $examples['good'][] = $item;
            }
            if ($score <= 0.55 && count($examples['weak']) < 4) {
                $examples['weak'][] = $item;
            }
        } catch (Throwable) {
            $issues['empty_website_title']++;
        }
    }

    $analyzed = max(1, $meta['enrichment_ok'] > 0 ? $meta['enrichment_ok'] : ($total - $issues['enrichment_failed']));

    return compact('total', 'analyzed', 'segFilled', 'meta', 'issues', 'matchStatus', 'examples');
}

function audit_pct(int $n, int $total): string
{
    return number_format($n * 100 / max(1, $total), 1, '.', '');
}

function audit_print_results(string $title, array $result, array $segments, ?array $scanStats = null): void
{
    $total = (int) $result['total'];
    $analyzed = (int) $result['analyzed'];
    $segFilled = $result['segFilled'];
    $meta = $result['meta'];
    $issues = $result['issues'];
    $examples = $result['examples'];

    echo "=== {$title} ===\n";
    echo "Eșantion selectat: {$total} · analizat cu succes: {$analyzed}\n\n";

    if ($scanStats !== null) {
        $rows = max(1, (int) $scanStats['rows']);
        echo "--- Rată căutare TecDoc (ultimele rapoarte scan) ---\n";
        foreach (['exact', 'probable', 'conflict', 'no_match'] as $k) {
            $n = (int) ($scanStats[$k] ?? 0);
            echo '  ' . str_pad($k, 12) . ': ' . str_pad((string) $n, 6, ' ', STR_PAD_LEFT) . '  (' . audit_pct($n, $rows) . "% din {$rows} rânduri)\n";
        }
        $hit = (int) ($scanStats['exact'] ?? 0) + (int) ($scanStats['probable'] ?? 0) + (int) ($scanStats['conflict'] ?? 0);
        echo '  TOTAL match (exact+probable+conflict): ' . audit_pct($hit, $rows) . "%\n\n";
    }

    echo "--- Meta date sursă (pe analizate) ---\n";
    foreach ($meta as $key => $n) {
        echo '  ' . str_pad($key, 28) . ': ' . str_pad((string) $n, 4, ' ', STR_PAD_LEFT) . '  (' . audit_pct((int) $n, $analyzed) . "%)\n";
    }

    echo "\n--- Segmente titlu website (recalcul live) ---\n";
    foreach ($segments as $sid) {
        echo '  ' . str_pad($sid, 18) . ': ' . str_pad((string) $segFilled[$sid], 4, ' ', STR_PAD_LEFT) . '  (' . audit_pct((int) $segFilled[$sid], $analyzed) . "%)\n";
    }

    $full5 = min(
        (int) $segFilled['denumire'],
        (int) $segFilled['brand_piesa'],
        (int) $segFilled['cod'],
        (int) $segFilled['pozitie'],
        (int) $segFilled['vehicul_manual']
    );
    $coreAvg = ((int) $segFilled['denumire'] + (int) $segFilled['brand_piesa'] + (int) $segFilled['cod']) / max(1, $analyzed * 3);

    echo "\n--- Scoruri compuse ---\n";
    echo '  Core (denumire+brand+cod medie):  ' . audit_pct((int) round($coreAvg * $analyzed), $analyzed) . "%\n";
    echo '  Toate 5 segmente UI active:        ' . audit_pct($full5, $analyzed) . "%\n";
    echo '  Titlu cu „pentru” (vehicul):      ' . audit_pct((int) $meta['title_has_pentru'], $analyzed) . "%\n";
    echo '  Titlu cu poziție montaj:           ' . audit_pct((int) $meta['title_has_position'], $analyzed) . "%\n";

    echo "\n--- Probleme ---\n";
    foreach ($issues as $key => $n) {
        echo '  ' . str_pad($key, 28) . ': ' . $n . '  (' . audit_pct((int) $n, max(1, $total)) . "% din eșantion)\n";
    }

    echo "\n--- Exemple bune (≥85%) ---\n";
    foreach ($examples['good'] as $ex) {
        echo "  [{$ex['supplier']}/{$ex['status']}] {$ex['id']} — {$ex['score']}% — {$ex['title']}\n";
    }

    echo "\n--- Exemple slabe (≤55%) ---\n";
    foreach ($examples['weak'] as $ex) {
        echo "  [{$ex['supplier']}/{$ex['status']}] {$ex['id']} — {$ex['score']}% — {$ex['title']}\n";
        $empty = array_keys(array_filter($ex['segments'], static fn (string $v): bool => trim($v) === ''));
        if ($empty !== []) {
            echo '    lipsă: ' . implode(', ', $empty) . "\n";
        }
    }
    echo "\n";
}

if ($source === 'queue') {
    $pdo = Database::getDB();
    $where = $status === 'all' ? '' : " WHERE status = 'pending'";
    $sql = 'SELECT id, pName, pNameMarketplace, pCode, pBrand, pMarca, pModel, pMotorizare, pCategory, pSubcategory, pSupplier, raw_json, status'
        . ' FROM import_produse' . $where
        . ' ORDER BY id DESC LIMIT ' . $limit;
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['_from_queue'] = true;
    }
    unset($r);
    $result = audit_analyze_products($rows, $svc, $segments);
    audit_print_results('Audit import_produse (coadă)', $result, $segments);
    exit(0);
}

[$picked, $scanStats] = audit_collect_from_reports($limit);
if ($picked === []) {
    echo "Nu s-au găsit produse exact/probable în rapoartele Import Pro.\n";
    exit(0);
}

$result = audit_analyze_products($picked, $svc, $segments);
audit_print_results('Audit Import Pro → staging → formare titlu', $result, $segments, $scanStats);

$analyzed = max(1, (int) $result['analyzed']);
$seg = $result['segFilled'];
$core = (((int) $seg['denumire'] + (int) $seg['brand_piesa'] + (int) $seg['cod']) / ($analyzed * 3)) * 100;
$full5 = min((int) $seg['denumire'], (int) $seg['brand_piesa'], (int) $seg['cod'], (int) $seg['pozitie'], (int) $seg['vehicul_manual']) / $analyzed * 100;
$rows = max(1, (int) $scanStats['rows']);
$matchHit = ((int) $scanStats['exact'] + (int) $scanStats['probable'] + (int) $scanStats['conflict']) / $rows * 100;

echo "--- REZUMAT CALIBRARE ---\n";
echo '  Căutare/match TecDoc (scan):     ~' . number_format($matchHit, 0) . "%\n";
echo '  Mapare core (den+brand+cod):    ~' . number_format($core, 0) . "%\n";
echo '  Titlu complet 5 segmente UI:    ~' . number_format($full5, 0) . "%\n";
echo '  Poziție montaj populată:        ~' . number_format((int) $seg['pozitie'] / $analyzed * 100, 0) . "%\n";
echo '  Vehicul manual populat:         ~' . number_format((int) $seg['vehicul_manual'] / $analyzed * 100, 0) . "%\n";
