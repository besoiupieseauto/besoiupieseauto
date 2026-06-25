<?php

declare(strict_types=1);

use Evasystem\Controllers\AdaosComercial\AdaosComercialService;

/**
 * Reguli clare Cron Sync — mod dual:
 * 1) Scanează toate CSV-urile furnizor.
 * 2) Ulei + lichide → vitrină homepage (max 8), pVitrina=1.
 * 3) Restul pieselor (frâne, discuri, filtre…) → magazin cu categorie, pVitrina=0.
 */

function import_cron_rules_log_line(): string
{
    require_once dirname(__DIR__, 3) . '/config/cron_import.php';

    $v = function_exists('admin_cron_vitrina_limit') ? admin_cron_vitrina_limit() : 8;
    $c = function_exists('admin_cron_catalog_limit') ? admin_cron_catalog_limit() : 50;

    return 'Reguli: vitrină max ' . $v . ' (ulei·lichide) | catalog max ' . $c . ' piese/rulare | scan toate fișierele';
}

/** @param array<string, mixed> $product */
function import_cron_is_vitrina_fluid(array $product): bool
{
    return import_consumable_is_fluid_consumable($product);
}

/** @param array<string, mixed> $product */
function import_cron_is_catalog_part(array $product): bool
{
    if (!import_consumable_has_supplier_price($product)) {
        return false;
    }
    $code = trim((string) ($product['pCode'] ?? ''));
    $name = trim((string) ($product['pName'] ?? ''));
    if ($code === '' || $name === '') {
        return false;
    }

    // Fluidele merg doar pe vitrină, nu și în catalog în aceeași rulare.
    if (import_cron_is_vitrina_fluid($product)) {
        return false;
    }

    return true;
}

/** @param array<string, mixed> $product */
function import_cron_apply_catalog_taxonomy(array &$product): void
{
    if (!function_exists('import_category_from_keywords')) {
        return;
    }

    $name = trim((string) ($product['pName'] ?? ''));
    $sub = trim((string) ($product['pSubcategory'] ?? ''));
    $rawName = function_exists('import_consumable_supplier_raw_name')
        ? import_consumable_supplier_raw_name($product)
        : '';
    $hay = trim($name . ' ' . $sub . ' ' . $rawName);

    if (import_cron_is_vitrina_fluid($product)) {
        $defs = import_consumable_category_defs();
        $cats = import_consumable_detect_categories($product);
        $product['pCategory'] = $defs[$cats[0] ?? 'ulei']['label'] ?? 'Ulei & Lichide';
        if ($sub === '') {
            $product['pSubcategory'] = function_exists('import_base_normalize_product_name')
                ? import_base_normalize_product_name($rawName !== '' ? $rawName : $name)
                : $name;
        }

        return;
    }

    $category = import_category_from_keywords($hay);
    if ($category !== '') {
        $product['pCategory'] = $category;
    } elseif (trim((string) ($product['pCategory'] ?? '')) === '') {
        $product['pCategory'] = 'Piese auto';
    }

    if ($sub === '' && function_exists('import_base_normalize_product_name')) {
        $normalized = import_base_normalize_product_name($rawName !== '' ? $rawName : $name);
        if ($normalized !== '') {
            $product['pSubcategory'] = $normalized;
        }
    }
}

/**
 * Parcurge CSV și separă fluide (vitrină) vs piese catalog.
 *
 * @param array<int, array{path:string,name:string}> $supplierFiles
 * @return array{
 *   vitrina: array<int, array<string, mixed>>,
 *   catalog: array<int, array<string, mixed>>,
 *   total_scanned: int
 * }
 */
