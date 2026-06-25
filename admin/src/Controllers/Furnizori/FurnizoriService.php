<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';

use Evasystem\Core\Furnizori\FurnizoriModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;

/**
 * Logica de business pentru furnizori.
 */
final class FurnizoriService
{
    public function __construct(
        private FurnizoriModel $furnizoriModel,
        private FurnizoriStatsService $statsService,
    ) {
    }

    /** @param array<string, string|int|float|null> $payload @return array{randomn_id:int} */
    public function createFurnizor(array $payload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $payload['randomn_id'] = $randomId;

        if (!$this->furnizoriModel->insert($payload)) {
            throw new PersistenceException('Furnizorul nu a putut fi salvat.');
        }

        $this->ensureSupplierFeedFolder(
            (string) ($payload['code'] ?? ''),
            $randomId
        );

        if (function_exists('import_supplier_feed_markup_reset_cache')) {
            import_supplier_feed_markup_reset_cache();
        }
        if (function_exists('import_furnizori_catalog_reset_cache')) {
            import_furnizori_catalog_reset_cache();
        }

        return ['randomn_id' => $randomId];
    }

    /** @param array<string, string|int|float|null> $payload @return array{randomn_id:int,products_repriced?:int} */
    public function updateFurnizor(int $randomId, array $payload): array
    {
        $existing = $this->furnizoriModel->findByRandomId($randomId);
        if ($existing === null) {
            throw new NotFoundException('Furnizorul cerut nu exista.');
        }

        $oldMarkup = (float) ($existing['price_markup_value'] ?? 0);
        $supplierCode = import_supplier_normalize_code((string) ($existing['code'] ?? ''));
        if (
            $supplierCode !== ''
            && $oldMarkup <= 0.0001
            && !in_array($supplierCode, import_supplier_return10_codes(), true)
        ) {
            $defaults = import_supplier_feed_markup_defaults();
            if (isset($defaults[$supplierCode])) {
                $oldMarkup = (float) $defaults[$supplierCode];
            }
        }

        if (!$this->furnizoriModel->updateByRandomId($randomId, $payload)) {
            throw new PersistenceException('Furnizorul nu a putut fi actualizat.');
        }

        $mergedCode = trim((string) ($payload['code'] ?? $existing['code'] ?? ''));
        $this->ensureSupplierFeedFolder($mergedCode, $randomId);

        if (function_exists('import_supplier_feed_markup_reset_cache')) {
            import_supplier_feed_markup_reset_cache();
        }

        $stockZeroLib = dirname(__DIR__) . '/Produse/import_supplier_stock_zero_lib.php';
        if (is_file($stockZeroLib)) {
            require_once $stockZeroLib;
            if (function_exists('import_supplier_scan_rules_reset_cache')) {
                import_supplier_scan_rules_reset_cache();
            }
        }
        if (function_exists('import_furnizori_catalog_reset_cache')) {
            import_furnizori_catalog_reset_cache();
        }

        $result = ['randomn_id' => $randomId];
        if (
            array_key_exists('price_markup_value', $payload)
            && $supplierCode !== ''
            && function_exists('import_supplier_reprice_products_after_markup_change')
        ) {
            $newMarkup = (float) ($payload['price_markup_value'] ?? $oldMarkup);
            if (abs($oldMarkup - $newMarkup) >= 0.0001) {
                $reprice = import_supplier_reprice_products_after_markup_change($supplierCode, $oldMarkup, $newMarkup);
                $result['products_repriced'] = (int) ($reprice['updated'] ?? 0);
            }
        }

        return $result;
    }

    public function changeFurnizorStatus(int $randomId, string $status): void
    {
        $this->ensureFurnizorExists($randomId);

        if (!$this->furnizoriModel->updateByRandomId($randomId, ['status' => $status])) {
            throw new PersistenceException('Statusul furnizorului nu a putut fi actualizat.');
        }

        $importLib = dirname(__DIR__) . '/Produse/import_supplier_lib.php';
        if (is_file($importLib)) {
            require_once $importLib;
            import_furnizori_reset_blocked_cache();
        }
        if (function_exists('import_furnizori_catalog_reset_cache')) {
            import_furnizori_catalog_reset_cache();
        }
    }

