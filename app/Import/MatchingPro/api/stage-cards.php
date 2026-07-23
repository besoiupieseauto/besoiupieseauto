<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/ImportCardStaging.php';

/** Max carduri per request HTTP — frontend-ul trimite pe loturi. */
const IMPORT_STAGE_CARDS_MAX_PER_REQUEST = 25;

@ini_set('memory_limit', '1024M');
@set_time_limit(300);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);
}

import_motor_api_run(static function (): void {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    /** @var list<array<string, mixed>> $cards */
    $cards = $data['cards'] ?? [];
    if (!is_array($cards) || $cards === []) {
        import_json_response(['success' => false, 'error' => 'Lipsește lista cards'], 400);
    }

    $importLane = trim((string) ($data['import_lane'] ?? 'standard'));
    if (!in_array($importLane, ['standard', 'showcase'], true)) {
        $importLane = 'standard';
    }

    import_motor_boot_furnizori_libs();
    import_motor_boot_admin_stack();
    if (!import_motor_ensure_database()) {
        import_json_response(['success' => false, 'error' => 'Baza de date indisponibilă'], 503);
    }

    if (class_exists(\Besoiu\Services\ImportReviewQueueService::class)) {
        try {
            (new \Besoiu\Services\ImportReviewQueueService())->ensureImportLaneColumn();
        } catch (Throwable) {
            // coloană opțională
        }
    }

    if (!function_exists('import_apply_taxonomy_gaps')
        || !function_exists('import_sanitize_motorizare_value')
        || !function_exists('import_stage_products_for_review')
    ) {
        \Besoiu\Services\Import\ImportLibLoader::bootFull(skipHttp: true);
    }

    $cards = import_motor_normalize_card_list($cards);
    $receivedCount = count($cards);
    if ($receivedCount > IMPORT_STAGE_CARDS_MAX_PER_REQUEST) {
        import_json_response([
            'success' => false,
            'error' => 'Maxim ' . IMPORT_STAGE_CARDS_MAX_PER_REQUEST
                . ' carduri pe request. Trimite pe loturi (frontend chunking).',
            'received' => $receivedCount,
            'max_per_request' => IMPORT_STAGE_CARDS_MAX_PER_REQUEST,
        ], 413);
    }

    // Matching Pro: cardurile sunt deja TecDoc + vitrină — fără scrape ePiesa (blochează 2–4 min / fatal).
    $stagingOpts = [
        'use_ollama' => false,
        'skip_category_match' => true,
        'matching_pro_fast' => true,
    ];
    $stagingResult = ImportCardStaging::cardsToProductsDetailed($cards, $importLane, $stagingOpts);
    $products = $stagingResult['products'];
    $rejectedCards = $stagingResult['rejected'];
    $convertedCount = count($products);
    if ($products === []) {
        $reasons = array_map(
            static fn (array $r): string => ($r['key'] ?? '?') . ': ' . ($r['reason'] ?? 'respins'),
            array_slice($rejectedCards, 0, 5)
        );
        if ($reasons === []) {
            foreach ($cards as $card) {
                if (!is_array($card)) {
                    continue;
                }
                $name = trim((string) ($card['title'] ?? $card['matchedName'] ?? $card['name'] ?? ''));
                $code = trim((string) ($card['supplierSku'] ?? $card['sku'] ?? ''));
                if ($name === '' || $code === '') {
                    $reasons[] = 'Lipsește titlu sau SKU (cod: ' . ($code !== '' ? $code : '—') . ')';
                }
            }
        }
        import_json_response([
            'success' => false,
            'error' => 'Niciun card valid pentru coadă'
                . ($reasons !== [] ? ' — ' . implode('; ', array_slice(array_unique($reasons), 0, 3)) : ''),
            'received' => $receivedCount,
            'converted' => 0,
            'skipped_staging' => $receivedCount,
            'rejected' => $rejectedCards,
            'hint' => 'Verifică SKU + titlu; cardurile trebuie să aibă sku/title înainte de trimitere.',
        ], 422);
    }

    if (!function_exists('import_stage_products_for_review')) {
        \Besoiu\Services\Import\ImportLibLoader::bootFull(skipHttp: true);
    }

    if (!function_exists('import_stage_products_for_review')) {
        import_json_response(['success' => false, 'error' => 'Funcția import_stage_products_for_review lipsește'], 503);
    }

    $pdo = \Config\Database::getDB();
    $markup = new \Besoiu\Services\AdaosComercial\AdaosComercialService();
    $priceIndex = function_exists('import_build_multi_supplier_price_index')
        ? import_build_multi_supplier_price_index($pdo)
        : [];

    $stats = import_stage_products_for_review($pdo, $products, $markup, [
        'epiesa_special_products' => false,
        'import_lane' => $importLane,
        'price_index' => $priceIndex,
        // Matching Pro: fără Ollama + fără re-enrich TecDoc (cardurile sunt deja îmbogățite).
        'ollama_normalize' => false,
        'skip_tecdoc_enrich' => true,
        // Matching Pro: staging rapid — fără scraper imagini / TtcPozeCatalog / formare titlu.
        'skip_image_fetch' => true,
        'matching_pro_fast' => true,
    ]);

    $queuedShowcase = (int) ($stats['queued_showcase'] ?? 0);
    $queuedNoImage = (int) ($stats['queued_no_image'] ?? 0);
    $withImage = (int) ($stats['with_image'] ?? 0);

    import_json_response([
        'success' => true,
        'received' => $receivedCount,
        'converted' => $convertedCount,
        'skipped_staging' => max(0, $receivedCount - $convertedCount),
        'rejected' => $rejectedCards,
        'queued' => (int) ($stats['queued'] ?? 0),
        'updated_existing' => (int) ($stats['updated_existing'] ?? 0),
        'stage_errors' => (int) ($stats['stage_errors'] ?? 0),
        'batch_duplicates' => (int) ($stats['batch_duplicates'] ?? 0),
        'with_image' => $withImage,
        'without_price' => (int) ($stats['without_price'] ?? 0),
        'queued_showcase' => $queuedShowcase,
        'queued_no_image' => $queuedNoImage,
        'import_lane' => $importLane,
        'stats' => $stats,
        'message' => ($stats['queued'] ?? 0) . ' produse trimise în coada de import'
            . ((int) ($stats['updated_existing'] ?? 0) > 0
                ? ' (' . (int) $stats['updated_existing'] . ' actualizate în coadă)'
                : '')
            . ($queuedShowcase > 0 ? ' · ' . $queuedShowcase . ' → Produse vitrină (cu imagine)' : '')
            . ($queuedNoImage > 0 ? ' · ' . $queuedNoImage . ' → Produse fără imagine (țintă vitrină)' : ''),
    ]);
}, 'stage-cards.php');