function import_cron_dual_scan_supplier_stream(
    array $supplierFiles,
    AdaosComercialService $markupService,
    array $priceIndex,
    int $maxVitrinaSlots,
    int $maxCatalogSlots
): array {
    if (!function_exists('import_stream_supplier_file')) {
        require_once __DIR__ . '/import_supplier_lib.php';
    }

    $vitrina = [];
    $catalog = [];
    $totalScanned = 0;
    $bestVitrina = [];
    $bestCatalog = [];

    foreach ($supplierFiles as $file) {
        $path = (string) ($file['path'] ?? '');
        $filename = (string) ($file['name'] ?? basename($path));
        if ($path === '' || !is_file($path)) {
            continue;
        }

        $kind = import_classify_file($path, $filename);
        $supplierType = str_starts_with($kind, 'supplier:') ? substr($kind, 9) : null;
        if ($supplierType === null) {
            continue;
        }

        $stop = false;
        import_stream_supplier_file(
            $path,
            $filename,
            $supplierType,
            static function (array $row, string $type) use (
                &$vitrina,
                &$catalog,
                &$totalScanned,
                &$bestVitrina,
                &$bestCatalog,
                &$stop,
                $markupService,
                $priceIndex,
                $maxVitrinaSlots,
                $maxCatalogSlots
            ): void {
                if (count($vitrina) >= $maxVitrinaSlots && count($catalog) >= $maxCatalogSlots) {
                    $stop = true;

                    return;
                }

                if (!function_exists('import_supplier_row_passes_supplier_scan_rules')) {
                    require_once __DIR__ . '/import_supplier_stock_zero_lib.php';
                }
                if (!import_supplier_row_passes_supplier_scan_rules($row, $type)) {
                    return;
                }

                $entry = import_supplier_row_to_entry($row, $type);
                if ($entry === null) {
                    return;
                }

                ++$totalScanned;

                $product = import_consumable_entry_to_product($entry, $markupService, $priceIndex);
                if ($product === null || !import_consumable_has_supplier_price($product)) {
                    return;
                }

                $key = (string) ($product['pCode'] ?? '') . '|' . str_replace(
                    ' ',
                    '',
                    import_normalize_supplier_brand((string) ($product['pBrand'] ?? ''))
                );

                if (import_cron_is_vitrina_fluid($product) && count($vitrina) < $maxVitrinaSlots) {
                    if (!isset($bestVitrina[$key])) {
                        import_cron_apply_catalog_taxonomy($product);
                        $bestVitrina[$key] = $product;
                        $vitrina[] = $product;
                    }

                    return;
                }

                if (count($catalog) >= $maxCatalogSlots) {
                    return;
                }

                if (!import_cron_is_catalog_part($product)) {
                    return;
                }

                if (import_cron_is_vitrina_fluid($product)) {
                    return;
                }

                if (isset($bestCatalog[$key])) {
                    return;
                }

                import_cron_apply_catalog_taxonomy($product);
                $bestCatalog[$key] = $product;
                $catalog[] = $product;
            },
            $stop
        );

        if ($stop) {
            break;
        }
    }

    return [
        'vitrina' => array_slice($vitrina, 0, $maxVitrinaSlots),
        'catalog' => array_slice($catalog, 0, $maxCatalogSlots),
        'total_scanned' => $totalScanned,
    ];
}

/**
 * Publică piese catalog (fără vitrină).
 *
 * @param array<int, array<string, mixed>> $products
 * @return array<string, int>
 */
