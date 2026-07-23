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
if (!function_exists('import_require_system_file')) {
    require_once __DIR__ . '/import_paths_lib.php';
}
import_require_system_file('import-queue-critical.php');
import_require_system_file('product_dual_title.php');
require_once __DIR__ . '/import_queue_markup_lib.php';

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
        'rag_indexed' => 0,
    ];

    if ($pendingRows === []) {
        return $stats;
    }

    if (class_exists(\Besoiu\Services\AiRag\AiProductCatalogRagIndexerService::class)) {
        \Besoiu\Services\AiRag\AiProductCatalogRagIndexerService::beginBatch();
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

    if (class_exists(\Besoiu\Services\AiRag\AiProductCatalogRagIndexerService::class)) {
        try {
            $rag = \Besoiu\Services\AiRag\AiProductCatalogRagIndexerService::endBatch($pdo);
            $stats['rag_indexed'] = (int) ($rag['indexed'] ?? 0);
            $stats['rag_library_synced'] = (int) ($rag['library_synced'] ?? 0);
        } catch (Throwable $ragError) {
            error_log('[import_rag_index_batch] ' . $ragError->getMessage());
        }
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

function import_invalidate_queue_caches(): void
{
    try {
        \Besoiu\Core\Import\ImportHooks::queueService()->invalidateQueueCaches();
    } catch (Throwable) {
        // Cache invalidation must not break the primary action.
    }
}

function import_action_queue_row_payload(array $row): array
{
    import_require_system_file('import-image-validate.php');

    if (function_exists('besoiu_import_row_hydrate_missing_image')) {
        $row = besoiu_import_row_hydrate_missing_image($row);
    }

    $image = function_exists('besoiu_import_row_resolve_preview_image_url')
        ? besoiu_import_row_resolve_preview_image_url($row)
        : besoiu_import_row_image_url($row);
    if ($image === '') {
        $image = '/admin/dist/images/fakers/preview-12.jpg';
    }

    $criticalFlags = besoiu_import_row_critical_flags($row);
    $placeholder = '/admin/dist/images/fakers/preview-12.jpg';
    $rawMeta = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    $importLane = is_array($rawMeta) ? trim((string) ($rawMeta['import_lane'] ?? 'standard')) : 'standard';

    $websiteTitle = rtrim(trim((string) ($row['pName'] ?? '')), "|\t ");
    $marketplaceTitle = rtrim(trim((string) ($row['pNameMarketplace'] ?? '')), "|\t ");
    if (class_exists(\Besoiu\Services\ProductCardFormationService::class)) {
        $dual = \Besoiu\Services\ProductCardFormationService::resolveDualTitlesFromRow($row);
        if (trim((string) ($dual['website'] ?? '')) !== '') {
            $websiteTitle = rtrim(trim((string) $dual['website']), "|\t ");
        }
        if (trim((string) ($dual['marketplace'] ?? '')) !== '') {
            $marketplaceTitle = rtrim(trim((string) $dual['marketplace']), "|\t ");
        }
    } elseif (function_exists('besoiu_resolve_marketplace_product_title')) {
        $marketplaceTitle = rtrim(trim(besoiu_resolve_marketplace_product_title($row)), "|\t ");
    }
    if ($marketplaceTitle === '') {
        $marketplaceTitle = $websiteTitle;
    }

    $motorizare = trim((string) ($row['pMotorizare'] ?? ''));
    if ($motorizare !== '') {
        $motorizare = trim((string) preg_replace('/\([^)]*\)/u', '', $motorizare));
        $motorizare = trim((string) preg_replace('/\s+/u', ' ', $motorizare));
        if (function_exists('import_sanitize_motorizare_value')) {
            $motorizare = import_sanitize_motorizare_value($motorizare);
            $motorizare = trim(str_replace("\n", ', ', $motorizare));
        }
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'pCode' => (string) ($row['pCode'] ?? ''),
        'pName' => $websiteTitle,
        'pNameMarketplace' => $marketplaceTitle,
        'pBrand' => (string) ($row['pBrand'] ?? ''),
        'pMarca' => (string) ($row['pMarca'] ?? ''),
        'pModel' => (string) ($row['pModel'] ?? ''),
        'pMotorizare' => $motorizare,
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
        'imageHasDisplay' => $image !== '' && $image !== $placeholder && !besoiu_import_image_is_placeholder($image),
        'importLane' => $importLane !== '' ? $importLane : 'standard',
        'status' => (string) ($row['status'] ?? ''),
        'criticalFlags' => array_map(
            static fn(array $flag): string => (string) ($flag['label'] ?? ''),
            $criticalFlags
        ),
        'needsReprocess' => (string) ($row['status'] ?? '') === 'pending'
            && (
                $criticalFlags !== []
                || !besoiu_import_row_has_trusted_image($row)
                || trim((string) ($row['pNote'] ?? '')) === ''
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
        return ['ok' => false, 'message' => 'Titlul site (web) este obligatoriu.', 'status' => 422];
    }

    $marketplaceName = import_action_queue_row_input_string($input, ['pNameMarketplace', 'nameMarketplace', 'marketplaceTitle']);
    if ($marketplaceName === '') {
        $marketplaceName = $name;
    }

    if (function_exists('import_ensure_name_marketplace_columns')) {
        import_ensure_name_marketplace_columns($pdo);
    }
    if (function_exists('besoiu_apply_dual_product_titles')) {
        besoiu_apply_dual_product_titles($row, $name, $marketplaceName);
    } else {
        $row['pName'] = $name;
        $row['pNameMarketplace'] = $marketplaceName;
    }

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
    $row = import_apply_taxonomy_gaps($row);
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
    $row = import_sync_tecdoc_refresh_row($row, $tecdocFiles, $sharedLookup);

    $enrichedRaw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($enrichedRaw) || empty($enrichedRaw['tecdoc_import_enrichment']['found'])) {
        return [
            'ok' => false,
            'message' => 'Nu am gasit date TecDoc pentru acest produs (MySQL / CSV).',
            'status' => 404,
        ];
    }

    $row = import_apply_taxonomy_gaps($row);

    import_sync_prepared_row($pdo, $id, $row);
    $stmt = $pdo->prepare('SELECT * FROM import_produse WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;

    $source = (string) ($enrichedRaw['tecdoc_import_enrichment']['source'] ?? 'tecdoc');
    $sourceLabel = $source === 'mysql_tecdoc' ? 'MySQL TecDoc' : 'TecDoc';
    $hasImage = import_row_has_queue_image($saved);

    return [
        'ok' => true,
        'message' => 'Date sincronizate din ' . $sourceLabel
            . ($hasImage ? ' + imagine reînnoită.' : ' (fără imagine nouă găsită).'),
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
        'rag_indexed' => (int) ($stats['rag_indexed'] ?? 0),
        'redirect' => $hasPendingConflicts
            ? '/admin/importreview?status=conflict_live'
            : '/admin/product',
    ]);
}

if (!function_exists('import_dispatch_queue_http_action')) {
/**
 * Dispatch acțiuni /admin/importreview (Edit/Delete/Publică/Scanare/…).
 * Apelat din CoadaImportQueueApiHandler sau direct din acest fișier (HTTP legacy).
 *
 * @param array<string, mixed>|null $input
 */
function import_dispatch_queue_http_action(?array $input = null): void
{
    try {
    $pdo = Database::getDB();
    if ($input === null) {
        $input = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($input)) {
            $input = $_POST;
        }
    }
    $action = (string)($input['action'] ?? '');
    $id = (int)($input['id'] ?? 0);
    $ids = array_values(array_filter(array_map('intval', (array)($input['ids'] ?? []))));
    $publishMode = import_resolve_publish_mode((string)($input['publish_mode'] ?? 'skip'));

    $imageJobActions = ['refresh_images_start', 'refresh_images_step', 'refresh_images_cancel', 'refresh_images_live'];
    $heavyImportActions = ['reprocess_one', 'sync_tecdoc_one'];
    if (in_array($action, $imageJobActions, true) || in_array($action, $heavyImportActions, true)) {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
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
            'spawned' => !empty($start['spawned']),
            'message' => !empty($start['spawned'])
                ? 'Scanare imagini pornită în fundal (runner CLI).'
                : 'Job creat — runner CLI nu a pornit; folosește polling live.',
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

    if ($action === 'refresh_images_live') {
        $jobId = trim((string)($input['job_id'] ?? ''));
        if ($jobId === '') {
            out_json(['success' => false, 'message' => 'Lipsește job_id.'], 422);
        }
        $meta = import_job_load_meta($jobId);
        if ($meta === null) {
            out_json(['success' => false, 'message' => 'Job inexistent.'], 404);
        }

        $status = (string)($meta['status'] ?? '');
        if ($status === 'running' && !import_image_job_runner_is_active($jobId)) {
            $updatedAt = strtotime((string)($meta['updated_at'] ?? ''));
            $staleSec = $updatedAt > 0 ? (time() - $updatedAt) : 0;
            $spawnCount = (int)($meta['runner_spawn_count'] ?? 0);
            $runnerPid = (int)($meta['runner_pid'] ?? 0);
            if ($spawnCount >= 4 && $runnerPid <= 0 && $staleSec >= 180) {
                import_job_update($jobId, [
                    'status' => 'error',
                    'error' => 'Runner CLI nu pornește — verifică PHP CLI (Laragon) și repornește scanarea.',
                    'message' => 'Runner CLI nu pornește — verifică PHP CLI (Laragon) și repornește scanarea.',
                ]);
                $meta = import_job_load_meta($jobId) ?? $meta;
            } elseif ($staleSec >= 600) {
                import_job_update($jobId, [
                    'status' => 'error',
                    'error' => 'Runner oprit — scanarea nu s-a finalizat. Repornește scanarea.',
                    'message' => 'Runner oprit — scanarea nu s-a finalizat. Repornește scanarea.',
                ]);
                $meta = import_job_load_meta($jobId) ?? $meta;
            } elseif (import_image_job_runner_spawn_cooldown_ok($jobId, 90)) {
                import_image_job_spawn_background_runner($jobId);
                $meta = import_job_load_meta($jobId) ?? $meta;
            }
        }

        out_json([
            'success' => true,
            'status' => import_job_public_status($meta),
            'spawned' => import_image_job_runner_is_active($jobId),
        ]);
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
        import_invalidate_queue_caches();
        out_json([
            'success' => true,
            'message' => $action === 'exclude_one'
                ? 'Produs exclus din coada de import.'
                : 'Produs sters din coada.',
        ]);
    }

    if ($action === 'restore_one') {
        $pdo->prepare("UPDATE import_produse SET status='pending' WHERE id=?")->execute([$id]);
        import_invalidate_queue_caches();
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
        import_invalidate_queue_caches();
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
        out_json([
            'success' => false,
            'message' => 'Folosește job async import.publish_bulk (BesoiuAsync) — add_all_pending sync este dezactivat.',
        ], 410);
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
        import_invalidate_queue_caches();
        import_publish_response($pdo, $stats, $publishMode);
    }

    if ($action === 'delete_selected') {
        if (!$ids) {
            out_json(['success' => false, 'message' => 'Nu ai selectat produse.'], 422);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE import_produse SET status='deleted' WHERE id IN ($placeholders) AND status='pending'");
        $stmt->execute($ids);
        import_invalidate_queue_caches();
        out_json(['success' => true, 'message' => 'Produse selectate mutate la sterse: ' . $stmt->rowCount()]);
    }

    if ($action === 'delete_all') {
        // COUNT(*) exact — TABLE_ROWS din information_schema e estimare InnoDB (poate arăta 57 când sunt 10).
        // Acceptabil aici: acțiune rară, confirmată, destructivă.
        $before = 0;
        try {
            $before = (int) $pdo->query('SELECT COUNT(*) FROM import_produse')->fetchColumn();
        } catch (Throwable) {
            $before = 0;
        }
        $pdo->exec('TRUNCATE TABLE import_produse');
        import_invalidate_queue_caches();
        $noun = $before === 1 ? 'produs' : 'produse';
        out_json([
            'success' => true,
            'message' => $before > 0
                ? ('Coada ștearsă: ' . $before . ' ' . $noun . ' eliminate din import_produse (nu au fost publicate în magazin).')
                : 'Coada de import era deja goală.',
            'deleted' => $before,
            'published' => 0,
        ]);
    }

    if ($action === 'queue_filter_facets') {
        $queueService = \Besoiu\Core\Import\ImportHooks::queueService();
        $statusFacet = trim((string) ($input['status'] ?? 'pending'));
        $laneFacet = trim((string) ($input['lane'] ?? 'standard'));
        $supplierFacet = trim((string) ($input['supplier'] ?? ''));
        $facets = $queueService->listQueueFilterFacets($statusFacet, $laneFacet, $supplierFacet);
        $totalExact = $queueService->countQueueRows($statusFacet, $supplierFacet, $laneFacet, [
            'marca' => trim((string) ($input['marca'] ?? '')),
            'model' => trim((string) ($input['model'] ?? '')),
            'motorizare' => trim((string) ($input['motorizare'] ?? '')),
            'brand' => trim((string) ($input['brand'] ?? '')),
            'an' => trim((string) ($input['an'] ?? '')),
        ]);
        out_json([
            'success' => true,
            'facets' => $facets,
            'total' => $totalExact,
        ]);
    }

    if ($action === 'apply_taxonomy_pending') {
        @set_time_limit(120);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $scope = trim((string) ($input['scope'] ?? 'selected'));
        $statusFilter = trim((string) ($input['status'] ?? 'pending'));
        $laneFilter = trim((string) ($input['lane'] ?? 'standard'));
        $supplierFilter = trim((string) ($input['supplier'] ?? ''));
        $batchMode = !empty($input['batch']);
        $offset = max(0, (int) ($input['offset'] ?? 0));
        $limit = $batchMode
            ? min(50, max(1, (int) ($input['limit'] ?? 25)))
            : min(500, max(1, (int) ($input['limit'] ?? 500)));
        $extraFilters = [
            'marca' => trim((string) ($input['marca'] ?? '')),
            'model' => trim((string) ($input['model'] ?? '')),
            'motorizare' => trim((string) ($input['motorizare'] ?? '')),
            'brand' => trim((string) ($input['brand'] ?? '')),
            'an' => trim((string) ($input['an'] ?? '')),
        ];
        $rows = [];
        $totalRows = 0;
        $whereSql = '';
        $whereParams = [];

        if ($scope === 'filtered') {
            $queueService = \Besoiu\Core\Import\ImportHooks::queueService();
            $whereData = $queueService->buildQueueWhere($statusFilter, $supplierFilter, $laneFilter, $extraFilters);
            $whereSql = $whereData['where'] !== [] ? ' WHERE ' . implode(' AND ', $whereData['where']) : '';
            $whereParams = $whereData['params'];

            if ($batchMode) {
                $countStmt = $pdo->prepare('SELECT COUNT(*) FROM import_produse' . $whereSql);
                $countStmt->execute($whereParams);
                $totalRows = (int) $countStmt->fetchColumn();
                if ($totalRows === 0) {
                    out_json(['success' => false, 'message' => 'Nu există produse de procesat în filtrul curent.'], 404);
                }
                $stmt = $pdo->prepare(
                    'SELECT * FROM import_produse' . $whereSql
                    . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
                );
                $stmt->execute($whereParams);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $stmt = $pdo->prepare(
                    'SELECT * FROM import_produse' . $whereSql . ' ORDER BY id DESC LIMIT ' . $limit
                );
                $stmt->execute($whereParams);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $totalRows = count($rows);
            }
        } else {
            if ($ids === []) {
                out_json(['success' => false, 'message' => 'Nu ai selectat produse.'], 422);
            }
            $totalRows = count($ids);
            $chunkIds = $batchMode ? array_slice($ids, $offset, $limit) : $ids;
            if ($chunkIds === []) {
                out_json([
                    'success' => true,
                    'batch' => $batchMode,
                    'done' => true,
                    'total' => $totalRows,
                    'offset' => $offset,
                    'next_offset' => $offset,
                    'batch_processed' => 0,
                    'batch_updated' => 0,
                    'batch_with_category' => 0,
                    'message' => 'Nu mai sunt produse de procesat.',
                ]);
            }
            $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT * FROM import_produse WHERE id IN ($placeholders) AND status='pending'"
            );
            $stmt->execute($chunkIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if ($rows === [] && !$batchMode) {
            out_json(['success' => false, 'message' => 'Nu există produse de procesat în selecția curentă.'], 404);
        }

        $updated = 0;
        $withCategory = 0;
        $updateStmt = $pdo->prepare(
            'UPDATE import_produse SET pCategory = ?, pSubcategory = ?, raw_json = ? WHERE id = ? AND status = ?'
        );

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $beforeCategory = trim((string) ($row['pCategory'] ?? ''));
            $beforeSubcategory = trim((string) ($row['pSubcategory'] ?? ''));
            $row = import_apply_taxonomy_gaps($row);
            $afterCategory = trim((string) ($row['pCategory'] ?? ''));
            $afterSubcategory = trim((string) ($row['pSubcategory'] ?? ''));
            if ($afterCategory !== '') {
                ++$withCategory;
            }
            if ($beforeCategory === $afterCategory && $beforeSubcategory === $afterSubcategory) {
                continue;
            }
            $updateStmt->execute([
                $afterCategory,
                $afterSubcategory,
                (string) ($row['raw_json'] ?? '{}'),
                (int) ($row['id'] ?? 0),
                'pending',
            ]);
            ++$updated;
        }

        $batchProcessed = count($rows);
        if (!$batchMode) {
            out_json([
                'success' => true,
                'message' => $updated . ' produse actualizate · ' . $withCategory . ' cu categorie completată.',
                'updated' => $updated,
                'with_category' => $withCategory,
                'processed' => $batchProcessed,
            ]);
        }

        $nextOffset = $offset + $batchProcessed;
        $done = $nextOffset >= $totalRows || $batchProcessed === 0;
        $processedSoFar = min($nextOffset, $totalRows);

        out_json([
            'success' => true,
            'batch' => true,
            'done' => $done,
            'total' => $totalRows,
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'processed' => $processedSoFar,
            'batch_processed' => $batchProcessed,
            'batch_updated' => $updated,
            'batch_with_category' => $withCategory,
            'message' => $done
                ? ('Finalizat: ' . $processedSoFar . ' procesate.')
                : ('Lot procesat: ' . $processedSoFar . ' / ' . $totalRows),
        ]);
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
        import_require_system_file('import-queue-export.php');
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

    $nameNormalizeActions = [
        'name_normalize_status',
        'name_normalize_preview',
        'name_normalize_ollama',
        'name_normalize_rules_list',
        'name_normalize_rule_save',
        'name_normalize_rule_delete',
        'name_normalize_queue_scan',
        'name_normalize_apply_one',
        'name_normalize_apply_selected',
        'name_normalize_bulk_stats',
    ];
    if (in_array($action, $nameNormalizeActions, true)) {
        $normalizeService = \Besoiu\Services\ProductNameNormalizeService::create();

        if ($action === 'name_normalize_status') {
            out_json(['success' => true, 'status' => $normalizeService->status()]);
        }

        if ($action === 'name_normalize_preview') {
            $raw = trim((string) ($input['raw_name'] ?? $input['name'] ?? ''));
            $brand = trim((string) ($input['brand'] ?? ''));
            $useOllama = !array_key_exists('use_ollama', $input) || !empty($input['use_ollama']);
            $names = $input['names'] ?? null;
            if (is_array($names) && $names !== []) {
                out_json([
                    'success' => true,
                    'items' => $normalizeService->previewBatch(array_map('strval', $names)),
                ]);
            }
            out_json([
                'success' => true,
                'preview' => $normalizeService->preview($raw, $brand, $useOllama),
            ]);
        }

        if ($action === 'name_normalize_ollama') {
            $raw = trim((string) ($input['raw_name'] ?? $input['name'] ?? ''));
            $brand = trim((string) ($input['brand'] ?? ''));
            $context = trim((string) ($input['context'] ?? ''));
            $result = $normalizeService->suggestWithOllama($raw, $brand, $context);
            out_json([
                'success' => !empty($result['ok']),
                'result' => $result,
                'message' => !empty($result['ok']) ? 'Sugestie Ollama generată.' : (string) ($result['error'] ?? 'Eroare Ollama.'),
            ], !empty($result['ok']) ? 200 : 422);
        }

        if ($action === 'name_normalize_rules_list') {
            $pageNum = max(1, (int) ($input['page'] ?? 1));
            $perPage = max(10, min(100, (int) ($input['per_page'] ?? 40)));
            $search = trim((string) ($input['search'] ?? ''));
            $source = trim((string) ($input['source'] ?? 'all'));
            $result = $normalizeService->listRules($pageNum, $perPage, $search, $source);
            out_json(['success' => true] + $result);
        }

        if ($action === 'name_normalize_rule_save') {
            $aliases = trim((string) ($input['aliases'] ?? $input['aliases_text'] ?? ''));
            $target = trim((string) ($input['target'] ?? $input['normalized'] ?? ''));
            $line = isset($input['line']) ? (int) $input['line'] : null;
            $result = $normalizeService->saveCustomRule($aliases, $target, $line);
            out_json([
                'success' => !empty($result['ok']),
                'message' => (string) ($result['message'] ?? ''),
                'rule' => $result['rule'] ?? null,
            ], !empty($result['ok']) ? 200 : 422);
        }

        if ($action === 'name_normalize_rule_delete') {
            $line = (int) ($input['line'] ?? 0);
            $result = $normalizeService->deleteCustomRule($line);
            out_json([
                'success' => !empty($result['ok']),
                'message' => (string) ($result['message'] ?? ''),
            ], !empty($result['ok']) ? 200 : 422);
        }

        if ($action === 'name_normalize_queue_scan') {
            $limit = max(5, min(200, (int) ($input['limit'] ?? 50)));
            $onlyGaps = !array_key_exists('only_gaps', $input) || !empty($input['only_gaps']);
            $useOllama = !empty($input['use_ollama']);
            $result = $normalizeService->scanQueue($pdo, [
                'status' => (string) ($input['status'] ?? 'pending'),
                'supplier' => (string) ($input['supplier'] ?? ''),
                'only_gaps' => $onlyGaps,
                'use_ollama' => $useOllama,
            ], $limit);
            out_json(['success' => true] + $result);
        }

        if ($action === 'name_normalize_apply_one') {
            $normalized = trim((string) ($input['normalized'] ?? $input['target'] ?? ''));
            $rawArt = trim((string) ($input['raw_art_name'] ?? $input['raw_name'] ?? ''));
            $saveRule = !empty($input['save_rule']);
            $result = $normalizeService->applyToQueueRow($pdo, $id, $normalized, $saveRule, $rawArt);
            out_json([
                'success' => !empty($result['ok']),
                'message' => (string) ($result['message'] ?? ''),
                'row' => $result['row'] ?? null,
            ], !empty($result['ok']) ? 200 : 422);
        }

        if ($action === 'name_normalize_apply_selected') {
            $saveRules = !empty($input['save_rules']);
            $useOllama = !array_key_exists('use_ollama', $input) || !empty($input['use_ollama']);
            $ollamaBatchMax = max(1, min(8, (int) ($input['ollama_batch_max'] ?? 3)));
            $result = $normalizeService->applyToSelected($pdo, $ids, $saveRules, $useOllama, $ollamaBatchMax);
            out_json([
                'success' => !empty($result['ok']),
                'message' => (string) ($result['message'] ?? ''),
                'applied' => (int) ($result['applied'] ?? 0),
                'ollama_applied' => (int) ($result['ollama_applied'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'remaining_ids' => $result['remaining_ids'] ?? [],
                'has_more' => !empty($result['has_more']),
                'errors' => $result['errors'] ?? [],
            ]);
        }

        if ($action === 'name_normalize_bulk_stats') {
            $result = $normalizeService->bulkStats($pdo, [
                'status' => (string) ($input['status'] ?? 'pending'),
                'supplier' => (string) ($input['supplier'] ?? ''),
            ]);
            out_json(['success' => true] + $result);
        }
    }

    if ($action === 'apply_markup_selected' || $action === 'apply_markup_filtered') {
        $filters = import_queue_normalize_markup_filters([
            'supplier' => $input['supplier'] ?? '',
            'status' => $input['status'] ?? 'pending',
            'lane' => $input['lane'] ?? 'standard',
            'marca' => $input['marca'] ?? ($input['filters']['marca'] ?? ''),
            'model' => $input['model'] ?? ($input['filters']['model'] ?? ''),
            'motorizare' => $input['motorizare'] ?? ($input['filters']['motorizare'] ?? ''),
            'brand' => $input['brand'] ?? ($input['filters']['brand'] ?? ''),
            'an' => $input['an'] ?? ($input['filters']['an'] ?? ''),
        ]);

        if ($action === 'apply_markup_selected') {
            if ($ids === []) {
                out_json(['success' => false, 'message' => 'Selectează cel puțin un produs din coadă.'], 422);
            }
            $targetIds = $ids;
        } else {
            $targetIds = [];
        }

        $result = import_queue_apply_markup($pdo, $targetIds, $filters, [
            'mode' => (string) ($input['markup_mode'] ?? 'conditional'),
            'rule_id' => (int) ($input['rule_id'] ?? 0),
        ]);

        out_json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    out_json(['success' => false, 'message' => 'Actiune invalida.'], 422);
    } catch (Throwable $e) {
        out_json(['success' => false, 'message' => 'Eroare: ' . $e->getMessage()], 500);
    }
}
}

if (!defined('IMPORT_ACTION_SKIP_HTTP')) {
    import_dispatch_queue_http_action();
}
