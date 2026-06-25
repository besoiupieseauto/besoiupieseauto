<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

use Config\Database;
use Evasystem\Core\Furnizori\FurnizoriModel;
use PDO;

require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';

/**
 * Statistici produse + sincronizare furnizori din import.
 */
final class FurnizoriStatsService
{
    private const IMPORT_SYNC_TTL_SECONDS = 120;

    private static ?int $importSyncAt = null;

    /** @var array<string, array{published?:int,queue?:int,queue_pending?:int}>|null */
    private ?array $productStatsCache = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $syncReportsCache = null;

    public function __construct(
        private readonly FurnizoriModel $furnizoriModel
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listWithStats(bool $syncImportSuppliers = true): array
    {
        if ($syncImportSuppliers) {
            $this->ensureImportSuppliersSynced();
        }

        $rows = array_values(array_filter(
            $this->furnizoriModel->findAll(),
            static function (array $row): bool {
                $code = function_exists('mb_strtoupper')
                    ? mb_strtoupper(trim((string) ($row['code'] ?? '')), 'UTF-8')
                    : strtoupper(trim((string) ($row['code'] ?? '')));
                $status = strtolower(trim((string) ($row['status'] ?? 'active')));

                return $code !== '' && $status !== 'deleted';
            }
        ));
        $statsByCode = $this->loadProductStatsBySupplierCode();

        return array_map(
            fn (array $row): array => $this->sanitizeClientSecrets($this->enrichRow($row, $statsByCode)),
            $rows
        );
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function listWithStatsPaginated(array $params = [], bool $syncImportSuppliers = false): array
    {
        if ($syncImportSuppliers) {
            $this->ensureImportSuppliersSynced(true);
        } else {
            $this->ensureImportSuppliersSynced();
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));
        $paged = $this->furnizoriModel->findPaginated($page, $perPage, $params);
        $statsByCode = $this->loadProductStatsBySupplierCode();

        $paged['items'] = array_values(array_filter(
            array_map(
                fn (array $row): array => $this->sanitizeClientSecrets($this->enrichRow($row, $statsByCode)),
                $paged['items']
            ),
            static function (array $row): bool {
                $code = function_exists('mb_strtoupper')
                    ? mb_strtoupper(trim((string) ($row['code'] ?? '')), 'UTF-8')
                    : strtoupper(trim((string) ($row['code'] ?? '')));
                $status = strtolower(trim((string) ($row['status'] ?? 'active')));

                return $code !== '' && $status !== 'deleted';
            }
        ));

        return $paged;
    }

    /** @return array<string, mixed> */
    public function findWithStats(int $randomId, bool $syncCatalog = false): array
    {
        if ($syncCatalog) {
            $this->ensureImportSuppliersSynced();
        }

        $row = $this->furnizoriModel->findByRandomId($randomId);
        if ($row === null) {
            return [];
        }

        $row = import_furnizori_resolve_credentials($row);

        $code = $this->normalizeSupplierCode((string) ($row['code'] ?? ''));
        if ($code === '') {
            return [];
        }

        $statsByCode = $this->loadProductStatsForSupplierCode($code);

        return $this->enrichRow($row, $statsByCode);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function listProductsForSupplier(
        string $supplierCode,
        int $limit = 50,
        int $offset = 0,
        string $scope = 'imported'
    ): array {
        $code = $this->normalizeSupplierCode($supplierCode);
        if ($code === '') {
            return ['items' => [], 'total' => 0];
        }

        $pdo = Database::getDB();
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $scope = mb_strtolower(trim($scope));

        if ($scope === 'imported' || $scope === 'magazin' || $scope === '') {
            return $this->listPublishedProductsForSupplier($pdo, $code, $limit, $offset);
        }

        if ($scope === 'queue') {
            return $this->listQueueProductsForSupplier($pdo, $code, $limit, $offset, ['pending']);
        }

        return $this->listAllProductsForSupplier($pdo, $code, $limit, $offset);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    private function listPublishedProductsForSupplier(PDO $pdo, string $code, int $limit, int $offset): array
    {
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM produse WHERE UPPER(TRIM(pSupplier)) = :code'
        );
        $countStmt->execute([':code' => $code]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT id, pCode AS code, pBrand AS brand, pName AS name, pPrice AS price, pStock AS stock,
                    pSupplier AS supplier, pState AS product_state, status
             FROM produse
             WHERE UPPER(TRIM(pSupplier)) = :code
             ORDER BY id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute([':code' => $code]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(
            fn (array $row): array => $this->normalizeProductListRow($row, 'magazin'),
            $rows
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param array<int, string> $statuses
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    private function listQueueProductsForSupplier(PDO $pdo, string $code, int $limit, int $offset, array $statuses): array
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_merge([$code], $statuses);

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM import_produse
             WHERE UPPER(TRIM(pSupplier)) = ?
               AND status IN (' . $placeholders . ')'
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT id, pCode AS code, pBrand AS brand, pName AS name, pPrice AS price, pStock AS stock,
                    pSupplier AS supplier, status
             FROM import_produse
             WHERE UPPER(TRIM(pSupplier)) = ?
               AND status IN (' . $placeholders . ')
             ORDER BY id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(
            fn (array $row): array => $this->normalizeProductListRow($row, 'coada'),
            $rows
        );

        return ['items' => $items, 'total' => $total];
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    private function listAllProductsForSupplier(PDO $pdo, string $code, int $limit, int $offset): array
    {
        $published = $this->listPublishedProductsForSupplier($pdo, $code, 10000, 0);

        $queueStmt = $pdo->prepare(
            'SELECT id, pCode AS code, pBrand AS brand, pName AS name, pPrice AS price, pStock AS stock,
                    pSupplier AS supplier, status
             FROM import_produse
             WHERE UPPER(TRIM(pSupplier)) = :code
             ORDER BY id DESC'
        );
        $queueStmt->execute([':code' => $code]);
        $queueRows = $queueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = $published['items'];
        foreach ($queueRows as $row) {
            $items[] = $this->normalizeProductListRow($row, 'coada');
        }

        usort($items, static function (array $a, array $b): int {
            $sourceOrder = ['magazin' => 0, 'coada' => 1];
            $sourceCmp = ($sourceOrder[$a['source'] ?? ''] ?? 9) <=> ($sourceOrder[$b['source'] ?? ''] ?? 9);
            if ($sourceCmp !== 0) {
                return $sourceCmp;
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        $total = count($items);

        return [
            'items' => array_slice($items, $offset, $limit),
            'total' => $total,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeProductListRow(array $row, string $source): array
    {
        $productState = trim((string) ($row['product_state'] ?? ''));
        $rawStatus = trim((string) ($row['status'] ?? ''));

        if ($source === 'magazin') {
            $statusLabel = $productState !== '' ? $productState : ($rawStatus === '1' || $rawStatus === '' ? 'Publicat' : $rawStatus);
        } else {
            $statusLabel = match ($rawStatus) {
                'pending' => 'De publicat',
                'imported' => 'Publicat din coada',
                'deleted' => 'Sters din coada',
                'conflict_live' => 'Conflict magazin',
                default => $rawStatus !== '' ? $rawStatus : '—',
            };
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'price' => (string) ($row['price'] ?? ''),
            'stock' => (string) ($row['stock'] ?? ''),
            'supplier' => (string) ($row['supplier'] ?? ''),
            'source' => $source,
            'status' => $statusLabel,
        ];
    }

    public function ensureImportSuppliersSynced(bool $force = false): void
    {
        if (
            !$force
            && self::$importSyncAt !== null
            && (time() - self::$importSyncAt) < self::IMPORT_SYNC_TTL_SECONDS
        ) {
            return;
        }

        $this->syncImportSuppliers($force);
        self::$importSyncAt = time();
        $this->productStatsCache = null;
    }

    public function syncImportSuppliers(bool $forceConnectionUpdate = false): void
    {
        $catalog = import_furnizori_catalog();

        foreach ($catalog as $code => $definition) {
            $payload = $this->catalogToDbPayload($code, $definition);
            $existing = $this->furnizoriModel->findByCode($code);

            if ($existing === null) {
                $payload['randomn_id'] = $this->generateUniqueRandomId();
                $this->furnizoriModel->insert($payload);
                continue;
            }

            if ($forceConnectionUpdate) {
                $randomId = (int) ($existing['randomn_id'] ?? 0);
                if ($randomId > 0) {
                    unset($payload['status']);
                    $this->furnizoriModel->updateByRandomId($randomId, $payload);
                }
            }
        }

        // tm_030: nu sterge furnizori configurati in cartela — BD este sursa de adevar.
    }

    public function seedCatalogConnections(): array
    {
        $this->syncImportSuppliers(true);

        $result = [];
        foreach (import_furnizori_catalog_codes() as $code) {
            $row = $this->furnizoriModel->findByCode($code);
            $result[] = [
                'code' => $code,
                'name' => $row['name'] ?? '',
                'connection_type' => $row['connection_type'] ?? '',
                'conn_host' => $row['conn_host'] ?? '',
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $definition @return array<string, string|int|float|null> */
    private function catalogToDbPayload(string $code, array $definition): array
    {
        $payload = [
            'name' => (string) ($definition['name'] ?? $code),
            'code' => $code,
            'status' => (string) ($definition['status'] ?? 'active'),
            'connection_type' => (string) ($definition['connection_type'] ?? 'ftp'),
            'scan_interval_minutes' => (int) ($definition['scan_interval_minutes'] ?? 360),
            'notes' => (string) ($definition['notes'] ?? ''),
        ];

        foreach ([
            'conn_host', 'conn_port', 'conn_username', 'conn_password', 'conn_remote_path',
            'conn_passive', 'conn_email', 'conn_email_inbox', 'conn_imap_host', 'conn_imap_port',
            'conn_email_password', 'api_base_url', 'api_token',
            'price_markup_type', 'price_markup_value',
        ] as $field) {
            if (array_key_exists($field, $definition) && $definition[$field] !== null && $definition[$field] !== '') {
                $payload[$field] = $definition[$field];
            }
        }

        return $payload;
    }

    private function backfillMissingCodes(): void
    {
        $pdo = Database::getDB();
        $pdo->exec(
            "UPDATE furnizori
             SET code = UPPER(TRIM(name))
             WHERE (code IS NULL OR TRIM(code) = '')
               AND TRIM(name) <> ''"
        );
    }

    /** @return array<string, array{published:int,queue:int,queue_pending:int}> */
    private function loadProductStatsForSupplierCode(string $code): array
    {
        $code = $this->normalizeSupplierCode($code);
        if ($code === '') {
            return [];
        }

        if ($this->productStatsCache !== null && isset($this->productStatsCache[$code])) {
            return [$code => $this->productStatsCache[$code]];
        }

        $pdo = Database::getDB();
        $publishedStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM produse WHERE UPPER(TRIM(pSupplier)) = :code'
        );
        $publishedStmt->execute([':code' => $code]);
        $published = (int) $publishedStmt->fetchColumn();

        $queue = 0;
        $queuePending = 0;
        $queueStmt = $pdo->prepare(
            'SELECT status, COUNT(*) AS cnt
             FROM import_produse
             WHERE UPPER(TRIM(pSupplier)) = :code
             GROUP BY status'
        );
        $queueStmt->execute([':code' => $code]);
        foreach ($queueStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $count = (int) ($row['cnt'] ?? 0);
            $queue += $count;
            if (($row['status'] ?? '') === 'pending') {
                $queuePending += $count;
            }
        }

        $stat = [
            'published' => $published,
            'queue' => $queue,
            'queue_pending' => $queuePending,
        ];

        if ($this->productStatsCache === null) {
            $this->productStatsCache = [];
        }
        $this->productStatsCache[$code] = $stat;

        return [$code => $stat];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function resolveSyncReportForCode(string $code, array $row): array
    {
        if ($this->syncReportsCache !== null && isset($this->syncReportsCache[$code])) {
            return $this->syncReportsCache[$code];
        }

        $report = (new FurnizoriDailySyncReportService())->buildReportForCode($code, $row);
        if ($this->syncReportsCache === null) {
            $this->syncReportsCache = [];
        }
        $this->syncReportsCache[$code] = $report;

        return $report;
    }

    /** @return array<string, array{published:int,queue:int,queue_pending:int}> */
    private function loadProductStatsBySupplierCode(): array
    {
        if ($this->productStatsCache !== null) {
            return $this->productStatsCache;
        }

        $pdo = Database::getDB();
        $stats = [];

        foreach ($pdo->query(
            "SELECT UPPER(TRIM(pSupplier)) AS supplier_code, COUNT(*) AS cnt
             FROM produse
             WHERE pSupplier IS NOT NULL AND TRIM(pSupplier) <> ''
             GROUP BY UPPER(TRIM(pSupplier))"
        ) as $row) {
            $code = (string) ($row['supplier_code'] ?? '');
            if ($code === '') {
                continue;
            }
            $stats[$code]['published'] = (int) ($row['cnt'] ?? 0);
        }

        foreach ($pdo->query(
            "SELECT UPPER(TRIM(pSupplier)) AS supplier_code, status, COUNT(*) AS cnt
             FROM import_produse
             WHERE pSupplier IS NOT NULL AND TRIM(pSupplier) <> ''
             GROUP BY UPPER(TRIM(pSupplier)), status"
        ) as $row) {
            $code = (string) ($row['supplier_code'] ?? '');
            if ($code === '') {
                continue;
            }
            $count = (int) ($row['cnt'] ?? 0);
            $stats[$code]['queue'] = ($stats[$code]['queue'] ?? 0) + $count;
            if (($row['status'] ?? '') === 'pending') {
                $stats[$code]['queue_pending'] = ($stats[$code]['queue_pending'] ?? 0) + $count;
            }
        }

        $this->productStatsCache = $stats;

        return $stats;
    }

    /** @param array<string, mixed> $row @param array<string, array{published?:int,queue?:int,queue_pending?:int}> $statsByCode */
    private function enrichRow(array $row, array $statsByCode): array
    {
        $row = import_furnizori_resolve_credentials($row);
        $code = $this->resolveSupplierCode($row);
        $row['supplier_code'] = $code;
        $stat = $statsByCode[$code] ?? [];

        $published = (int) ($stat['published'] ?? 0);
        $queue = (int) ($stat['queue'] ?? 0);
        $queuePending = (int) ($stat['queue_pending'] ?? 0);

        $row['products_published'] = $published;
        $row['products_queue'] = $queue;
        $row['products_queue_pending'] = $queuePending;
        $row['products_count'] = $published + $queue;

        $definitions = import_furnizori_catalog();
        $isConfigured = $code !== '' && isset($definitions[$code]);
        if ($isConfigured) {
            $def = $definitions[$code];
            foreach ([
                'connection_type',
                'conn_email_inbox',
                'conn_host',
                'conn_username',
                'conn_remote_path',
            ] as $catalogField) {
                if (
                    (!isset($row[$catalogField]) || trim((string) $row[$catalogField]) === '')
                    && isset($def[$catalogField])
                    && trim((string) $def[$catalogField]) !== ''
                ) {
                    $row[$catalogField] = $def[$catalogField];
                }
            }
            $row['import_priority'] = (int) ($def['priority'] ?? 0);
            $row['import_vat_rule'] = (string) ($def['vat_rule'] ?? 'net_plus_tva');
            $row['import_vat_label'] = import_supplier_vat_rule_label((string) ($def['vat_rule'] ?? 'net_plus_tva'));
            $row['import_price_columns'] = (string) ($def['price_columns'] ?? '');
            $row['is_import_supplier'] = true;
            $row['is_configured_supplier'] = true;
            $row['export_destinations'] = import_furnizori_destinations_for_code($code);
        } else {
            $row['import_priority'] = null;
            $row['import_vat_rule'] = null;
            $row['import_vat_label'] = null;
            $row['import_price_columns'] = null;
            $row['is_import_supplier'] = false;
            $row['is_configured_supplier'] = $code !== '';
            $row['export_destinations'] = $code !== '' ? import_furnizori_destinations_for_code($code) : [];
        }

        $row['stock_zero_label'] = match ($row['stock_zero_mode'] ?? 'full') {
            'hide' => 'Ascunde produsul',
            'out_of_stock' => 'Afiseaza epuizat',
            default => 'Afiseaza ca FULL',
        };

        $markupType = (string) ($row['price_markup_type'] ?? 'percentage');
        $storedMarkupValue = (float) ($row['price_markup_value'] ?? 0);
        $isReturn10Supplier = $code !== '' && in_array($code, import_supplier_return10_codes(), true);
        $hasFeedMarkupOverride = $isReturn10Supplier && $storedMarkupValue > 0.0001;
        if ($isReturn10Supplier) {
            $markupType = 'percentage';
            $markupValue = $hasFeedMarkupOverride ? $storedMarkupValue : 0.0;
        } elseif ($storedMarkupValue <= 0.0001 && $code !== '') {
            $markupValue = $storedMarkupValue;
            $canonicalDefaults = import_supplier_feed_markup_defaults();
            if (isset($canonicalDefaults[$code])) {
                $markupValue = (float) $canonicalDefaults[$code];
            }
        } else {
            $markupValue = $storedMarkupValue;
        }
        $row['price_markup_type'] = $markupType;
        $row['price_markup_value'] = $markupValue;
        $row['price_markup_label'] = $markupType === 'fixed'
            ? number_format($markupValue, 2, '.', '') . ' lei'
            : number_format($markupValue, 2, '.', '') . '%';
        $row['feed_markup_locked'] = $isReturn10Supplier;
        $row['feed_markup_override'] = $hasFeedMarkupOverride;
        $row['feed_markup_editable'] = !$isReturn10Supplier || $hasFeedMarkupOverride;
        $row['feed_markup_lock_reason'] = $isReturn10Supplier
            ? 'Return 10% lunar la target — pretul din CSV este pretul de achizitie real; adaos compensator pe feed = 0% (implicit). Bifați override pentru adaos personalizat.'
            : '';
        if ($isReturn10Supplier) {
            $row['compensator_profile_links'] = $this->compensatorProfileLinks();
        }

        $syncReport = $this->resolveSyncReportForCode($code, $row);

        $folder = (new SupplierFeedFolderService())->ensureFolder(
            $code,
            (int) ($row['randomn_id'] ?? 0)
        );
        $row['feed_folder_path'] = $folder['path'];
        $row['feed_folder_relative'] = $folder['relative'];
        $row['feed_folder_exists'] = $folder['exists'];
        $row['feed_folder_slug'] = $folder['slug'];

        $merged = array_merge($row, $syncReport);
        $fileCount = (int) ($merged['sync_files_count'] ?? 0);
        $merged['files_ready'] = $fileCount > 0;
        $merged['files_ready_label'] = $merged['files_ready'] ? 'DA' : 'NU';

        return SupplierScanScheduleService::normalizeSupplierRow($merged);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function sanitizeClientSecrets(array $row): array
    {
        $resolved = import_furnizori_resolve_credentials($row);
        $row['has_conn_password'] = trim((string) ($resolved['conn_password'] ?? '')) !== '';
        $row['has_api_token'] = trim((string) ($resolved['api_token'] ?? '')) !== '';
        $row['api_login_hint'] = '';
        $row['api_password_saved'] = false;
        $row['api_token_saved'] = false;

        $rawToken = trim((string) ($resolved['api_token'] ?? ''));
        if ($rawToken !== '') {
            $decoded = json_decode($rawToken, true);
            if (is_array($decoded)) {
                foreach (['clientCode', 'login', 'username', 'user'] as $key) {
                    if (!empty($decoded[$key])) {
                        $row['api_login_hint'] = (string) $decoded[$key];
                        break;
                    }
                }
                foreach (['clientPassword', 'wsPassword', 'password', 'pass'] as $key) {
                    if (!empty($decoded[$key])) {
                        $row['api_password_saved'] = true;
                        break;
                    }
                }
                foreach (['token', 'access_token', 'api_key', 'bearer'] as $key) {
                    if (!empty($decoded[$key])) {
                        $row['api_token_saved'] = true;
                        break;
                    }
                }
            } else {
                $row['api_token_saved'] = true;
            }
        }

        unset($row['conn_password'], $row['api_token']);

        return $row;
    }

    /** @return array<string, array<string, mixed>> */
    private function loadSyncReportsByCode(): array
    {
        if ($this->syncReportsCache !== null) {
            return $this->syncReportsCache;
        }

        $this->syncReportsCache = (new FurnizoriDailySyncReportService())->buildAllReports();

        return $this->syncReportsCache;
    }

    /** @return array<int, array{code:string,name:string,randomn_id:int,default_markup:float}> */
    private function compensatorProfileLinks(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        $defaults = import_supplier_feed_markup_defaults();

        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT randomn_id, UPPER(TRIM(code)) AS code, name, price_markup_value
                 FROM furnizori
                 WHERE UPPER(TRIM(code)) IN ('ELIT', 'AUTOPARTNER')
                 ORDER BY FIELD(UPPER(TRIM(code)), 'ELIT', 'AUTOPARTNER')"
            );
            if ($stmt !== false) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $code = import_supplier_normalize_code((string) ($row['code'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $markup = (float) ($row['price_markup_value'] ?? 0);
                    if ($markup <= 0.0001 && isset($defaults[$code])) {
                        $markup = (float) $defaults[$code];
                    }
                    $cache[] = [
                        'code' => $code,
                        'name' => (string) ($row['name'] ?? $code),
                        'randomn_id' => (int) ($row['randomn_id'] ?? 0),
                        'default_markup' => $markup,
                    ];
                }
            }
        } catch (\Throwable $e) {
            foreach (['ELIT', 'AUTOPARTNER'] as $code) {
                if (!isset($defaults[$code])) {
                    continue;
                }
                $cache[] = [
                    'code' => $code,
                    'name' => $code,
                    'randomn_id' => 0,
                    'default_markup' => (float) $defaults[$code],
                ];
            }
        }

        return $cache;
    }

    /** @param array<string, mixed> $row */
    private function resolveSupplierCode(array $row): string
    {
        $code = $this->normalizeSupplierCode((string) ($row['code'] ?? ''));
        if ($code !== '') {
            return $code;
        }

        return $this->normalizeSupplierCode((string) ($row['name'] ?? ''));
    }

    private function normalizeSupplierCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(600000, 699999);
            if (!$this->furnizoriModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }

        return random_int(600000, 699999);
    }
}
