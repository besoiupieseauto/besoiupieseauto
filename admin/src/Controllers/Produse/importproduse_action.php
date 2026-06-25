<?php
declare(strict_types=1);

use Config\Database;

ini_set('display_errors', '0');
ini_set('max_execution_time', '900');
set_time_limit(900);
if (ob_get_level() === 0) {
    ob_start();
}

define('IMPORT_PRODUCE_SKIP_HTTP', true);
require_once __DIR__ . '/importproduse.php';
require_once dirname(__DIR__, 4) . '/system/import-queue-critical.php';

function import_add_prepared_row(
    PDO $pdo,
    array $row,
    ?array $tecdocFiles = null,
    ?array $sharedLookup = null,
    string $publishMode = 'skip'
): array {
    unset($tecdocFiles, $sharedLookup);

    if (function_exists('import_ensure_publish_image')) {
        $row = import_ensure_publish_image($row);
    }

    $importId = (int)($row['id'] ?? 0);
    $publishResult = import_publish_prepared_row($pdo, $row, $publishMode);
    $publishResult['import_id'] = $importId;
    $publishResult['pCode'] = (string)($row['pCode'] ?? '');
    $publishResult['pBrand'] = (string)($row['pBrand'] ?? '');
    $publishResult['tecdoc_used'] = false;

    return $publishResult;
}

function import_process_publish_rows(PDO $pdo, array $pendingRows, string $publishMode): array
{
    $stats = [
        'added' => 0,
        'updated' => 0,
        'skipped' => 0,
        'forced' => 0,
        'tecdoc' => 0,
        'conflicts' => [],
    ];

    if ($pendingRows === []) {
        return $stats;
    }

    foreach ($pendingRows as $row) {
        $importId = (int)($row['id'] ?? 0);
        $result = import_add_prepared_row($pdo, $row, null, null, $publishMode);
        if ($importId > 0) {
            import_finalize_staging_row($pdo, $importId, $result);
        }

        $productId = (int) ($result['product_id'] ?? $result['existing_id'] ?? 0);
        if ($productId > 0) {
            import_apply_vitrina_if_staged($pdo, $productId, $row);
        }

        $result['import_id'] = $importId;
        import_collect_publish_stats($stats, $result, false);
    }

    $publishedCount = (int) ($stats['added'] ?? 0) + (int) ($stats['updated'] ?? 0) + (int) ($stats['forced'] ?? 0);
    if ($publishedCount > 0) {
        $feedLib = dirname(__DIR__, 4) . '/system/baselinker-feed.php';
        if (is_file($feedLib)) {
            require_once $feedLib;
            baselinker_feed_queue_regenerate($pdo);
        }
    }

    return $stats;
}

function import_action_fetch_pending_row(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM import_produse WHERE id=? AND status='pending' LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function import_action_queue_row_payload(array $row): array
{
    require_once dirname(__DIR__, 4) . '/system/import-image-validate.php';

    $image = besoiu_import_row_image_url($row);
    if ($image === '') {
        $image = '/admin/dist/images/fakers/preview-12.jpg';
    }

    $criticalFlags = besoiu_import_row_critical_flags($row);

    return [
        'id' => (int) ($row['id'] ?? 0),
        'pCode' => (string) ($row['pCode'] ?? ''),
        'pName' => (string) ($row['pName'] ?? ''),
        'pBrand' => (string) ($row['pBrand'] ?? ''),
        'pMarca' => (string) ($row['pMarca'] ?? ''),
        'pModel' => (string) ($row['pModel'] ?? ''),
        'pMotorizare' => (string) ($row['pMotorizare'] ?? ''),
        'pPrice' => (string) ($row['pPrice'] ?? ''),
        'pBasePrice' => (string) ($row['pBasePrice'] ?? ''),
        'pStock' => (string) ($row['pStock'] ?? '0'),
        'pCategory' => (string) ($row['pCategory'] ?? ''),
        'pSubcategory' => (string) ($row['pSubcategory'] ?? ''),
        'pNote' => (string) ($row['pNote'] ?? ''),
        'pOem' => (string) ($row['pOem'] ?? ''),
        'pCompatibilitati' => (string) ($row['pCompatibilitati'] ?? ''),
        'image' => $image,
        'imageSource' => (string) ($row['pImageSource'] ?? 'missing'),
        'imageTrusted' => besoiu_import_row_has_trusted_image($row),
        'status' => (string) ($row['status'] ?? ''),
        'criticalFlags' => array_map(
            static fn(array $flag): string => (string) ($flag['label'] ?? ''),
            $criticalFlags
        ),
    ];
}

function import_action_queue_row_input_string(array $input, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $input)) {
            return trim((string) $input[$key]);
        }
    }

    return $default;
}

