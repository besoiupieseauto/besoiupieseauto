<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

use Evasystem\Controllers\Furnizori\AutoPartnerApiClient;
use Evasystem\Services\SupplierSearch\Builders\AutonetSearchBuilder;
use Evasystem\Services\SupplierSearch\Builders\AutototalSearchBuilder;
use Evasystem\Services\SupplierSearch\Clients\AutototalClient;
use Evasystem\Services\SupplierSearch\Parsers\AutonetParser;
use Evasystem\Services\SupplierSearch\Parsers\AutopartnerParser;
use Evasystem\Services\SupplierSearch\Parsers\AutototalParser;
use Evasystem\Services\SupplierSearch\Parsers\ElitParser;
use Evasystem\Services\SupplierSearch\Parsers\MateromParser;

/**
 * Orchestrator căutare paralelă furnizori (M2 Phase 1 + 2).
 */
final class SupplierSearchService
{
    private const AUTONET_CHUNK_SIZE = 50;

    /** @param array<int, string> $suppliers @return array<string, mixed> */
    public function search(string $query, array $suppliers, bool $debugTimings = false): array
    {
        $timings = [];
        $started = microtime(true);

        $query = SupplierSearchConfig::normalizeQuery($query);
        if ($query === '') {
            return ['success' => false, 'message' => 'Query is required'];
        }

        $suppliers = $this->normalizeSuppliers($suppliers);
        if ($suppliers === []) {
            return ['success' => false, 'message' => 'Select at least one supplier'];
        }

        $legacyPdo = SupplierSearchConfig::legacyPdo();
        $catalogLookup = new PartsCatalogLookup($legacyPdo);

        $buildStart = microtime(true);
        $autonetItems = null;
        $autonetRowMap = [];
        $autototalRequests = null;

        if (in_array('autonet', $suppliers, true)) {
            $autonetRows = $catalogLookup->getRowsForAutonet($query, $query);
            $autonetItems = (new AutonetSearchBuilder())->buildItems($query, $query, $autonetRows, $legacyPdo);
            $autonetRowMap = (new AutonetSearchBuilder())->buildRowMap($autonetRows, $query, $legacyPdo);
        }

        if (in_array('autototal', $suppliers, true)) {
            $autototalRequests = (new AutototalSearchBuilder())->buildRequests($query, $query, $legacyPdo, 18);
            if ($autototalRequests === []) {
                $autototalRequests = [[
                    'itemkey' => $query,
                    'quantity' => 2,
                    'targets' => [[
                        'base_code' => preg_replace('/[.\s\-\/|\\\\]+/', '', $query) ?: $query,
                        'manufacturer' => null,
                        'db_name' => null,
                        'sup_brand' => null,
                    ]],
                ]];
            }
        }

        $timings['build_inputs'] = round(microtime(true) - $buildStart, 3);

        $poolStart = microtime(true);
        $responses = $this->runPool($query, $suppliers, $catalogLookup, $autonetItems, $autototalRequests);
        $timings['pool'] = round(microtime(true) - $poolStart, 3);

        $parseStart = microtime(true);
        $entries = [];

        if (in_array('materom', $suppliers, true)) {
            $materom = $responses['materom'] ?? ['success' => false, 'data' => []];
            if ($materom['success']) {
                $entries = array_merge($entries, (new MateromParser())->parse($query, $materom['data']));
            }
        }

        if (in_array('autopartner', $suppliers, true)) {
            $ap = $responses['autopartner'] ?? ['success' => false, 'data' => []];
            if ($ap['success']) {
                $parser = new AutopartnerParser($catalogLookup);
                $parser->setSeedMap($this->buildAutopartnerSeedMap($query, $catalogLookup));
                $entries = array_merge($entries, $parser->parse($query, $ap['data']));
            }
        }

        if (in_array('elit', $suppliers, true)) {
            $entries = array_merge($entries, (new ElitParser($legacyPdo))->parse($query));
        }

        if (in_array('autonet', $suppliers, true)) {
            $autonet = $responses['autonet'] ?? ['success' => false, 'data' => []];
            if ($autonet['success']) {
                $parser = new AutonetParser($legacyPdo);
                $parser->setRequestRowMap($autonetRowMap);
                $entries = array_merge($entries, $parser->parse($query, $autonet['data']));
            }
        }

        if (in_array('autototal', $suppliers, true)) {
            $autototal = $responses['autototal'] ?? ['success' => false, 'data' => []];
            if ($autototal['success']) {
                $entries = array_merge($entries, (new AutototalParser())->parse($query, $autototal['data']));
            }
        }

        $productsMap = (new ProductAggregator())->merge($entries);
        $timings['parse_merge'] = round(microtime(true) - $parseStart, 3);

        $postStart = microtime(true);
        (new PricingApplier())->applyToProducts($productsMap);
        (new DeliveryFormatter())->formatProducts($productsMap);
        (new ProductNameResolver())->resolveProducts($productsMap);
        $timings['postprocess'] = round(microtime(true) - $postStart, 3);
        $timings['total'] = round(microtime(true) - $started, 3);

        $payload = (new SearchPayloadBuilder())->build($productsMap, $debugTimings ? $timings : []);
        $payload['query'] = $query;
        $payload['suppliers_requested'] = $suppliers;
        $payload['errors'] = $this->collectErrors($responses, $legacyPdo, $suppliers);

        return $payload;
    }

