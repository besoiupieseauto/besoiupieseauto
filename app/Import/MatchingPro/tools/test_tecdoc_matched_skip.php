<?php
declare(strict_types=1);

/**
 * Test P3: flag tecdoc_matched + skip rematch (doar preț/stoc).
 * php app/Import/MatchingPro/tools/test_tecdoc_matched_skip.php
 */

$root = dirname(__DIR__, 4);
require $root . '/app/Import/bootstrap.php';
require $root . '/app/Import/MatchingPro/api/bootstrap.php';
import_motor_boot_furnizori_libs();
\Besoiu\Services\Import\ImportLibLoader::bootFull(skipHttp: true);

if (!import_motor_ensure_database()) {
    fwrite(STDERR, "DB indisponibilă\n");
    exit(2);
}

$pdo = Config\Database::getDB();
import_ensure_tecdoc_matched_columns($pdo);

$code = 'TEST-MATCH-' . date('His');
$brand = 'BOSCH';
$note = '<p><b>Placute Frana Bosch</b> (Cod: ' . $code . ')</p>'
    . '<p><b>Specificatii tehnice:</b></p><ul><li>Pozitie: Fata</li></ul>'
    . '<p><b>Compatibil cu urmatoarele modele auto:</b></p><ul><li><b>AUDI</b><ul><li>A3</li></ul></li></ul>';

$identity = import_apply_identity_to_row(['pCode' => $code, 'pBrand' => $brand]);
$pdo->prepare(
    "INSERT INTO import_produse (pName, pCode, pBrand, pCodeNorm, pBrandNorm, pPrice, pBasePrice, pStock, pNote, raw_json, status, tecdoc_matched, tecdoc_matched_at)
     VALUES (?, ?, ?, ?, ?, '100', '80', '5', ?, ?, 'pending', 1, NOW())"
)->execute([
    'Placute Frana Bosch ' . $code,
    $code,
    $brand,
    $identity['pCodeNorm'],
    $identity['pBrandNorm'],
    $note,
    json_encode(['match_status' => 'exact', 'import_pro_card' => true], JSON_UNESCAPED_UNICODE),
]);
$importId = (int) $pdo->lastInsertId();

$scan = [
    'sku_supplier' => $code,
    'brand' => $brand,
    'matched_brand' => $brand,
    'status' => 'exact',
    'price_csv' => 99.5,
    'stock' => '12',
    'matched_name' => 'Placute Frana',
];

$errors = [];
if (!import_cron_should_skip_tecdoc_rematch($pdo, $scan)) {
    $errors[] = 'should_skip a returnat false pentru produs matchuit';
}

$existing = import_lookup_tecdoc_matched_record($pdo, $code, $brand);
if ($existing === null || (int) $existing['id'] !== $importId) {
    $errors[] = 'lookup nu a găsit rândul staging';
}

$ok = import_cron_update_price_stock_only($pdo, $scan, $existing ?? ['table' => 'import_produse', 'id' => $importId, 'row' => []]);
if (!$ok) {
    $errors[] = 'update price/stock a eșuat';
}

$row = $pdo->query('SELECT pBasePrice, pPrice, pStock, tecdoc_matched FROM import_produse WHERE id = ' . $importId)->fetch(PDO::FETCH_ASSOC);
if ((string) ($row['pStock'] ?? '') !== '12') {
    $errors[] = 'stoc neactualizat: ' . json_encode($row);
}
if ((float) ($row['pBasePrice'] ?? 0) != 99.5) {
    $errors[] = 'preț bază neactualizat: ' . json_encode($row);
}

$forceScan = $scan + ['force_tecdoc_rematch' => 1];
if (import_cron_should_skip_tecdoc_rematch($pdo, $forceScan)) {
    $errors[] = 'force_tecdoc_rematch ar trebui să forțeze rematch';
}

// cleanup
$pdo->prepare('DELETE FROM import_produse WHERE id = ?')->execute([$importId]);

if ($errors === []) {
    echo "OK: tecdoc_matched skip + price/stock update + force rematch\n";
    exit(0);
}

echo "FAIL:\n- " . implode("\n- ", $errors) . "\n";
exit(1);
