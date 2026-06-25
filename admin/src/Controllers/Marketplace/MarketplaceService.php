<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Marketplace;

use Evasystem\Core\Marketplace\MarketplaceModel;
use Evasystem\Core\Produse\ProduseModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;
use Evasystem\Exceptions\ValidationException;
use Evasystem\Services\Marketplace\BaseLinkerImportLimits;
use Evasystem\Services\Marketplace\BaseLinkerProductMapper;

/**
 * Logica de business pentru conexiunile marketplace.
 */
final class MarketplaceService
{
    public function __construct(
        private readonly MarketplaceModel $marketplaceModel
    ) {
    }

    /** @param array<string, string|int|null> $marketplacePayload @return array{randomn_id:int} */
    public function createMarketplace(array $marketplacePayload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $marketplacePayload['randomn_id'] = $randomId;

        if (!$this->marketplaceModel->insert($marketplacePayload)) {
            throw new PersistenceException('Marketplace-ul nu a putut fi salvat.');
        }

        return ['randomn_id' => $randomId];
    }

    /** @param array<string, string|int|null> $marketplacePayload @return array{randomn_id:int} */
    public function updateMarketplace(int $randomId, array $marketplacePayload): array
    {
        $this->ensureMarketplaceExists($randomId);

        if (!$this->marketplaceModel->updateByRandomId($randomId, $marketplacePayload)) {
            throw new PersistenceException('Marketplace-ul nu a putut fi actualizat.');
        }

        return ['randomn_id' => $randomId];
    }

    public function changeMarketplaceStatus(int $randomId, string $tokenStatus): void
    {
        $this->ensureMarketplaceExists($randomId);

        if (!$this->marketplaceModel->updateByRandomId($randomId, ['token_status' => $tokenStatus])) {
            throw new PersistenceException('Statusul conexiunii nu a putut fi actualizat.');
        }
    }

    public function deleteMarketplace(int $randomId): void
    {
        $this->ensureMarketplaceExists($randomId);

        if (!$this->marketplaceModel->deleteByRandomId($randomId)) {
            throw new PersistenceException('Marketplace-ul nu a putut fi sters.');
        }
    }

    /** @return array<string, string|null> */
    public function testMarketplace(int $randomId): array
    {
        $marketplace = $this->marketplaceModel->findByRandomId($randomId);
        if ($marketplace === null) {
            throw new NotFoundException('Marketplace-ul cerut nu exista.');
        }

        $status = 'success';
        $message = $this->validateMarketplaceLocally($marketplace);

        if ($message === 'OK') {
            $platform = strtolower(trim((string) ($marketplace['platform'] ?? '')));
            if ($platform === 'baselinker') {
                $message = $this->testBaseLinkerApiMessage($marketplace);
            } elseif (!empty($marketplace['api_base_url'])) {
                $message = $this->testUrl((string) $marketplace['api_base_url']);
            }
        }

        if ($message !== 'OK') {
            $status = 'failed';
        }

        $this->marketplaceModel->updateByRandomId($randomId, [
            'last_test_status' => $status,
            'last_test_message' => $message,
            'last_test_at' => date('Y-m-d H:i:s'),
        ]);

        return ['last_test_status' => $status, 'last_test_message' => $message];
    }

    /** @return array<int, array<string, mixed>> */
    public function listMarketplaces(): array
    {
        return $this->marketplaceModel->findAll();
    }

    /** @return array<string, mixed> */
    public function findMarketplace(int $randomId): array
    {
        $marketplace = $this->marketplaceModel->findByRandomId($randomId);
        if ($marketplace === null) {
            throw new NotFoundException('Marketplace-ul cerut nu exista.');
        }

        return $marketplace;
    }

    /** @return array<string, mixed>|null */
    public function findBaseLinkerConnection(): ?array
    {
        return $this->findActiveBaseLinkerConnection();
    }