    /** @param array<int, string> $suppliers @return array<int, string> */
    private function normalizeSuppliers(array $suppliers): array
    {
        $allowed = SupplierSearchConfig::supportedSuppliersFromCatalog();
        if ($allowed === []) {
            $allowed = SupplierSearchConfig::all()['supported_suppliers'] ?? [];
        }
        $normalized = [];
        foreach ($suppliers as $supplier) {
            $slug = strtolower(trim((string) $supplier));
            if ($slug !== '' && in_array($slug, $allowed, true)) {
                $normalized[] = $slug;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int, array<string, mixed>>|null $autonetItems
     * @param array<int, array<string, mixed>>|null $autototalRequests
     * @return array<string, array{success:bool,data:mixed,error:string}>
     */
    private function runPool(
        string $query,
        array $suppliers,
        PartsCatalogLookup $catalogLookup,
        ?array $autonetItems,
        ?array $autototalRequests
    ): array {
        $responses = [];
        $handles = [];
        $multi = curl_multi_init();
        $hasHandles = false;
        $config = SupplierSearchConfig::all();
        $timeout = (int) ($config['timeout'] ?? 15);
        $connectTimeout = (int) ($config['connect_timeout'] ?? 5);

        if (in_array('materom', $suppliers, true)) {
            $token = SupplierSearchConfig::materomToken();
            if ($token !== '') {
                $url = SupplierSearchConfig::materomBaseUrl() . '/v4/part_search/global?' . http_build_query(['term' => $query]);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                ]);
                curl_multi_add_handle($multi, $ch);
                $handles[(int) $ch] = ['key' => 'materom', 'handle' => $ch, 'type' => 'json'];
                $hasHandles = true;
            } else {
                $responses['materom'] = ['success' => false, 'data' => [], 'error' => 'Token Materom lipsă (MATEROM_TOKEN_TIMISOARA).'];
            }
        }

        if (in_array('autopartner', $suppliers, true)) {
            $furnizor = SupplierSearchConfig::autopartnerFurnizor();
            if ($furnizor !== null) {
                $products = $this->buildAutopartnerProducts($query, $catalogLookup);
                $client = (new AutoPartnerApiClient())->configure($furnizor);
                $stored = $client->credentialsForStorage();
                $payload = json_encode([
                    'clientCode' => $stored['api_client_code'] ?? '',
                    'wsPassword' => $stored['api_ws_password'] ?? '',
                    'clientPassword' => $stored['api_client_password'] ?? '',
                    'products' => $products,
                    'onlySite' => false,
                ], JSON_UNESCAPED_UNICODE);

                $url = rtrim((string) ($stored['api_base_url'] ?? ''), '/') . '/ProductsAvailabilityV2';
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                ]);
                curl_multi_add_handle($multi, $ch);
                $handles[(int) $ch] = ['key' => 'autopartner', 'handle' => $ch, 'type' => 'json'];
                $hasHandles = true;
            } else {
                $responses['autopartner'] = ['success' => false, 'data' => [], 'error' => 'Furnizor AUTOPARTNER neconfigurat în admin.'];
            }
        }