function import_action_save_queue_row_fields(PDO $pdo, int $id, array $input): array
{
    $row = import_action_fetch_pending_row($pdo, $id);
    if ($row === null) {
        return ['ok' => false, 'message' => 'Produsul nu este in coada de publicat.', 'status' => 404];
    }

    $name = import_action_queue_row_input_string($input, ['pName', 'name']);
    if ($name === '') {
        return ['ok' => false, 'message' => 'Titlul produsului este obligatoriu.', 'status' => 422];
    }

    $row['pName'] = $name;
    $row['pBrand'] = import_action_queue_row_input_string($input, ['pBrand', 'brand']);
    $row['pMarca'] = import_action_queue_row_input_string($input, ['pMarca', 'marca']);
    $row['pModel'] = import_action_queue_row_input_string($input, ['pModel', 'model']);
    $row['pMotorizare'] = import_action_queue_row_input_string($input, ['pMotorizare', 'motorizare']);
    $row['pCategory'] = import_action_queue_row_input_string($input, ['pCategory', 'category']);
    $row['pSubcategory'] = import_action_queue_row_input_string($input, ['pSubcategory', 'subcategory']);
    $row['pNote'] = import_action_queue_row_input_string($input, ['pNote', 'note']);
    $row['pOem'] = import_action_queue_row_input_string($input, ['pOem', 'oem']);
    $row['pCompatibilitati'] = import_action_queue_row_input_string($input, ['pCompatibilitati', 'compatibilitati']);

    $priceRaw = import_action_queue_row_input_string($input, ['pPrice', 'price']);
    if ($priceRaw !== '') {
        $row['pPrice'] = $priceRaw;
    }

    $basePriceRaw = import_action_queue_row_input_string($input, ['pBasePrice', 'basePrice']);
    if ($basePriceRaw !== '') {
        $row['pBasePrice'] = $basePriceRaw;
    }

    $stockRaw = import_action_queue_row_input_string($input, ['pStock', 'stock'], '0');
    $row['pStock'] = $stockRaw !== '' ? $stockRaw : '0';

    import_sync_prepared_row($pdo, $id, $row);
    $stmt = $pdo->prepare('SELECT * FROM import_produse WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;

    return [
        'ok' => true,
        'message' => 'Modificarile au fost salvate.',
        'row' => import_action_queue_row_payload($saved),
    ];
}

function import_action_reprocess_queue_row(PDO $pdo, int $id): array
{
    $row = import_action_fetch_pending_row($pdo, $id);
    if ($row === null) {
        return ['ok' => false, 'message' => 'Produsul nu este in coada de publicat.', 'status' => 404];
    }

    $row = import_enrich_row_before_live_publish($row);
    import_sync_prepared_row($pdo, $id, $row);
    $stmt = $pdo->prepare('SELECT * FROM import_produse WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;

    return [
        'ok' => true,
        'message' => 'Produs re-procesat (TecDoc + imagine + descriere).',
        'row' => import_action_queue_row_payload($saved),
    ];
}

function import_action_sync_tecdoc_queue_row(PDO $pdo, int $id): array
{
    $row = import_action_fetch_pending_row($pdo, $id);
    if ($row === null) {
        return ['ok' => false, 'message' => 'Produsul nu este in coada de publicat.', 'status' => 404];
    }

    $tecdocFiles = import_resolve_uploaded_tecdoc_files();
    $sharedLookup = import_build_tecdoc_lookup_for_products([$row], $tecdocFiles);
    $enriched = import_enrich_product_from_tecdoc($row, $tecdocFiles, $sharedLookup);
    $enrichedRaw = json_decode((string) ($enriched['raw_json'] ?? '{}'), true);
    if (is_array($enrichedRaw) && !empty($enrichedRaw['tecdoc_import_enrichment']['found'])) {
        $row = $enriched;
    } else {
        return [
            'ok' => false,
            'message' => 'Nu am gasit date TecDoc pentru acest produs.',
            'status' => 404,
        ];
    }

    import_sync_prepared_row($pdo, $id, $row);
    $stmt = $pdo->prepare('SELECT * FROM import_produse WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;

    return [
        'ok' => true,
        'message' => 'Date TecDoc sincronizate.',
        'row' => import_action_queue_row_payload($saved),
    ];
}

function import_publish_response(PDO $pdo, array $stats, string $publishMode, int $blockedCritical = 0): void
{
    $message = import_build_publish_message($stats);
    if ($blockedCritical > 0) {
        $message .= ' ' . $blockedCritical . ' produs'
            . ($blockedCritical === 1 ? '' : 'e')
            . ' sărit(e) — date critice lipsă (auto-publish blocat).';
    }
    $hasPendingConflicts = ($stats['skipped'] ?? 0) > 0;

    out_json([
        'success' => true,
        'message' => $message,
        'publish_mode' => import_resolve_publish_mode($publishMode),
        'added' => (int)($stats['added'] ?? 0),
        'updated' => (int)($stats['updated'] ?? 0),
        'skipped' => (int)($stats['skipped'] ?? 0),
        'forced' => (int)($stats['forced'] ?? 0),
        'tecdoc' => (int)($stats['tecdoc'] ?? 0),
        'blocked_critical' => $blockedCritical,
        'conflicts' => $stats['conflicts'] ?? [],
        'redirect' => $hasPendingConflicts
            ? '/admin/importreview?status=conflict_live'
            : '/admin/product',
    ]);
}

try {
    $pdo = Database::getDB();
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = (string)($input['action'] ?? '');
    $id = (int)($input['id'] ?? 0);
    $ids = array_values(array_filter(array_map('intval', (array)($input['ids'] ?? []))));
    $publishMode = import_resolve_publish_mode((string)($input['publish_mode'] ?? 'skip'));

    $imageJobActions = ['refresh_images_start', 'refresh_images_step', 'refresh_images_cancel'];
    if (in_array($action, $imageJobActions, true)) {
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    if ($action === 'refresh_images_start') {
        $supplier = trim((string)($input['supplier'] ?? ''));
        $force = !empty($input['force']) || $ids !== [];
        $start = import_image_job_start($pdo, $ids, $supplier, $force);
        if (empty($start['ok'])) {
            out_json([
                'success' => false,
                'message' => (string)($start['message'] ?? 'Nu există produse de scanat.'),
                'skipped' => (int)($start['skipped'] ?? 0),
            ]);
        }

        out_json([
            'success' => true,
            'job_id' => (string)$start['job_id'],
            'total' => (int)($start['total'] ?? 0),
            'skipped' => (int)($start['skipped'] ?? 0),
            'message' => 'Scanare imagini pornită în fundal.',
        ]);
    }

    if ($action === 'refresh_images_step') {
        $jobId = trim((string)($input['job_id'] ?? ''));
        if ($jobId === '') {
            out_json(['success' => false, 'message' => 'Lipsește job_id.'], 422);
        }

        $step = import_image_job_step($pdo, $jobId);
        if (empty($step['ok'])) {
            out_json([
                'success' => false,
                'message' => (string)($step['error'] ?? 'Eroare la pasul job-ului.'),
            ], 422);
        }

        out_json([
            'success' => true,
            'status' => $step['status'] ?? null,
            'result' => $step['result'] ?? null,
            'cancelled' => !empty($step['cancelled']),
            'message' => (string)(($step['status']['message'] ?? '') ?: 'Pas executat.'),
        ]);
    }

    if ($action === 'refresh_images_cancel') {
        $jobId = trim((string)($input['job_id'] ?? ''));
        if ($jobId === '') {
            out_json(['success' => false, 'message' => 'Lipsește job_id.'], 422);
        }
        import_job_cancel($jobId);
        out_json(['success' => true, 'message' => 'Scanare imagini oprită.']);
    }

    if ($action === 'refresh_images') {
        out_json([
            'success' => false,
            'message' => 'Folosește scanarea în fundal (refresh_images_start / refresh_images_step).',
        ], 410);
    }

    if ($action === 'exclude_one' || $action === 'delete_one') {
        $stmt = $pdo->prepare("UPDATE import_produse SET status='deleted' WHERE id=? AND status='pending'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            out_json(['success' => false, 'message' => 'Produsul nu este in coada de publicat.'], 404);
        }
        out_json([
            'success' => true,
            'message' => $action === 'exclude_one'
                ? 'Produs exclus din coada de import.'
                : 'Produs sters din coada.',
        ]);
    }

    if ($action === 'restore_one') {
        $pdo->prepare("UPDATE import_produse SET status='pending' WHERE id=?")->execute([$id]);
        out_json(['success' => true, 'message' => 'Produs restaurat.']);
    }

    if ($action === 'add_one') {
        $stmt = $pdo->prepare("SELECT * FROM import_produse WHERE id=? AND status='pending' LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            out_json(['success' => false, 'message' => 'Produsul nu este disponibil pentru adaugare.'], 404);
        }

        $stats = import_process_publish_rows($pdo, [$row], $publishMode);
        if (($stats['skipped'] ?? 0) > 0) {
            $conflict = $stats['conflicts'][0] ?? [];
            out_json([
                'success' => true,
                'message' => 'Produs omis: exista deja in magazin (#' . (int)($conflict['existing_id'] ?? 0) . ').',
                'action' => 'skipped',
                'skipped' => 1,
                'conflicts' => $stats['conflicts'],
                'redirect' => '/admin/importreview?status=conflict_live',
            ]);
        }

        $actionLabel = ($stats['updated'] ?? 0) > 0 ? 'actualizat' : 'adaugat';
        out_json([
            'success' => true,
            'message' => 'Produs ' . $actionLabel . ' in magazin.',
            'action' => ($stats['updated'] ?? 0) > 0 ? 'updated' : 'inserted',
            'added' => (int)($stats['added'] ?? 0),
            'updated' => (int)($stats['updated'] ?? 0),
            'redirect' => '/admin/product',
        ]);
    }

    if ($action === 'add_all_pending') {
        $supplier = trim((string)($input['supplier'] ?? ''));
        $sql = "SELECT * FROM import_produse WHERE status='pending'";
        $params = [];
        if ($supplier !== '') {
            $sql .= ' AND pSupplier=?';
            $params[] = $supplier;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pendingRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $filtered = besoiu_import_filter_auto_publishable_rows($pendingRows);
        $stats = import_process_publish_rows($pdo, $filtered['publishable'], $publishMode);
        import_publish_response($pdo, $stats, $publishMode, (int) ($filtered['blocked'] ?? 0));
    }

    if ($action === 'add_selected') {
        if (!$ids) {
            out_json(['success' => false, 'message' => 'Nu ai selectat produse.'], 422);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM import_produse WHERE id IN ($placeholders) AND status='pending'");
        $stmt->execute($ids);
        $pendingRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stats = import_process_publish_rows($pdo, $pendingRows, $publishMode);
        import_publish_response($pdo, $stats, $publishMode);
    }

    if ($action === 'delete_selected') {
        if (!$ids) {
            out_json(['success' => false, 'message' => 'Nu ai selectat produse.'], 422);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE import_produse SET status='deleted' WHERE id IN ($placeholders) AND status='pending'");
        $stmt->execute($ids);
        out_json(['success' => true, 'message' => 'Produse selectate mutate la sterse: ' . $stmt->rowCount()]);
    }

    if ($action === 'queue_row_get') {
        $row = import_action_fetch_pending_row($pdo, $id);
        if ($row === null) {
            out_json(['success' => false, 'message' => 'Produsul nu este in coada de publicat.'], 404);
        }
        out_json([
            'success' => true,
            'row' => import_action_queue_row_payload($row),
        ]);
    }

    if ($action === 'queue_row_save') {
        $result = import_action_save_queue_row_fields($pdo, $id, $input);
        if (empty($result['ok'])) {
            out_json(['success' => false, 'message' => (string) ($result['message'] ?? 'Eroare.')], (int) ($result['status'] ?? 422));
        }
        out_json([
            'success' => true,
            'message' => (string) $result['message'],
            'row' => $result['row'] ?? null,
        ]);
    }

    if ($action === 'reprocess_one') {
        $result = import_action_reprocess_queue_row($pdo, $id);
        if (empty($result['ok'])) {
            out_json(['success' => false, 'message' => (string) ($result['message'] ?? 'Eroare.')], (int) ($result['status'] ?? 422));
        }
        out_json([
            'success' => true,
            'message' => (string) $result['message'],
            'row' => $result['row'] ?? null,
        ]);
    }

    if ($action === 'sync_tecdoc_one') {
        $result = import_action_sync_tecdoc_queue_row($pdo, $id);
        if (empty($result['ok'])) {
            out_json(['success' => false, 'message' => (string) ($result['message'] ?? 'Eroare.')], (int) ($result['status'] ?? 422));
        }
        out_json([
            'success' => true,
            'message' => (string) $result['message'],
            'row' => $result['row'] ?? null,
        ]);
    }

    if ($action === 'export_validated_csv') {
        require_once dirname(__DIR__, 4) . '/system/import-queue-export.php';
        $supplierFilter = trim((string) ($input['supplier'] ?? ''));
        $validatedRows = import_queue_export_fetch_validated_rows($pdo, $supplierFilter, $ids);
        if ($validatedRows === []) {
            out_json([
                'success' => false,
                'message' => 'Nu există produse validate în coadă pentru export (categorie, brand, preț > 0, imagine).',
            ], 422);
        }

        $csv = import_queue_export_csv_content($validatedRows);
        $filename = import_queue_export_filename($supplierFilter);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    if ($action === 'export_autopro_csv') {
        require_once dirname(__DIR__, 4) . '/system/import-queue-export.php';
        $supplierFilter = trim((string) ($input['supplier'] ?? ''));
        $validatedRows = import_queue_export_fetch_validated_rows($pdo, $supplierFilter, $ids);
        if ($validatedRows === []) {
            out_json([
                'success' => false,
                'message' => 'Nu există produse validate în coadă pentru export Piese Autopro (categorie, brand, preț > 0, imagine).',
            ], 422);
        }

        $csv = import_queue_export_autopro_csv_content($validatedRows);
        $filename = import_queue_export_autopro_filename($supplierFilter);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    if ($action === 'export_baselinker') {
        require_once dirname(__DIR__, 4) . '/system/import-queue-baselinker-export.php';
        $supplierFilter = trim((string) ($input['supplier'] ?? ''));
        $result = import_queue_baselinker_export($pdo, $supplierFilter, $ids);
        if (empty($result['ok'])) {
            out_json([
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Export BaseLinker eșuat.'),
                'sent' => (int) ($result['sent'] ?? 0),
                'errors' => (int) ($result['errors'] ?? 0),
                'error_details' => $result['error_details'] ?? [],
            ], 422);
        }

        out_json([
            'success' => true,
            'message' => (string) ($result['message'] ?? ''),
            'sent' => (int) ($result['sent'] ?? 0),
            'errors' => (int) ($result['errors'] ?? 0),
            'error_details' => $result['error_details'] ?? [],
        ]);
    }

    out_json(['success' => false, 'message' => 'Actiune invalida.'], 422);
} catch (Throwable $e) {
    out_json(['success' => false, 'message' => 'Eroare: ' . $e->getMessage()], 500);
}
