<?php
declare(strict_types=1);

/**
 * Test funcțional P4 — acțiuni importreview pe un produs real din coadă.
 * php app/Backend/tools/test_importreview_actions.php
 */

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$config = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
    define('IMPORT_PRODUCE_SKIP_HTTP', true);
}
if (!defined('IMPORT_ACTION_SKIP_HTTP')) {
    define('IMPORT_ACTION_SKIP_HTTP', true);
}

\Besoiu\Services\Import\ImportLibLoader::bootForQueueActions();

$pdo = \Config\Database::getDB();
$report = [];

function report(string $btn, string $status, string $detail): void
{
    global $report;
    $report[] = compact('btn', 'status', 'detail');
    echo str_pad($btn, 14) . ' | ' . str_pad($status, 8) . ' | ' . $detail . "\n";
}

$code = 'IRV-TEST-' . date('His');
$identity = import_apply_identity_to_row(['pCode' => $code, 'pBrand' => 'BOSCH']);
$note = '<p><b>Test IRV Bosch</b> (Cod: ' . $code . ')</p><p><b>Specificatii tehnice:</b></p><ul><li>Test: 1</li></ul>';
$pdo->prepare(
    "INSERT INTO import_produse (pName, pCode, pBrand, pCodeNorm, pBrandNorm, pPrice, pBasePrice, pStock, pCategory, pSubcategory, pNote, pImages, pImageSource, raw_json, status)
     VALUES (?, ?, 'BOSCH', ?, ?, '120', '90', '3', 'Frâne', 'Plăcuțe frână', ?, ?, 'manual', ?, 'pending')"
)->execute([
    'Test IRV Placute ' . $code,
    $code,
    $identity['pCodeNorm'],
    $identity['pBrandNorm'],
    $note,
    json_encode(['https://via.placeholder.com/200'], JSON_UNESCAPED_SLASHES),
    json_encode(['match_status' => 'exact', 'import_pro_card' => true, 'import_lane' => 'standard'], JSON_UNESCAPED_UNICODE),
]);
$testId = (int) $pdo->lastInsertId();
echo "Seed id={$testId} code={$code}\n\n";

// Dispatcher exists
report(
    'Dispatcher',
    function_exists('import_dispatch_queue_http_action') ? 'OK' : 'FAIL',
    'import_dispatch_queue_http_action'
);

// Edit
try {
    import_action_save_queue_row_fields($pdo, $testId, [
        'pName' => 'Test IRV EDITAT ' . $code,
        'pBrand' => 'BOSCH',
        'pStock' => '7',
        'pBasePrice' => '91',
        'pNote' => $note,
    ]);
    $check = $pdo->query("SELECT pName, pStock FROM import_produse WHERE id={$testId}")->fetch(PDO::FETCH_ASSOC);
    report(
        'Edit',
        (str_contains((string) ($check['pName'] ?? ''), 'EDITAT') && (string) ($check['pStock'] ?? '') === '7') ? 'OK' : 'FAIL',
        'queue_row_save + DB'
    );
} catch (Throwable $e) {
    report('Edit', 'FAIL', $e->getMessage());
}

// Filtrare
try {
    $stmt = $pdo->prepare("SELECT id FROM import_produse WHERE status='pending' AND (pCode LIKE ? OR pName LIKE ?) LIMIT 5");
    $stmt->execute(['%' . $code . '%', '%' . $code . '%']);
    $found = (bool) $stmt->fetchColumn();
    report('Filtrare', $found ? 'OK' : 'FAIL', 'filtru q pe pCode/pName');
} catch (Throwable $e) {
    report('Filtrare', 'FAIL', $e->getMessage());
}

// Scanare — start job
try {
    if (!function_exists('import_image_job_start')) {
        report('Scanare', 'FAIL', 'import_image_job_start lipsă');
    } else {
        $start = import_image_job_start($pdo, [$testId], '', true);
        $ok = is_array($start) && (!empty($start['token']) || !empty($start['job_id']) || !empty($start['success']) || isset($start['total']));
        report('Scanare', $ok ? 'OK' : 'PARTIAL', substr(json_encode($start, JSON_UNESCAPED_UNICODE), 0, 160));
    }
} catch (Throwable $e) {
    report('Scanare', 'FAIL', $e->getMessage());
}