        if (in_array('autonet', $suppliers, true)) {
            $taxCode = SupplierSearchConfig::autonetTaxCode();
            $token = SupplierSearchConfig::autonetSecurityToken();
            if ($taxCode === '' || $token === '') {
                $responses['autonet'] = ['success' => false, 'data' => [], 'error' => 'Credențiale Autonet lipsă (AUTONET_TAX_CODE / AUTONET_SECURITY_TOKEN).'];
            } elseif ($autonetItems === null || $autonetItems === []) {
                $responses['autonet'] = ['success' => false, 'data' => [], 'error' => 'Niciun articol Autonet de interogat.'];
            } else {
                $url = SupplierSearchConfig::autonetBaseUrl() . '/GetDeliveryData';
                $headers = [
                    'TAX-CODE: ' . $taxCode,
                    'SECURITY-TOKEN: ' . $token,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ];
                $branch = SupplierSearchConfig::autonetBranch();
                if ($branch !== '') {
                    $headers[] = 'BRANCH: ' . $branch;
                }

                foreach (array_chunk($autonetItems, self::AUTONET_CHUNK_SIZE) as $chunkIndex => $chunk) {
                    $payload = json_encode($chunk, JSON_UNESCAPED_UNICODE);
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $payload,
                        CURLOPT_HTTPHEADER => $headers,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => $timeout,
                        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                    ]);
                    curl_multi_add_handle($multi, $ch);
                    $handles[(int) $ch] = ['key' => 'autonet', 'handle' => $ch, 'type' => 'autonet_chunk', 'chunk' => $chunkIndex];
                    $hasHandles = true;
                }
            }
        }

        if (in_array('autototal', $suppliers, true)) {
            $atClient = new AutototalClient();
            $atToken = $atClient->getAvailabilityToken();
            if ($atToken === null) {
                $responses['autototal'] = ['success' => false, 'data' => [], 'error' => 'Token Autototal indisponibil (AUTOTOTAL_USERNAME/PASSWORD).'];
            } elseif ($autototalRequests === null || $autototalRequests === []) {
                $responses['autototal'] = ['success' => false, 'data' => [], 'error' => 'Niciun request Autototal de interogat.'];
            } else {
                $baseUrl = SupplierSearchConfig::autototalAvailabilityBaseUrl();
                foreach ($autototalRequests as $requestIndex => $request) {
                    $itemkey = (string) ($request['itemkey'] ?? '');
                    if ($itemkey === '') {
                        continue;
                    }
                    $quantity = max(1, (int) ($request['quantity'] ?? 2));
                    $url = $baseUrl . '/api/Availability?' . http_build_query([
                        'itemkey' => $itemkey,
                        'quantity' => $quantity,
                    ]);
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $atToken, 'Accept: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => $timeout,
                        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                    ]);
                    curl_multi_add_handle($multi, $ch);
                    $handles[(int) $ch] = [
                        'key' => 'autototal',
                        'handle' => $ch,
                        'type' => 'autototal_request',
                        'request_index' => $requestIndex,
                        'meta' => ['targets' => $request['targets'] ?? []],
                    ];
                    $hasHandles = true;
                }
            }
        }

        if ($hasHandles) {
            $running = null;
            do {
                curl_multi_exec($multi, $running);
                curl_multi_select($multi, 1.0);
            } while ($running > 0);

            $autonetMerged = [];
            $autototalMerged = [];
            $autototalHadSuccess = false;

            foreach ($handles as $meta) {
                $ch = $meta['handle'];
                $body = curl_multi_getcontent($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $decoded = json_decode((string) $body, true);
                $success = $status >= 200 && $status < 300;

                if (($meta['type'] ?? '') === 'autonet_chunk') {
                    if ($success && is_array($decoded)) {
                        $chunkRows = isset($decoded[0]) ? $decoded : [$decoded];
                        $autonetMerged = array_merge($autonetMerged, $chunkRows);
                    }
                } elseif (($meta['type'] ?? '') === 'autototal_request') {
                    if ($success && is_array($decoded)) {
                        $autototalHadSuccess = true;
                        $autototalMerged[] = [
                            'response' => $decoded,
                            'meta' => $meta['meta'] ?? [],
                        ];
                    }
                } else {
                    $responses[$meta['key']] = [
                        'success' => $success,
                        'data' => is_array($decoded) ? $decoded : [],
                        'error' => $success ? '' : 'HTTP ' . $status,
                    ];
                }

                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }
            curl_multi_close($multi);

            if (in_array('autonet', $suppliers, true) && !isset($responses['autonet'])) {
                $responses['autonet'] = [
                    'success' => $autonetMerged !== [],
                    'data' => $autonetMerged,
                    'error' => $autonetMerged !== [] ? '' : 'Răspuns Autonet gol sau invalid.',
                ];
            }

            if (in_array('autototal', $suppliers, true) && !isset($responses['autototal'])) {
                $responses['autototal'] = [
                    'success' => $autototalHadSuccess,
                    'data' => $autototalMerged,
                    'error' => $autototalHadSuccess ? '' : 'Răspuns Autototal gol sau invalid.',
                ];
            }
        } else {
            curl_multi_close($multi);
        }

        return $responses;
    }

    /** @return array<int, array{productCode:string,quantity:int}> */
    private function buildAutopartnerProducts(string $query, PartsCatalogLookup $catalogLookup): array
    {
        $products = [['productCode' => $query, 'quantity' => 1]];
        $seen = [AutopartnerParser::normalizeCode($query) => true];

        foreach ($catalogLookup->getAllRowsByCode($query) as $row) {
            $code = AutopartnerParser::normalizeCode((string) ($row->mainart_code_parts ?? ''));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $products[] = ['productCode' => (string) ($row->mainart_code_parts ?? $code), 'quantity' => 1];
        }

        return array_slice($products, 0, 20);
    }

    /** @return array<string, array<string, mixed>> */
    private function buildAutopartnerSeedMap(string $query, PartsCatalogLookup $catalogLookup): array
    {
        $map = [];
        foreach ($catalogLookup->getAllRowsByCode($query) as $row) {
            $code = AutopartnerParser::normalizeCode((string) ($row->mainart_code_parts ?? ''));
            if ($code === '') {
                continue;
            }
            $map[$code] = [
                'manufacturer' => $row->mainart_brands ?? null,
                'db_name' => $row->brands ?? null,
                'order_code' => $row->mainart_code_parts ?? $code,
            ];
        }

        return $map;
    }

    /** @param array<string, array{success:bool,error:string}> $responses @param array<int, string> $suppliers @return array<string, string> */
    private function collectErrors(array $responses, ?\PDO $legacyPdo, array $suppliers): array
    {
        $errors = [];
        foreach ($responses as $supplier => $response) {
            if (empty($response['success']) && !empty($response['error'])) {
                $errors[$supplier] = (string) $response['error'];
            }
        }

        if (in_array('elit', $suppliers, true) && $legacyPdo === null) {
            $errors['elit'] = 'Conexiune legacy indisponibilă (LEGACY_DB_*).';
        }

        if (in_array('autonet', $suppliers, true) && $legacyPdo === null) {
            $errors['autonet'] = ($errors['autonet'] ?? '') !== ''
                ? $errors['autonet']
                : 'Conexiune legacy indisponibilă pentru mapare Autonet (LEGACY_DB_*).';
        }

        if (in_array('autototal', $suppliers, true) && $legacyPdo === null) {
            $errors['autototal'] = ($errors['autototal'] ?? '') !== ''
                ? $errors['autototal']
                : 'Conexiune legacy indisponibilă pentru mapare Autototal (LEGACY_DB_*).';
        }

        return $errors;
    }
}