    /** @return array{last_test_status:string,last_test_message:string,inventories?:array<int,array<string,mixed>>} */
    public function testBaseLinkerConnection(int $randomId): array
    {
        $marketplace = $this->requireBaseLinkerMarketplace($randomId);
        $message = $this->testBaseLinkerApiMessage($marketplace);
        $status = $message === 'OK' ? 'success' : 'failed';

        $this->marketplaceModel->updateByRandomId($randomId, [
            'last_test_status' => $status,
            'last_test_message' => $message,
            'last_test_at' => date('Y-m-d H:i:s'),
        ]);

        $result = [
            'last_test_status' => $status,
            'last_test_message' => $message,
        ];

        if ($status === 'success') {
            $inventories = $this->fetchBaseLinkerInventories($marketplace);
            if ($inventories !== []) {
                $result['inventories'] = $inventories;
            }
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function getBaseLinkerInventories(int $randomId): array
    {
        $marketplace = $this->requireBaseLinkerMarketplace($randomId);

        return $this->fetchBaseLinkerInventories($marketplace);
    }

    /** @return array<string, mixed> */
    public function getBaseLinkerConfig(int $randomId): array
    {
        $marketplace = $this->requireBaseLinkerMarketplace($randomId);
        $mapping = $this->decodeFieldMapping($marketplace);

        return [
            'randomn_id' => (int) ($marketplace['randomn_id'] ?? 0),
            'name' => (string) ($marketplace['name'] ?? 'BaseLinker'),
            'api_token_masked' => $this->maskToken((string) ($marketplace['api_token'] ?? '')),
            'token_status' => (string) ($marketplace['token_status'] ?? ''),
            'bl_inventory_id' => (int) ($marketplace['bl_inventory_id'] ?? 0),
            'field_mapping' => $mapping,
            'allowed_source_fields' => BaseLinkerProductMapper::allowedSourceFields(),
            'default_mapping' => BaseLinkerProductMapper::defaultMapping(),
            'last_test_status' => (string) ($marketplace['last_test_status'] ?? ''),
            'last_test_message' => (string) ($marketplace['last_test_message'] ?? ''),
            'products_synced' => (int) ($marketplace['products_synced'] ?? 0),
            'last_sync_at' => (string) ($marketplace['last_sync_at'] ?? ''),
            'import_limits' => BaseLinkerImportLimits::catalog(),
            'catalog_strategy' => BaseLinkerImportLimits::recommendStrategy($this->countActiveProductsForBaseLinker()),
        ];
    }

    /** @param array<string, string> $mapping @return array{randomn_id:int,field_mapping:array<string,string>} */
    public function saveBaseLinkerFieldMapping(int $randomId, array $mapping): array
    {
        $this->requireBaseLinkerMarketplace($randomId);
        $resolved = BaseLinkerProductMapper::resolveMapping($mapping);
        $encoded = json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (!$this->marketplaceModel->updateByRandomId($randomId, ['field_mapping' => $encoded])) {
            throw new PersistenceException('Maparea câmpurilor nu a putut fi salvată.');
        }

        return ['randomn_id' => $randomId, 'field_mapping' => $resolved];
    }

    /** @return array{randomn_id:int,bl_inventory_id:int} */
    public function saveBaseLinkerInventory(int $randomId, int $inventoryId): array
    {
        $this->requireBaseLinkerMarketplace($randomId);
        if ($inventoryId <= 0) {
            throw new ValidationException('ID inventar BaseLinker invalid.');
        }

        if (!$this->marketplaceModel->updateByRandomId($randomId, ['bl_inventory_id' => $inventoryId])) {
            throw new PersistenceException('Inventarul BaseLinker nu a putut fi salvat.');
        }

        return ['randomn_id' => $randomId, 'bl_inventory_id' => $inventoryId];
    }

    /** @return array<string, mixed> */
    public function getBaseLinkerCatalogStats(int $randomId): array
    {
        $this->requireBaseLinkerMarketplace($randomId);
        $total = $this->countActiveProductsForBaseLinker();
        $batchSize = BaseLinkerImportLimits::DEFAULT_API_BATCH_SIZE;

        return array_merge(
            BaseLinkerImportLimits::catalog(),
            BaseLinkerImportLimits::recommendStrategy($total, $batchSize),
            [
                'active_products' => $total,
                'strategy' => 'api_direct',
            ]
        );
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    public function enqueueBaseLinkerCatalogSync(int $randomId, array $options = []): array
    {
        $marketplace = $this->requireBaseLinkerMarketplace($randomId);
        if ($this->validateMarketplaceLocally($marketplace) !== 'OK') {
            return [
                'status' => 'failed',
                'message' => 'Conexiunea BaseLinker nu este activă.',
                'queued_jobs' => 0,
            ];
        }

        $inventoryId = (int) ($marketplace['bl_inventory_id'] ?? 0);
        if ($inventoryId <= 0) {
            return [
                'status' => 'failed',
                'message' => 'Selectează inventarul BaseLinker înainte de sincronizare.',
                'queued_jobs' => 0,
            ];
        }

        $batchSize = max(1, min(BaseLinkerImportLimits::MAX_API_BATCH_SIZE, (int) ($options['limit'] ?? BaseLinkerImportLimits::DEFAULT_API_BATCH_SIZE)));
        $total = $this->countActiveProductsForBaseLinker();
        if ($total <= 0) {
            return [
                'status' => 'skipped',
                'message' => 'Nu există produse active de sincronizat.',
                'queued_jobs' => 0,
                'active_products' => 0,
            ];
        }

        $estimatedBatches = (int) ceil($total / $batchSize);
        $jobId = $this->pushBaseLinkerCatalogJob($randomId, 0, $batchSize, true);

        return [
            'status' => 'queued',
            'message' => "Catalog pus în coadă: {$total} produse, ~{$estimatedBatches} batch-uri API (fără limită 30MB fișier).",
            'queued_jobs' => 1,
            'job_id' => $jobId,
            'active_products' => $total,
            'batch_size' => $batchSize,
            'estimated_batches' => $estimatedBatches,
            'strategy' => 'api_direct',
            'limits' => BaseLinkerImportLimits::catalog(),
        ];
    }

    /** @param array<string, mixed> $options @return array{status:string,message:string,synced:int,failed:int,errors:array<int,string>,has_more:bool,offset:int,total_products:int} */
    public function syncProductsToBaseLinker(int $randomId, array $options = []): array
    {
        $marketplace = $this->requireBaseLinkerMarketplace($randomId);
        if ($this->validateMarketplaceLocally($marketplace) !== 'OK') {
            return [
                'status' => 'failed',
                'message' => 'Conexiunea BaseLinker nu este activă.',
                'synced' => 0,
                'failed' => 0,
                'errors' => [],
                'has_more' => false,
                'offset' => 0,
                'total_products' => 0,
            ];
        }

        $inventoryId = (int) ($marketplace['bl_inventory_id'] ?? 0);
        if ($inventoryId <= 0) {
            return [
                'status' => 'failed',
                'message' => 'Selectează inventarul BaseLinker înainte de sincronizare.',
                'synced' => 0,
                'failed' => 0,
                'errors' => [],
                'has_more' => false,
                'offset' => 0,
                'total_products' => 0,
            ];
        }

        $limit = max(1, min(BaseLinkerImportLimits::MAX_API_BATCH_SIZE, (int) ($options['limit'] ?? BaseLinkerImportLimits::DEFAULT_API_BATCH_SIZE)));
        $offset = max(0, (int) ($options['offset'] ?? 0));
        $totalProducts = $this->countActiveProductsForBaseLinker();
        $productIds = [];
        if (!empty($options['product_randomn_ids']) && is_array($options['product_randomn_ids'])) {
            foreach ($options['product_randomn_ids'] as $id) {
                if (is_numeric($id) || is_string($id)) {
                    $productIds[] = (string) $id;
                }
            }
        }

        $products = $this->loadProductsForBaseLinkerSync($productIds, $limit, $offset);
        if ($products === []) {
            return [
                'status' => 'skipped',
                'message' => 'Nu există produse de sincronizat.',
                'synced' => 0,
                'failed' => 0,
                'errors' => [],
                'has_more' => false,
                'offset' => $offset,
                'total_products' => $totalProducts,
            ];
        }

        $mapping = $this->decodeFieldMapping($marketplace);
        $token = (string) ($marketplace['api_token'] ?? '');
        $siteBaseUrl = $this->resolveSiteBaseUrl();
        $synced = 0;
        $failed = 0;
        /** @var array<int, string> $errors */
        $errors = [];

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $payload = BaseLinkerProductMapper::toBaseLinkerPayload($product, $mapping, $siteBaseUrl);
            $payload['inventory_id'] = $inventoryId;

            $response = $this->callBaseLinkerApi($token, 'addInventoryProduct', $payload);
            if (($response['status'] ?? '') === 'SUCCESS') {
                ++$synced;
                continue;
            }

            ++$failed;
            $sku = (string) ($payload['sku'] ?? $product['randomn_id'] ?? '?');
            $errors[] = $sku . ': ' . (string) ($response['error_message'] ?? 'Eroare necunoscută');
        }

        $this->marketplaceModel->updateByRandomId($randomId, [
            'products_synced' => (int) ($marketplace['products_synced'] ?? 0) + $synced,
            'errors_count' => (int) ($marketplace['errors_count'] ?? 0) + $failed,
            'last_sync_at' => date('Y-m-d H:i:s'),
        ]);

        $status = $failed === 0 ? 'success' : ($synced > 0 ? 'partial' : 'failed');
        $message = "Sincronizate {$synced} produse" . ($failed > 0 ? ", eșuate {$failed}" : '') . '.';
        $processedOffset = $productIds !== [] ? $offset : $offset + count($products);
        $hasMore = $productIds === [] && $processedOffset < $totalProducts;

        return [
            'status' => $status,
            'message' => $message,
            'synced' => $synced,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 20),
            'has_more' => $hasMore,
            'offset' => $offset,
            'next_offset' => $hasMore ? $processedOffset : $offset,
            'total_products' => $totalProducts,
        ];
    }

    /**
     * tm_107 — Trimite produse validate din coada import către BaseLinker (addInventoryProduct).
     *
     * @param array<int, array<string, mixed>> $importRows
     * @return array{status:string,message:string,sent:int,errors:int,error_details:array<int,string>}
     */
    public function syncImportQueueRowsToBaseLinker(array $importRows): array
    {
        if ($importRows === []) {
            return [
                'status' => 'skipped',
                'message' => 'Nu există produse de trimis.',
                'sent' => 0,
                'errors' => 0,
                'error_details' => [],
            ];
        }

        $marketplace = $this->findActiveBaseLinkerConnection();
        if ($marketplace === null) {
            return [
                'status' => 'failed',
                'message' => 'Conexiune BaseLinker activă lipsă. Configurează token-ul în Marketplace → BaseLinker.',
                'sent' => 0,
                'errors' => 0,
                'error_details' => [],
            ];
        }

        $randomId = (int) ($marketplace['randomn_id'] ?? 0);
        $inventoryId = (int) ($marketplace['bl_inventory_id'] ?? 0);
        if ($inventoryId <= 0) {
            return [
                'status' => 'failed',
                'message' => 'Selectează inventarul BaseLinker înainte de export.',
                'sent' => 0,
                'errors' => 0,
                'error_details' => [],
            ];
        }

        $mapping = $this->decodeFieldMapping($marketplace);
        $token = (string) ($marketplace['api_token'] ?? '');
        $siteBaseUrl = $this->resolveSiteBaseUrl();
        $sent = 0;
        $errors = 0;
        /** @var array<int, string> $errorDetails */
        $errorDetails = [];

        foreach ($importRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $payload = BaseLinkerProductMapper::toBaseLinkerPayload($row, $mapping, $siteBaseUrl);
            $payload['inventory_id'] = $inventoryId;

            $response = $this->callBaseLinkerApi($token, 'addInventoryProduct', $payload);
            if (($response['status'] ?? '') === 'SUCCESS') {
                ++$sent;
                continue;
            }

            ++$errors;
            $sku = (string) ($payload['sku'] ?? $row['pCode'] ?? $row['id'] ?? '?');
            $errorDetails[] = $sku . ': ' . (string) ($response['error_message'] ?? 'Eroare necunoscută');
        }

        if ($randomId > 0 && ($sent > 0 || $errors > 0)) {
            $this->marketplaceModel->updateByRandomId($randomId, [
                'products_synced' => (int) ($marketplace['products_synced'] ?? 0) + $sent,
                'errors_count' => (int) ($marketplace['errors_count'] ?? 0) + $errors,
                'last_sync_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $status = $errors === 0 ? 'success' : ($sent > 0 ? 'partial' : 'failed');
        $message = "{$sent} produse trimise, {$errors} erori.";

        return [
            'status' => $status,
            'message' => $message,
            'sent' => $sent,
            'errors' => $errors,
            'error_details' => array_slice($errorDetails, 0, 20),
        ];
    }

    public function continueBaseLinkerCatalogSync(int $randomId, int $nextOffset, int $batchSize): int
    {
        return $this->pushBaseLinkerCatalogJob($randomId, $nextOffset, $batchSize, true, 2);
    }

    /** @return array<string, mixed> */
    public function getBaseLinkerFeedInfo(): array
    {
        $lib = dirname(__DIR__, 4) . '/system/baselinker-feed.php';
        if (!is_file($lib)) {
            throw new PersistenceException('Modul feed BaseLinker indisponibil.');
        }

        require_once $lib;

        return baselinker_feed_info(\Config\Database::getDB());
    }

    /** @return array<string, mixed> */
    public function getBaseLinkerStoreImportInfo(): array
    {
        $lib = dirname(__DIR__, 4) . '/system/baselinker-shop-integration.php';
        if (!is_file($lib)) {
            throw new PersistenceException('Modul integrare magazin BaseLinker indisponibil.');
        }

        require_once $lib;

        $pdo = \Config\Database::getDB();
        $activeProducts = $this->countActiveProductsForBaseLinker();
        $shopInfo = baselinker_shop_info($pdo);
        $report = \Evasystem\Services\Marketplace\BaseLinkerStoreImportInvestigation::report($activeProducts);

        return [
            'shop' => $shopInfo,
            'investigation' => $report,
            'support_ticket' => \Evasystem\Services\Marketplace\BaseLinkerStoreImportInvestigation::buildSupportTicket(
                $shopInfo,
                $activeProducts
            ),
            'active_products' => $activeProducts,
        ];
    }

    /** @return array<string, mixed> */
    public function regenerateBaseLinkerFeed(): array
    {
        $lib = dirname(__DIR__, 4) . '/system/baselinker-feed.php';
        if (!is_file($lib)) {
            throw new PersistenceException('Modul feed BaseLinker indisponibil.');
        }

        require_once $lib;

        $pdo = \Config\Database::getDB();
        $queued = baselinker_feed_queue_regenerate($pdo, 0);
        if (($queued['queued'] ?? false) === true) {
            return [
                'status' => 'queued',
                'message' => 'Regenerare feed pusă în coadă.',
                'job_id' => (int) ($queued['job_id'] ?? 0),
            ] + baselinker_feed_info($pdo);
        }

        $result = baselinker_feed_regenerate($pdo);

        return [
            'status' => 'success',
            'message' => (string) ($result['message'] ?? 'Feed regenerat.'),
            'product_count' => (int) ($result['product_count'] ?? 0),
            'parts' => (int) ($result['parts'] ?? 0),
        ] + baselinker_feed_info($pdo);
    }

    /** @return array{status:string,message:string} */
    public function syncOrderToBaseLinker(int $orderRandomId): array
    {
        $connection = $this->findActiveBaseLinkerConnection();
        if ($connection === null) {
            return ['status' => 'skipped', 'message' => 'Conexiune BaseLinker activa lipsa.'];
        }

        $order = $this->loadOrderForSync($orderRandomId);
        if ($order === null) {
            return ['status' => 'failed', 'message' => 'Comanda nu exista.'];
        }

        $payload = $this->buildBaseLinkerOrderPayload($order);
        $response = $this->callBaseLinkerApi(
            (string) ($connection['api_token'] ?? ''),
            'addOrder',
            $payload
        );

        if (($response['status'] ?? '') === 'ERROR') {
            return [
                'status' => 'failed',
                'message' => (string) ($response['error_message'] ?? 'BaseLinker a respins comanda.'),
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Comanda sincronizata in BaseLinker.',
        ];
    }

    /** @return array<string, mixed>|null */
    private function findActiveBaseLinkerConnection(): ?array
    {
        foreach ($this->marketplaceModel->findAll() as $marketplace) {
            if (!is_array($marketplace)) {
                continue;
            }

            $platform = strtolower(trim((string) ($marketplace['platform'] ?? '')));
            if ($platform !== 'baselinker') {
                continue;
            }

            if ($this->validateMarketplaceLocally($marketplace) === 'OK') {
                return $marketplace;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function loadOrderForSync(int $orderRandomId): ?array
    {
        require_once dirname(__DIR__, 2) . '/Core/Comenzi/ComenziModel.php';
        require_once dirname(__DIR__, 2) . '/Core/Comenzi/OrderItemsModel.php';

        $comenziModel = new \Evasystem\Core\Comenzi\ComenziModel();
        $order = $comenziModel->findByRandomId($orderRandomId);
        if ($order === null) {
            return null;
        }

        $orderItemsModel = new \Evasystem\Core\Comenzi\OrderItemsModel();
        $dbId = (int) ($order['id'] ?? 0);
        $lines = $dbId > 0 ? $orderItemsModel->findGroupedByOrderIds([$dbId])[$dbId] ?? [] : [];
        $order['items'] = $lines;

        return $order;
    }

    /** @param array<string, mixed> $order @return array<string, mixed> */
    private function buildBaseLinkerOrderPayload(array $order): array
    {
        $products = [];
        foreach ($order['items'] ?? [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $products[] = [
                'name' => (string) ($line['product_name'] ?? 'Produs'),
                'sku' => (string) ($line['oem_code'] ?? $line['randomn_id'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 1),
                'price_brutto' => (float) ($line['unit_price'] ?? 0),
            ];
        }

        return [
            'order_status_id' => 0,
            'date_add' => time(),
            'user_login' => (string) ($order['client_name'] ?? $order['name'] ?? 'Client site'),
            'email' => (string) ($order['email'] ?? ''),
            'phone' => (string) ($order['phone'] ?? ''),
            'payment_method' => (string) ($order['payment_status'] ?? 'website'),
            'payment_done' => 0,
            'admin_comments' => 'Sync automat site #' . (string) ($order['order_number'] ?? $order['randomn_id'] ?? ''),
            'products' => $products,
        ];
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed> */
    private function callBaseLinkerApi(string $token, string $method, array $parameters): array
    {
        if ($token === '') {
            return ['status' => 'ERROR', 'error_message' => 'Token BaseLinker lipsa.'];
        }

        $body = http_build_query([
            'token' => $token,
            'method' => $method,
            'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 15,
            ],
        ]);

        $raw = @file_get_contents('https://api.baselinker.com/connector.php', false, $context);
        if ($raw === false) {
            return ['status' => 'ERROR', 'error_message' => 'Nu am putut contacta API BaseLinker.'];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['status' => 'ERROR', 'error_message' => 'Raspuns BaseLinker invalid.'];
    }

    /** @param array<string, mixed> $marketplace */
    private function validateMarketplaceLocally(array $marketplace): string
    {
        if (($marketplace['token_status'] ?? '') !== 'active') {
            return 'Tokenul nu este activ.';
        }

        if (empty($marketplace['api_token'])) {
            return 'Tokenul API lipseste.';
        }

        if (!empty($marketplace['starts_at']) && strtotime((string) $marketplace['starts_at']) > time()) {
            return 'Conexiunea nu este inca in perioada de start.';
        }

        if (!empty($marketplace['ends_at']) && strtotime((string) $marketplace['ends_at']) < time()) {
            return 'Tokenul este expirat.';
        }

        return 'OK';
    }

    /** @param array<string, mixed> $marketplace */
    private function testBaseLinkerApiMessage(array $marketplace): string
    {
        $local = $this->validateMarketplaceLocally($marketplace);
        if ($local !== 'OK') {
            return $local;
        }

        $response = $this->callBaseLinkerApi(
            (string) ($marketplace['api_token'] ?? ''),
            'getInventories',
            []
        );

        if (($response['status'] ?? '') !== 'SUCCESS') {
            return (string) ($response['error_message'] ?? 'Test BaseLinker eșuat.');
        }

        if (!isset($response['inventories']) || !is_array($response['inventories'])) {
            return 'Răspuns BaseLinker fără inventare.';
        }

        return 'OK — ' . count($response['inventories']) . ' inventare disponibile.';
    }

    /** @param array<string, mixed> $marketplace @return array<int, array<string, mixed>> */
    private function fetchBaseLinkerInventories(array $marketplace): array
    {
        $response = $this->callBaseLinkerApi(
            (string) ($marketplace['api_token'] ?? ''),
            'getInventories',
            []
        );

        if (($response['status'] ?? '') !== 'SUCCESS' || !is_array($response['inventories'] ?? null)) {
            return [];
        }

        $items = [];
        foreach ($response['inventories'] as $inventory) {
            if (is_array($inventory)) {
                $items[] = $inventory;
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function requireBaseLinkerMarketplace(int $randomId): array
    {
        $marketplace = $this->marketplaceModel->findByRandomId($randomId);
        if ($marketplace === null) {
            throw new NotFoundException('Conexiunea marketplace nu există.');
        }

        if (strtolower(trim((string) ($marketplace['platform'] ?? ''))) !== 'baselinker') {
            throw new ValidationException('Conexiunea selectată nu este BaseLinker.');
        }

        return $marketplace;
    }

    /** @return array<string, string> */
    private function decodeFieldMapping(array $marketplace): array
    {
        $raw = $marketplace['field_mapping'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            return BaseLinkerProductMapper::decodeStored(is_array($decoded) ? $decoded : null);
        }

        if (is_array($raw)) {
            return BaseLinkerProductMapper::decodeStored($raw);
        }

        return BaseLinkerProductMapper::defaultMapping();
    }

    public function countActiveProductsForBaseLinker(): int
    {
        $pdo = \Config\Database::getDB();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM produse WHERE status <> :inactive');
        $stmt->execute([':inactive' => '0']);

        return (int) $stmt->fetchColumn();
    }

    /** @param list<string> $productRandomIds @return array<int, array<string, mixed>> */
    private function loadProductsForBaseLinkerSync(array $productRandomIds, int $limit, int $offset = 0): array
    {
        $model = new ProduseModel();

        if ($productRandomIds !== []) {
            $items = [];
            foreach (array_slice($productRandomIds, 0, $limit) as $randomId) {
                $product = $model->find((string) $randomId);
                if ($product !== null && ($product['status'] ?? '1') !== '0') {
                    $items[] = $product;
                }
            }

            return $items;
        }

        $pdo = \Config\Database::getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM produse WHERE status <> :inactive ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':inactive', '0', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function pushBaseLinkerCatalogJob(int $randomId, int $offset, int $batchSize, bool $autoContinue, int $delaySeconds = 0): int
    {
        $jobQueuePath = dirname(__DIR__, 4) . '/system/JobQueue.php';
        if (!is_file($jobQueuePath)) {
            throw new PersistenceException('JobQueue indisponibil.');
        }

        require_once $jobQueuePath;

        $queue = new \JobQueue(\Config\Database::getDB(), 'default');

        return $queue->push('baselinker_sync_products', [
            'connection_randomn_id' => $randomId,
            'randomn_id' => $randomId,
            'limit' => $batchSize,
            'offset' => max(0, $offset),
            'auto_continue' => $autoContinue,
            'batch_size' => $batchSize,
        ], max(0, $delaySeconds));
    }

    private function resolveSiteBaseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'besoiupieseauto.ro.test';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        return $scheme . '://' . $host;
    }

    private function maskToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        return strlen($token) <= 12
            ? '************'
            : substr($token, 0, 6) . '...' . substr($token, -6);
    }

    private function testUrl(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'URL-ul API nu este valid.';
        }

        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 6],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            return 'Test HTTP esuat sau blocat de server.';
        }

        return 'OK';
    }

    private function ensureMarketplaceExists(int $randomId): void
    {
        if (!$this->marketplaceModel->existsByRandomId($randomId)) {
            throw new NotFoundException('Marketplace-ul cerut nu exista.');
        }
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(700000, 999999);
            if (!$this->marketplaceModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }

        throw new PersistenceException('Nu am reusit sa generez un randomn_id unic pentru marketplace.');
    }
}