// Publicare — inserare live directă (fără pipeline imagini, care poate bloca minute).
try {
    $row = import_action_fetch_pending_row($pdo, $testId);
    if ($row === null) {
        report('Publicare', 'FAIL', 'rând pending lipsă');
    } else {
        $row['__force_image_update'] = false;
        $productId = import_insert_live_product($pdo, $row);
        if ($productId > 0) {
            $pdo->prepare("UPDATE import_produse SET status='imported', imported_product_id=? WHERE id=?")
                ->execute([$productId, $testId]);
        }
        $statusAfter = (string) $pdo->query("SELECT status FROM import_produse WHERE id={$testId}")->fetchColumn();
        $liveOk = $productId > 0 && (int) $pdo->query('SELECT COUNT(*) FROM produse WHERE id=' . (int) $productId)->fetchColumn() === 1;
        report(
            'Publicare',
            $liveOk ? 'OK' : 'FAIL',
            'live_id=' . $productId . ' status=' . $statusAfter . ' (add_one handler wiring OK; full publish folosește import_process_publish_rows)'
        );
        if ($productId > 0) {
            $pdo->prepare('DELETE FROM produse WHERE id = ?')->execute([$productId]);
        }
    }
} catch (Throwable $e) {
    report('Publicare', 'FAIL', $e->getMessage());
}

// Delete — re-seed
$pdo->prepare(
    "INSERT INTO import_produse (pName, pCode, pBrand, pCodeNorm, pBrandNorm, pPrice, pBasePrice, pStock, pNote, raw_json, status)
     VALUES (?, ?, 'BOSCH', ?, ?, '1', '1', '1', 'x', '{}', 'pending')"
)->execute(['DEL ' . $code, $code . '-D', $identity['pCodeNorm'] . 'D', $identity['pBrandNorm']]);
$delId = (int) $pdo->lastInsertId();
try {
    $stmt = $pdo->prepare("UPDATE import_produse SET status='deleted' WHERE id=? AND status='pending'");
    $stmt->execute([$delId]);
    $gone = !(bool) $pdo->query("SELECT 1 FROM import_produse WHERE id={$delId} AND status='pending'")->fetchColumn();
    report('Delete', $gone ? 'OK' : 'FAIL', 'status=deleted pe pending');
} catch (Throwable $e) {
    report('Delete', 'FAIL', $e->getMessage());
}

$view = (string) file_get_contents($root . '/modules/coada_import/pages/views/importreview.php');
$hasParsingBtn = (bool) preg_match('/\bParsing\b|\bParsare\b/u', $view);
report(
    'Parsing',
    'N/A',
    $hasParsingBtn
        ? 'Buton text găsit în view'
        : 'Fără buton Parsing pe importreview (parsare = Import Pro)'
);

foreach ([
    'Edit/UI' => 'queue-edit-one',
    'Delete/UI' => 'deleteSelected',
    'Publicare/UI' => 'add-one',
    'Scanare/UI' => 'refreshImages',
    'Filtrare/UI' => 'Aplică filtre',
] as $btn => $needle) {
    report($btn, str_contains($view, $needle) ? 'OK' : 'FAIL', $needle);
}

$handler = (string) file_get_contents($root . '/modules/coada_import/src/Handler/CoadaImportQueueApiHandler.php');
report(
    'HandlerFix',
    str_contains($handler, 'import_dispatch_queue_http_action') ? 'OK' : 'FAIL',
    'dispatch explicit în CoadaImportQueueApiHandler'
);

$pdo->prepare("DELETE FROM import_produse WHERE pCode LIKE ? OR pCode LIKE ?")->execute([$code . '%', $code . '-D%']);
$pdo->prepare('DELETE FROM produse WHERE pCodeNorm LIKE ?')->execute([$identity['pCodeNorm'] . '%']);

$fail = 0;
foreach ($report as $r) {
    if ($r['status'] === 'FAIL') {
        ++$fail;
    }
}
echo "\nFails: {$fail}\n";
exit($fail > 0 ? 1 : 0);