function import_cron_catalog_publish_batch(
    PDO $pdo,
    array $products,
    AdaosComercialService $markupService,
    array $options = []
): array {
    require_once dirname(__DIR__, 3) . '/config/cron_import.php';

    $limit = max(1, min(200, (int) ($options['limit'] ?? admin_cron_catalog_limit())));
    $supplierCode = strtoupper(trim((string) ($options['supplier_code'] ?? '')));
    $logger = $options['logger'] ?? null;
    $publishMode = admin_cron_import_publish_mode();
    $lightEnrich = function_exists('admin_cron_light_enrich') && admin_cron_light_enrich();

    $stats = [
        'published' => 0,
        'added' => 0,
        'updated' => 0,
        'skipped' => 0,
        'with_image' => 0,
        'catalog' => 0,
    ];

    $log = static function (string $message, string $level = 'info') use ($logger): void {
        if (is_callable($logger)) {
            $logger($message, $level);
        }
    };

    $enrichOpts = [
        'light' => $lightEnrich,
        'skip_csv_scan' => true,
        'skip_tecdoc_api' => true,
    ];

    foreach ($products as $product) {
        if ($stats['published'] >= $limit) {
            break;
        }
        if (!is_array($product)) {
            continue;
        }

        if ($supplierCode !== '') {
            $product['pSupplier'] = trim((string) ($product['pSupplier'] ?? $supplierCode));
        }

        import_cron_apply_catalog_taxonomy($product);

        try {
            $product = import_consumable_enrich_product_row($product, null, true, $enrichOpts);
            $catalogImageMode = function_exists('import_consumable_image_source_mode')
                ? import_consumable_image_source_mode($product)
                : 'auto';
            $product = import_consumable_resolve_image($product, null, $catalogImageMode);
        } catch (Throwable $e) {
            $log('Catalog skip enrich: ' . $e->getMessage(), 'warn');
        }

        $staging = import_consumable_build_staging_row($product, $markupService);
        if (trim((string) ($staging['pPrice'] ?? '')) === '') {
            ++$stats['skipped'];
            continue;
        }

        $staging = import_cron_light_prepare_row($staging);
        $staging['__force_image_update'] = true;
        $raw = json_decode((string) ($staging['raw_json'] ?? '{}'), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw['cron_catalog_part'] = true;
        $staging['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!function_exists('besoiu_import_row_blocks_auto_publish')) {
            require_once dirname(__DIR__, 4) . '/system/import-queue-critical.php';
        }
        if (besoiu_import_row_blocks_auto_publish($staging)) {
            ++$stats['skipped'];
            if (!isset($stats['blocked_critical'])) {
                $stats['blocked_critical'] = 0;
            }
            ++$stats['blocked_critical'];
            $log('Catalog sărit — date critice lipsă (categorie/brand/preț/imagine).', 'warn');
            continue;
        }

        $result = import_publish_prepared_row($pdo, $staging, $publishMode);
        $action = (string) ($result['action'] ?? 'skipped');
        ++$stats['published'];
        ++$stats['catalog'];

        if ($action === 'inserted') {
            ++$stats['added'];
        } elseif ($action === 'updated') {
            ++$stats['updated'];
        } else {
            ++$stats['skipped'];
        }

        if (function_exists('import_row_image_url') && import_row_image_url($staging) !== '') {
            ++$stats['with_image'];
        }
    }

    return $stats;
}

function import_cron_count_vitrina_products(PDO $pdo): int
{
    try {
        if (!function_exists('tecdoc_ensure_vitrina_column')) {
            require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';
        }
        tecdoc_ensure_vitrina_column($pdo);

        return (int) $pdo->query(
            "SELECT COUNT(*) FROM produse WHERE status <> '0' AND pVitrina = 1"
        )->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

/** Păstrează pe vitrină doar primele N produse fluide (restul pVitrina=0). */
function import_cron_cap_vitrina_fluids(PDO $pdo, int $max = 8): int
{
    if ($max <= 0) {
        return 0;
    }

    try {
        if (!function_exists('tecdoc_ensure_vitrina_column')) {
            require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';
        }
        tecdoc_ensure_vitrina_column($pdo);

        $stmt = $pdo->query(
            "SELECT id FROM produse WHERE status <> '0' AND pVitrina = 1 ORDER BY id DESC"
        );
        $ids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if (!is_array($ids) || count($ids) <= $max) {
            return is_array($ids) ? count($ids) : 0;
        }

        $keep = array_slice(array_map('intval', $ids), 0, $max);
        $placeholders = implode(',', array_fill(0, count($keep), '?'));
        $clear = $pdo->prepare(
            "UPDATE produse SET pVitrina = 0 WHERE pVitrina = 1 AND id NOT IN ($placeholders)"
        );
        $clear->execute($keep);

        return count($keep);
    } catch (Throwable) {
        return 0;
    }
}
