<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Scan;

use Config\Database;
use Evasystem\Controllers\AdaosComercial\AdaosComercialService;
use Evasystem\Controllers\Furnizori\FurnizoriStatsService;
use Evasystem\Controllers\Furnizori\SupplierFeedFolderService;
use Evasystem\Core\Furnizori\FurnizoriModel;
use PDO;
use Throwable;

/**
 * Import automat din folderele furnizor (Cron Sync).
 *
 * Mod dual (implicit): scan toate CSV-urile → ulei/lichide pe vitrină (max 8) + piese în catalog.
 * Mod consumables: doar ulei · lichide · electrice (vechi).
 */
final class SupplierCronImportService
{
    private const STATE_FILE = '/storage/supplier_cron_import_state.json';

    public function __construct(
        private readonly ?SupplierFeedFolderService $feed = null,
        private readonly ?FurnizoriStatsService $stats = null,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $supplierRows
     * @return array<string, mixed>
     */
    public function run(array $supplierRows, SupplierScanDashboardService $dash, bool $force = true): array
    {
        require_once dirname(__DIR__, 3) . '/config/cron_import.php';
        $this->bootImportLibs();
        ScanService::assertNotStopped();

        $dualMode = admin_cron_import_mode() === 'dual';
        $limit = admin_cron_import_limit();
        $consumablesOnly = admin_cron_consumables_only();
        $autoPublish = admin_cron_auto_publish_consumables();
        $importableSuppliers = array_values(array_filter(
            $supplierRows,
            static fn ($row): bool => is_array($row)
                && trim((string) ($row['supplier_code'] ?? $row['code'] ?? '')) !== ''
                && (int) ($row['randomn_id'] ?? 0) > 0
        ));
        $importSupplierTotal = count($importableSuppliers);

        $dash->updateProgress([
            'pct' => 42,
            'phase' => 'sync',
            'phase_label' => 'Import produse',
            'message' => $dualMode ? 'Import automat — mod dual' : 'Import automat — mod consumabile',
            'supplier_total' => $importSupplierTotal,
            'supplier_index' => 0,
        ]);

        if ($dualMode) {
            $dash->log(
                'Import automat — mod dual. ' . import_cron_rules_log_line()
                . ($autoPublish ? ' → publicare directă magazin.' : ' → coadă importreview.'),
                '',
                'info',
                'sync'
            );
        } else {
            $dash->log(
                'Import automat pornit — limită ' . $limit . ' produse'
                . ($consumablesOnly ? ' (doar consumabile: ulei · lichide · electrice)' : '')
                . ($autoPublish ? ' → publicare directă magazin + vitrină (ca /admin/import).' : ' → coadă importreview.'),
                '',
                'info',
                'sync'
            );
        }

        $pdo = Database::getDB();
        $markupService = new AdaosComercialService();
        $dash->updateProgress([
            'pct' => 45,
            'message' => 'Construiesc index preț multi-furnizor…',
        ]);
        $priceIndex = $this->buildMultiSupplierPriceIndex($supplierRows, $dash);
        if (function_exists('import_price_index_size') && import_price_index_size($priceIndex) > 0) {
            $dash->log(
                'Index preț multi-furnizor: ' . number_format(import_price_index_size($priceIndex), 0, ',', '.') . ' coduri',
                '',
                'info',
                'sync'
            );
        }

        $vitrinaSlots = $dualMode
            ? max(0, admin_cron_vitrina_limit() - import_cron_count_vitrina_products($pdo))
            : 0;
        $catalogSlots = $dualMode ? admin_cron_catalog_limit() : 0;
        $remaining = $limit;

        if ($dualMode) {
            $dash->log(
                'Sloturi rulare: vitrină ' . $vitrinaSlots . '/' . admin_cron_vitrina_limit()
                . ', catalog ' . $catalogSlots . ' piese',
                '',
                'info',
                'sync'
            );
        }

        $totals = [
            'limit' => $dualMode ? ($vitrinaSlots + $catalogSlots) : $limit,
            'parsed' => 0,
            'queued' => 0,
            'published' => 0,
            'added' => 0,
            'updated' => 0,
            'with_image' => 0,
            'without_price' => 0,
            'tecdoc_enriched' => 0,
            'vitrina' => 0,
            'catalog' => 0,
            'epiesa_checked' => 0,
            'epiesa_found' => 0,
            'suppliers_touched' => 0,
            'errors' => [],
        ];

        $importSupplierIndex = 0;

        foreach ($supplierRows as $row) {
            ScanService::assertNotStopped();

            if ($dualMode) {
                if ($vitrinaSlots <= 0 && $catalogSlots <= 0) {
                    break;
                }
            } elseif ($remaining <= 0) {
                break;
            }

            if (!is_array($row)) {
                continue;
            }

            $code = strtoupper(trim((string) ($row['supplier_code'] ?? $row['code'] ?? '')));
            $randomnId = (int) ($row['randomn_id'] ?? 0);
            if ($code === '' || $randomnId <= 0) {
                continue;
            }

            ++$importSupplierIndex;
            $syncPct = 50 + (int) round(44 * ($importSupplierIndex / max(1, $importSupplierTotal)));
            $dash->updateProgress([
                'pct' => $syncPct,
                'phase' => 'sync',
                'phase_label' => 'Import produse',
                'message' => 'Procesez furnizor ' . $code . ' (' . $importSupplierIndex . '/' . $importSupplierTotal . ')',
                'supplier' => $code,
                'supplier_index' => $importSupplierIndex,
                'supplier_total' => $importSupplierTotal,
            ]);

            $supplierFiles = $dualMode
                ? $this->resolveAllFeedCsvPaths($code, $randomnId, $row)
                : [];

            if ($dualMode) {
                if ($supplierFiles === []) {
                    $folderRel = $this->feed()->folderRelative($code, $randomnId);
                    $dash->log('Fără CSV în ' . $folderRel, $code, 'warn', 'sync');
                    continue;
                }

                $dash->log('Scan ' . count($supplierFiles) . ' fișier(e) CSV', $code, 'info', 'sync');

                try {
                    $batch = $this->importFromFileDual(
                        $pdo,
                        $markupService,
                        $dash,
                        $code,
                        $supplierFiles,
                        $vitrinaSlots,
                        $catalogSlots,
                        $priceIndex
                    );
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $totals['errors'][] = $code . ': ' . $msg;
                    $dash->log('Eroare import dual: ' . $msg, $code, 'error', 'sync');
                    continue;
                }

                $vitrinaSlots = (int) ($batch['vitrina_slots_left'] ?? $vitrinaSlots);
                $catalogSlots = (int) ($batch['catalog_slots_left'] ?? $catalogSlots);
            } else {
                $filePath = $this->resolveFeedCsvPath($code, $randomnId, $row);
                if ($filePath === null) {
                    $folderRel = $this->feed()->folderRelative($code, $randomnId);
                    $dash->log('Fără CSV în ' . $folderRel, $code, 'warn', 'sync');
                    continue;
                }

                $fileHash = hash_file('sha256', $filePath) ?: '';
                if (!$force && $this->wasFileImported($code, basename($filePath), $fileHash)) {
                    $dash->log('Fișier deja importat (skip) — ' . basename($filePath), $code, 'info', 'idle');

                    continue;
                }

                $dash->log('Citesc CSV: ' . basename($filePath), $code, 'info', 'sync');

                try {
                    $batch = $this->importFromFile(
                        $pdo,
                        $markupService,
                        $dash,
                        $code,
                        $filePath,
                        $remaining,
                        $priceIndex
                    );
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $totals['errors'][] = $code . ': ' . $msg;
                    $dash->log('Eroare import: ' . $msg, $code, 'error', 'sync');
                    continue;
                }

                $this->markFileImported($code, basename($filePath), $fileHash, (int) ($batch['queued'] ?? $batch['published'] ?? 0));
            }

            $queued = (int) ($batch['queued'] ?? $batch['published'] ?? 0);
            if ($queued <= 0 && (int) ($batch['parsed'] ?? 0) <= 0) {
                $dash->log('Niciun produs valid în fișier(e).', $code, 'warn', 'sync');
                continue;
            }

            ++$totals['suppliers_touched'];
            $totals['parsed'] += (int) ($batch['parsed'] ?? 0);
            $totals['queued'] += $queued;
            $totals['published'] += $queued;
            $totals['added'] += (int) ($batch['added'] ?? 0);
            $totals['updated'] += (int) ($batch['updated'] ?? 0);
            $totals['with_image'] += (int) ($batch['with_image'] ?? 0);
            $totals['without_price'] += (int) ($batch['without_price'] ?? 0);
            $totals['tecdoc_enriched'] += (int) ($batch['tecdoc_enriched'] ?? 0);
            $totals['vitrina'] += (int) ($batch['vitrina'] ?? $batch['vitrina_candidates'] ?? 0);
            $totals['catalog'] += (int) ($batch['catalog'] ?? 0);
            $totals['epiesa_checked'] += (int) ($batch['epiesa_checked'] ?? 0);
            $totals['epiesa_found'] += (int) ($batch['epiesa_found'] ?? 0);
            $remaining -= $queued;

            $dash->log(
                $code . ': ' . $queued . ' publicate'
                . ((int) ($batch['vitrina'] ?? 0) > 0 ? ', vitrină ' . (int) $batch['vitrina'] : '')
                . ((int) ($batch['catalog'] ?? 0) > 0 ? ', catalog ' . (int) $batch['catalog'] : '')
                . ' (' . (int) ($batch['with_image'] ?? 0) . ' cu imagine)',
                $code,
                'ok',
                'sync'
            );
        }

        if ($dualMode && function_exists('import_cron_cap_vitrina_fluids')) {
            $kept = import_cron_cap_vitrina_fluids($pdo, admin_cron_vitrina_limit());
            if ($kept > 0) {
                $dash->log('Vitrină homepage: max ' . admin_cron_vitrina_limit() . ' produse (' . $kept . ' active).', '', 'info', 'sync');
            }
        }

        $summary = $this->buildSummaryMessage($totals, $dualMode);
        $dash->updateProgress([
            'pct' => 98,
            'message' => $summary,
        ]);
        $dash->log($summary, '', $totals['queued'] > 0 ? 'ok' : 'info', 'sync');

        return [
            'import_summary' => $totals,
            'import_message' => $summary,
        ];
    }

    /**
     * Mod dual: vitrină (fluide) + catalog (piese).
     *
     * @param array<int, array{path:string,name:string}> $supplierFiles
     * @return array<string, mixed>
     */
    private function importFromFileDual(
        PDO $pdo,
        AdaosComercialService $markupService,
        SupplierScanDashboardService $dash,
        string $supplierCode,
        array $supplierFiles,
        int $vitrinaSlots,
        int $catalogSlots,
        array $priceIndex = []
    ): array {
        require_once dirname(__DIR__, 3) . '/config/cron_import.php';

        $stream = import_cron_dual_scan_supplier_stream(
            $supplierFiles,
            $markupService,
            $priceIndex,
            $vitrinaSlots,
            $catalogSlots
        );

        $vitrinaProducts = $stream['vitrina'] ?? [];
        $catalogProducts = $stream['catalog'] ?? [];
        $parsedTotal = (int) ($stream['total_scanned'] ?? 0);

        $dash->log(
            'Rezultat scan: vitrină ' . count($vitrinaProducts) . ', catalog ' . count($catalogProducts)
            . ' / ' . $parsedTotal . ' rânduri',
            $supplierCode,
            'info',
            'sync'
        );

        $logger = static function (string $msg, string $level = 'info') use ($dash, $supplierCode): void {
            $dash->log($msg, $supplierCode, $level, 'sync');
        };

        $merged = [
            'parsed' => $parsedTotal,
            'published' => 0,
            'queued' => 0,
            'added' => 0,
            'updated' => 0,
            'with_image' => 0,
            'vitrina' => 0,
            'catalog' => 0,
            'vitrina_slots_left' => $vitrinaSlots,
            'catalog_slots_left' => $catalogSlots,
        ];

        if (!admin_cron_auto_publish_consumables()) {
            $all = array_merge($vitrinaProducts, $catalogProducts);
            if ($all === []) {
                return $merged;
            }

            $stageStats = import_stage_products_for_review($pdo, $all, $markupService, [
                'epiesa_special_products' => true,
                'supplier_code' => $supplierCode,
                'cron_sync' => true,
                'price_index' => $priceIndex,
                'logger' => $logger,
            ]);
            $stageStats['parsed'] = $parsedTotal;
            $stageStats['vitrina_slots_left'] = $vitrinaSlots;
            $stageStats['catalog_slots_left'] = $catalogSlots;

            return $stageStats;
        }

        if ($vitrinaProducts !== [] && $vitrinaSlots > 0) {
            $dash->log('Public vitrină: ' . count($vitrinaProducts) . ' ulei/lichide', $supplierCode, 'info', 'sync');
            $vStats = import_consumable_cron_publish_batch($pdo, $vitrinaProducts, $markupService, [
                'limit' => $vitrinaSlots,
                'supplier_code' => $supplierCode,
                'check_epiesa' => false,
                'always_vitrina' => true,
                'skip_tecdoc_csv' => true,
                'price_index' => $priceIndex,
                'stage_incomplete' => admin_cron_stage_incomplete(),
                'logger' => $logger,
            ]);
            $merged['published'] += (int) ($vStats['published'] ?? 0);
            $merged['added'] += (int) ($vStats['added'] ?? 0);
            $merged['updated'] += (int) ($vStats['updated'] ?? 0);
            $merged['with_image'] += (int) ($vStats['with_image'] ?? 0);
            $merged['vitrina'] += (int) ($vStats['vitrina'] ?? 0);
            $merged['vitrina_slots_left'] = max(0, $vitrinaSlots - (int) ($vStats['published'] ?? 0));
        }

        if ($catalogProducts !== [] && $catalogSlots > 0) {
            $dash->log('Public catalog: ' . count($catalogProducts) . ' piese auto', $supplierCode, 'info', 'sync');
            $cStats = import_cron_catalog_publish_batch($pdo, $catalogProducts, $markupService, [
                'limit' => $catalogSlots,
                'supplier_code' => $supplierCode,
                'logger' => $logger,
            ]);
            $merged['published'] += (int) ($cStats['published'] ?? 0);
            $merged['added'] += (int) ($cStats['added'] ?? 0);
            $merged['updated'] += (int) ($cStats['updated'] ?? 0);
            $merged['with_image'] += (int) ($cStats['with_image'] ?? 0);
            $merged['catalog'] += (int) ($cStats['catalog'] ?? 0);
            $merged['catalog_slots_left'] = max(0, $catalogSlots - (int) ($cStats['published'] ?? 0));
        }

        $merged['queued'] = $merged['published'];

        return $merged;
    }

    /** @return array<string, mixed> */
    private function importFromFile(
        PDO $pdo,
        AdaosComercialService $markupService,
        SupplierScanDashboardService $dash,
        string $supplierCode,
        string $filePath,
        int $maxProducts,
        array $priceIndex = []
    ): array {
        require_once dirname(__DIR__, 3) . '/config/cron_import.php';

        $filename = basename($filePath);
        $priceIndex = function_exists('import_build_price_index')
            ? import_build_price_index([['path' => $filePath, 'name' => $filename]])
            : [];

        if (admin_cron_consumables_only()) {
            $categories = admin_cron_consumable_categories();
            $priorityFluids = function_exists('admin_cron_priority_fluids_only') && admin_cron_priority_fluids_only();
            if ($priorityFluids) {
                $dash->log('Filtru cron: doar ulei + lichide (fără becuri/siguranțe).', $supplierCode, 'info', 'sync');
            }
            $stream = import_consumable_scan_supplier_stream(
                [['path' => $filePath, 'name' => $filename]],
                $categories,
                $markupService,
                $priceIndex,
                $maxProducts,
                '',
                $priorityFluids
            );
            $products = $stream['products'];
            $parsedTotal = (int) ($stream['total_scanned'] ?? 0);

            $dash->log(
                'Scan consumabile (fișier integral): ' . count($products) . ' potriviri / '
                . $parsedTotal . ' rânduri',
                $supplierCode,
                $products === [] ? 'warn' : 'info',
                'sync'
            );

            if ($products === []) {
                return ['parsed' => $parsedTotal, 'queued' => 0];
            }
        } else {
            $catalog = import_build_supplier_catalog(
                [['path' => $filePath, 'name' => $filename]],
                $maxProducts * 8,
                ''
            );
            $entries = is_array($catalog['entries'] ?? null) ? $catalog['entries'] : [];
            $parsedTotal = count($entries);

            $products = [];
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $product = import_consumable_entry_to_product($entry, $markupService, $priceIndex);
                if ($product !== null) {
                    $products[] = $product;
                }
            }

            if ($products === []) {
                return ['parsed' => $parsedTotal, 'queued' => 0];
            }

            if (count($products) > $maxProducts) {
                $products = array_slice($products, 0, $maxProducts);
            }
        }

        $logger = static function (string $msg, string $level = 'info') use ($dash, $supplierCode): void {
            $dash->log($msg, $supplierCode, $level, 'sync');
        };

        if (admin_cron_auto_publish_consumables()) {
            $dash->log(
                'Publicare ' . count($products) . ' consumabile — preț + imagine (pipeline Scraper) + magazin + vitrină',
                $supplierCode,
                'info',
                'sync'
            );

            $stageStats = import_consumable_cron_publish_batch($pdo, $products, $markupService, [
                'limit' => $maxProducts,
                'supplier_code' => $supplierCode,
                'check_epiesa' => admin_cron_check_epiesa(),
                'always_vitrina' => admin_cron_always_vitrina(),
                'skip_tecdoc_csv' => admin_cron_skip_tecdoc_csv_lookup(),
                'price_index' => $priceIndex,
                'stage_incomplete' => admin_cron_stage_incomplete(),
                'logger' => $logger,
            ]);
            $stageStats['parsed'] = $parsedTotal;

            if (($stageStats['published'] ?? 0) > 0) {
                $dash->log(
                    (int) $stageStats['published'] . ' publicate în magazin, '
                    . (int) ($stageStats['vitrina'] ?? 0) . ' pe vitrină',
                    $supplierCode,
                    'ok',
                    'sync'
                );
            }

            return $stageStats;
        }

        $dash->log(
            'Parsate ' . count($products) . ' produse — enrich TecDoc + imagine (ca Import)…',
            $supplierCode,
            'info',
            'sync'
        );

        $stageStats = import_stage_products_for_review($pdo, $products, $markupService, [
            'epiesa_special_products' => true,
            'supplier_code' => $supplierCode,
            'cron_sync' => true,
            'price_index' => $priceIndex,
            'logger' => $logger,
        ]);

        $stageStats['parsed'] = $parsedTotal;

        if ($stageStats['queued'] > 0) {
            $dash->log(
                $stageStats['queued'] . ' produse în coadă — verifică în /admin/importreview',
                $supplierCode,
                'ok',
                'sync'
            );
        }

        return $stageStats;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, array{path:string,name:string}>
     */
    private function resolveAllFeedCsvPaths(string $code, int $randomnId, array $row): array
    {
        $files = [];
        $scanFile = trim((string) ($row['scan_file'] ?? ''));
        if ($scanFile !== '') {
            $folder = $this->feed()->folderPath($code, $randomnId);
            $candidate = $folder . DIRECTORY_SEPARATOR . $scanFile;
            if (is_file($candidate)) {
                $files[] = ['path' => $candidate, 'name' => basename($candidate)];
            }
        }

        foreach ($this->feed()->listFeedCsvFiles($code, $randomnId) as $entry) {
            $path = (string) ($entry['local_path'] ?? '');
            $name = (string) ($entry['name'] ?? basename($path));
            if ($path === '' || !is_file($path)) {
                continue;
            }
            if (!preg_match('/\.(csv|txt|tsv|xlsx)$/i', $name)) {
                continue;
            }
            $key = realpath($path) ?: $path;
            $seen = array_column($files, 'path');
            $resolved = array_map(static fn (string $p): string => realpath($p) ?: $p, $seen);
            if (in_array($key, $resolved, true)) {
                continue;
            }
            $files[] = ['path' => $path, 'name' => $name];
        }

        return $files;
    }

    /** @param array<string, mixed> $row */
    private function resolveFeedCsvPath(string $code, int $randomnId, array $row): ?string
    {
        $all = $this->resolveAllFeedCsvPaths($code, $randomnId, $row);

        return $all[0]['path'] ?? null;
    }

    private function wasFileImported(string $code, string $filename, string $hash): bool
    {
        $state = $this->loadState();
        $entry = is_array($state[$code] ?? null) ? $state[$code] : [];

        return ($entry['filename'] ?? '') === $filename
            && ($entry['sha256'] ?? '') === $hash
            && (int) ($entry['products'] ?? 0) > 0;
    }

    private function markFileImported(string $code, string $filename, string $hash, int $products): void
    {
        $state = $this->loadState();
        $state[$code] = [
            'filename' => $filename,
            'sha256' => $hash,
            'products' => $products,
            'imported_at' => date('c'),
        ];
        $this->saveState($state);
    }

    /** @return array<string, mixed> */
    private function loadState(): array
    {
        $path = dirname(__DIR__, 3) . self::STATE_FILE;
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $state */
    private function saveState(array $state): void
    {
        $path = dirname(__DIR__, 3) . self::STATE_FILE;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** @param array<string, mixed> $totals */
    private function buildSummaryMessage(array $totals, bool $dualMode = false): string
    {
        require_once dirname(__DIR__, 3) . '/config/cron_import.php';

        $queued = (int) ($totals['queued'] ?? 0);
        if ($queued <= 0) {
            if ($dualMode) {
                return 'Import dual: 0 produse — scan toate CSV din supplier_feeds; vitrină ulei/lichide (max 8), restul piese în catalog.';
            }

            return 'Import: 0 consumabile publicate — verifică CSV în admin/storage/supplier_feeds/{cod}/ (ulei · lichide · electrice cu preț).';
        }

        if ($dualMode) {
            $msg = 'Import dual finalizat: ' . $queued . ' produse';
            $msg .= ' (vitrină ' . (int) ($totals['vitrina'] ?? 0) . ', catalog ' . (int) ($totals['catalog'] ?? 0) . ')';
            $added = (int) ($totals['added'] ?? 0);
            $updated = (int) ($totals['updated'] ?? 0);
            if ($added > 0 || $updated > 0) {
                $msg .= ' — +' . $added . ' noi, ~' . $updated . ' actualizate';
            }

            return $msg . '.';
        }

        $msg = 'Import finalizat: ' . $queued . '/' . (int) ($totals['limit'] ?? 10);
        if (admin_cron_auto_publish_consumables()) {
            $msg .= ' consumabile publicate în magazin';
            $added = (int) ($totals['added'] ?? 0);
            $updated = (int) ($totals['updated'] ?? 0);
            if ($added > 0 || $updated > 0) {
                $msg .= ' (+' . $added . ' noi, ~' . $updated . ' actualizate)';
            }
        } else {
            $msg .= ' produse în coadă importreview — deschide /admin/importreview pentru publicare.';
        }
        $epiesa = (int) ($totals['epiesa_found'] ?? 0);
        $vitrina = (int) ($totals['vitrina'] ?? 0);
        if ($epiesa > 0 || $vitrina > 0) {
            $msg .= ' ePiesa: ' . $epiesa . ', candidați vitrină: ' . $vitrina . '.';
        }

        return $msg;
    }

    private function bootImportLibs(): void
    {
        if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
            define('IMPORT_PRODUCE_SKIP_HTTP', true);
        }

        $base = dirname(__DIR__) . '/Produse';
        require_once $base . '/import_lib.php';
        require_once $base . '/import_supplier_lib.php';
        require_once $base . '/import_identity_lib.php';
        require_once $base . '/import_base_lib.php';
        require_once $base . '/import_tecdoc_lib.php';
        require_once $base . '/import_consumable_scan_lib.php';
        require_once $base . '/import_cron_dual_scan_lib.php';

        if (!function_exists('preview_products_from_file')) {
            require_once $base . '/importproduse.php';
        }

        if (!function_exists('import_sync_products_oem')) {
            require_once dirname(__DIR__, 4) . '/system/products_oem.php';
        }
    }

    private function feed(): SupplierFeedFolderService
    {
        return $this->feed ?? new SupplierFeedFolderService();
    }

    /**
     * Index preț din toate CSV-urile furnizor disponibile (prioritate multi-furnizor).
     *
     * @param array<int, array<string, mixed>> $supplierRows
     * @return array<string, mixed>
     */
    private function buildMultiSupplierPriceIndex(array $supplierRows, SupplierScanDashboardService $dash): array
    {
        if (!function_exists('import_build_price_index')) {
            return [];
        }

        $files = [];
        $seen = [];
        foreach ($supplierRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['supplier_code'] ?? $row['code'] ?? '')));
            $randomnId = (int) ($row['randomn_id'] ?? 0);
            if ($code === '' || $randomnId <= 0) {
                continue;
            }

            foreach ($this->resolveAllFeedCsvPaths($code, $randomnId, $row) as $fileRef) {
                $path = (string) ($fileRef['path'] ?? '');
                if ($path === '' || !is_file($path)) {
                    continue;
                }
                $key = realpath($path) ?: $path;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $files[] = ['path' => $path, 'name' => (string) ($fileRef['name'] ?? basename($path))];
            }
        }

        if ($files === []) {
            return [];
        }

        $dash->log('Construiesc index preț din ' . count($files) . ' fișier(e) furnizor…', '', 'info', 'sync');

        return import_build_price_index($files);
    }

    private function stats(): FurnizoriStatsService
    {
        return $this->stats ?? new FurnizoriStatsService(new FurnizoriModel());
    }
}