    public function deleteFurnizor(int $randomId): void
    {
        $this->ensureFurnizorExists($randomId);

        if (!$this->furnizoriModel->deleteByRandomId($randomId)) {
            throw new PersistenceException('Furnizorul nu a putut fi sters.');
        }

        if (function_exists('import_furnizori_catalog_reset_cache')) {
            import_furnizori_catalog_reset_cache();
        }
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function browseConnection(int $randomId, array $rawInput): array
    {
        $furnizor = $this->requireFurnizorRow($randomId);
        $furnizor = $this->applyConnectionOverrides($furnizor, $rawInput);

        $connectionType = strtolower(trim((string) ($rawInput['connection_type'] ?? '')));
        if ($connectionType !== '') {
            $furnizor['connection_type'] = $connectionType;
        }

        $path = trim((string) ($rawInput['path'] ?? ''));
        $browseOptions = [];
        if (array_key_exists('include_remote', $rawInput)) {
            $browseOptions['include_remote'] = filter_var($rawInput['include_remote'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('auto_mirror', $rawInput)) {
            $browseOptions['auto_mirror'] = filter_var($rawInput['auto_mirror'], FILTER_VALIDATE_BOOLEAN);
        }

        return (new FurnizoriConnectionBrowser())->browse($furnizor, $path, $browseOptions);
    }

    /** @return array{copied:array<int,string>,skipped:array<int,string>,folder:string,path:string} */
    public function mirrorFeedFilesFromImport(int $randomId): array
    {
        $furnizor = $this->requireFurnizorRow($randomId);
        $code = trim((string) ($furnizor['code'] ?? ''));

        return (new SupplierFeedFolderService())->mirrorImportFilesForSupplier(
            $code,
            (int) ($furnizor['randomn_id'] ?? $randomId)
        );
    }

    /** @param array<string, mixed> $options @return array<string, string|null> */
    public function testConnection(int $randomId, array $options = []): array
    {
        $furnizor = $this->applyConnectionOverrides(
            $this->requireFurnizorRow($randomId),
            $options
        );

        if (($furnizor['status'] ?? '') === 'blocked') {
            $message = 'Furnizorul este blocat.';
            $this->saveTestResult($randomId, 'failed', $message);

            return ['last_test_status' => 'failed', 'last_test_message' => $message];
        }

        $testTarget = $this->resolveTestTarget($furnizor, $options);
        if ($testTarget === 'ftp') {
            $this->maybePersistConnectionFields($randomId, $options);
            $message = $this->testFtp($furnizor);
        } else {
            $message = $this->testApi($furnizor);
        }

        $status = str_starts_with($message, 'OK') ? 'success' : 'failed';
        $this->saveTestResult($randomId, $status, $message);

        return ['last_test_status' => $status, 'last_test_message' => $message];
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    public function syncNow(int $randomId, array $options = []): array
    {
        $furnizor = $this->applyConnectionOverrides(
            $this->requireFurnizorRow($randomId),
            $options
        );

        if (($furnizor['status'] ?? '') === 'blocked') {
            throw new PersistenceException('Furnizorul este blocat.');
        }

        $this->maybePersistConnectionFields($randomId, $options);

        $result = (new FurnizoriRemoteSyncService())->syncFromFtp($furnizor);
        if (empty($result['success'])) {
            throw new PersistenceException((string) ($result['message'] ?? 'Sincronizarea a esuat.'));
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>>|array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function listFurnizori(array $params = []): array
    {
        if ($params === [] || (!isset($params['page']) && !isset($params['per_page']) && !isset($params['q']) && !isset($params['status']))) {
            return $this->statsService->listWithStats();
        }

        return $this->statsService->listWithStatsPaginated($params);
    }

    /** @return array<string, mixed> */
    public function findFurnizor(int $randomId): array
    {
        $furnizor = $this->statsService->findWithStats($randomId);
        if ($furnizor === []) {
            throw new NotFoundException('Furnizorul cerut nu exista.');
        }

        return $furnizor;
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function listFurnizorProducts(int $randomId, int $limit = 50, int $offset = 0, string $scope = 'imported'): array
    {
        $furnizor = $this->furnizoriModel->findByRandomId($randomId);
        if ($furnizor === null) {
            throw new NotFoundException('Furnizorul cerut nu exista.');
        }

        $code = trim((string) ($furnizor['code'] ?? ''));
        if ($code === '') {
            $code = trim((string) ($furnizor['name'] ?? ''));
        }

        return $this->statsService->listProductsForSupplier($code, $limit, $offset, $scope);
    }

    /** @param array<string, mixed> $furnizor */
    private function testFtp(array $furnizor): string
    {
        if (!FtpConnectionClient::isAvailable()) {
            return 'Extensia curl sau ftp nu este disponibila pe server.';
        }

        $host = trim((string) ($furnizor['conn_host'] ?? ''));
        if ($host === '') {
            return 'Host FTP neconfigurat.';
        }

        $client = (new FtpConnectionClient())->configure($furnizor);
        $remotePath = trim((string) ($furnizor['conn_remote_path'] ?? ''));
        $result = $remotePath !== '' ? $client->testRemotePath($remotePath) : $client->ping();

        return !empty($result['ok'])
            ? (string) ($result['message'] ?? 'OK')
            : (string) ($result['message'] ?? 'Test FTP esuat.');
    }

    /** @param array<string, mixed> $options */
    private function resolveTestTarget(array $furnizor, array $options): string
    {
        $explicit = strtolower(trim((string) ($options['test_target'] ?? $options['connection_target'] ?? '')));
        if (in_array($explicit, ['api', 'ftp'], true)) {
            return $explicit;
        }

        return (string) ($furnizor['connection_type'] ?? 'api') === 'api' ? 'api' : 'ftp';
    }

    /** @param array<string, mixed> $options */
    private function maybePersistConnectionFields(int $randomId, array $options): void
    {
        $payload = [];
        foreach (['conn_host', 'conn_port', 'conn_username', 'conn_remote_path'] as $field) {
            if (array_key_exists($field, $options) && trim((string) $options[$field]) !== '') {
                $payload[$field] = trim((string) $options[$field]);
            }
        }

        if (!empty($options['conn_password'])) {
            $payload['conn_password'] = (string) $options['conn_password'];
        }

        if ($payload !== []) {
            $this->furnizoriModel->updateByRandomId($randomId, $payload);
        }
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function applyConnectionOverrides(array $furnizor, array $options): array
    {
        foreach (['conn_host', 'conn_port', 'conn_username', 'conn_remote_path', 'api_base_url', 'connection_type'] as $field) {
            if (array_key_exists($field, $options) && trim((string) $options[$field]) !== '') {
                $furnizor[$field] = trim((string) $options[$field]);
            }
        }

        if (!empty($options['conn_password'])) {
            $furnizor['conn_password'] = (string) $options['conn_password'];
        }

        return import_furnizori_resolve_credentials($furnizor);
    }

    /** @return array<string, mixed> */
    private function requireFurnizorRow(int $randomId): array
    {
        $furnizor = $this->furnizoriModel->findByRandomId($randomId);
        if ($furnizor === null) {
            throw new NotFoundException('Furnizorul cerut nu exista.');
        }

        return $furnizor;
    }

    /** @param array<string, mixed> $furnizor */
    private function testApi(array $furnizor): string
    {
        if ($this->isAutoPartnerSupplier($furnizor)) {
            $result = (new AutoPartnerApiClient())->configure($furnizor)->testConnection();

            return $result['message'];
        }

        $url = trim((string) ($furnizor['api_base_url'] ?? ''));
        if ($url === '') {
            return empty($furnizor['api_token']) ? 'Tokenul API lipseste.' : 'OK';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'URL-ul API nu este valid.';
        }

        if (empty($furnizor['api_token'])) {
            return 'Tokenul API lipseste.';
        }

        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 6]]);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            return 'Test HTTP esuat sau blocat de server.';
        }

        return 'OK';
    }

    /** @param array<string, mixed> $furnizor */
    private function isAutoPartnerSupplier(array $furnizor): bool
    {
        $code = strtoupper(trim((string) ($furnizor['code'] ?? '')));
        if ($code === 'AUTOPARTNER') {
            return true;
        }

        $url = strtolower(trim((string) ($furnizor['api_base_url'] ?? '')));

        return str_contains($url, 'autopartner');
    }

    private function saveTestResult(int $randomId, string $status, string $message): void
    {
        $this->furnizoriModel->updateByRandomId($randomId, [
            'last_test_status' => $status,
            'last_test_message' => $message,
            'last_test_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ensureFurnizorExists(int $randomId): void
    {
        if (!$this->furnizoriModel->existsByRandomId($randomId)) {
            throw new NotFoundException('Furnizorul cerut nu exista.');
        }
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(600000, 699999);
            if (!$this->furnizoriModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }

        throw new PersistenceException('Nu am reusit sa generez un randomn_id unic pentru furnizor.');
    }

    private function ensureSupplierFeedFolder(string $code, int $randomnId): void
    {
        (new SupplierFeedFolderService())->ensureFolder($code, $randomnId);
    }
}
