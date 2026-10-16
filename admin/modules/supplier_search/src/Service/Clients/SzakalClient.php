<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Clients;

use Besoiu\Core\Http\CurlMultiPool;
use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;
use PDO;

/**
 * Client API Szakál Metál Webservice (https://webservice.szakalmetal.hu).
 * Autentificare HTTP Basic Auth; căutare paralelă prin /product/code, /product/teccom, /product/{glid} + /stock_info.
 */
final class SzakalClient
{
    private const MAX_LOOKUP_REQUESTS = 24;
    private const MAX_STOCK_REQUESTS = 30;
    private const STOCK_QUERY_QUANTITY = 50;

    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * @param array<int, object>|null $catalogRows
     * @return array{success:bool,data:array{products:array<int,array<string,mixed>>,stocks:array<string,array<int,array<string,mixed>>>},error:string}
     */
    public function search(
        string $query,
        ?PDO $legacyPdo = null,
        ?array $catalogRows = null,
        int $timeout = 12,
        int $connectTimeout = 4
    ): array {
        $creds = SupplierSearchConfig::szakalCredentials();
        if ($creds['username'] === '' || $creds['password'] === '') {
            $this->lastError = 'Credențiale Szakál Metál lipsă (SZAKAL_USERNAME / SZAKAL_PASSWORD).';

            return [
                'success' => false,
                'data' => ['products' => [], 'stocks' => []],
                'error' => $this->lastError,
            ];
        }

        $lookups = $this->buildLookupRequests($query, $legacyPdo, $catalogRows);
        if ($lookups === []) {
            return [
                'success' => true,
                'data' => ['products' => [], 'stocks' => []],
                'error' => '',
            ];
        }

        $activeUser = $creds['username'];
        $wave1 = $this->executeLookupWave($lookups, $activeUser, $creds['password'], $timeout, $connectTimeout);
        if ($wave1['unauthorized'] && !empty($creds['fallback_username']) && $creds['fallback_username'] !== $activeUser) {
            $activeUser = (string) $creds['fallback_username'];
            $wave1 = $this->executeLookupWave($lookups, $activeUser, $creds['password'], $timeout, $connectTimeout);
        }

        if (!$wave1['any_success']) {
            $this->lastError = $wave1['error'] !== '' ? $wave1['error'] : 'Eroare la interogarea Szakál Metál API.';

            return [
                'success' => false,
                'data' => ['products' => [], 'stocks' => []],
                'error' => $this->lastError,
            ];
        }

        $productsByGlid = $wave1['products'];
        if ($productsByGlid === []) {
            return [
                'success' => true,
                'data' => ['products' => [], 'stocks' => []],
                'error' => '',
            ];
        }

        $glids = array_slice(array_keys($productsByGlid), 0, self::MAX_STOCK_REQUESTS);
        $stocksByGlid = $this->executeStockWave($glids, $activeUser, $creds['password'], $timeout, $connectTimeout);

        return [
            'success' => true,
            'data' => [
                'products' => array_values($productsByGlid),
                'stocks' => $stocksByGlid,
            ],
            'error' => '',
        ];
    }

    /**
     * Comandă prin token (/order_by_token).
     *
     * @return array{status:int,data:array<string,mixed>,error:string}
     */
    public function orderByToken(string $stockToken, int $quantity, string $orderNote = ''): array
    {
        $payload = [
            'stock_token' => trim($stockToken),
            'quantity' => max(1, min(50, $quantity)),
        ];
        if (trim($orderNote) !== '') {
            $payload['order_note'] = trim($orderNote);
        }

        return $this->requestJson('POST', '/order_by_token', $payload);
    }

    /**
     * Comandă prin GLID (/order) — fallback dacă tokenul a expirat.
     *
     * @return array{status:int,data:array<string,mixed>,error:string}
     */
    public function orderByGlid(string $glid, int $quantity, string $orderNote = '', string $searchOrder = 'fastest_first'): array
    {
        $payload = [
            'glid' => trim($glid),
            'quantity' => max(1, min(50, $quantity)),
            'search_order' => in_array($searchOrder, ['fastest_first', 'cheapest_first'], true) ? $searchOrder : 'fastest_first',
        ];
        if (trim($orderNote) !== '') {
            $payload['order_note'] = trim($orderNote);
        }

        return $this->requestJson('POST', '/order', $payload);
    }

    /**
     * Test conexiune (/finger).
     *
     * @return array{status:int,data:array<string,mixed>,error:string}
     */
    public function finger(): array
    {
        return $this->requestJson('GET', '/finger');
    }

    /**
     * @param array<int, object>|null $catalogRows
     * @return list<array{method:string,path:string,payload:?array<string,mixed>,meta:array<string,mixed>}>
     */
    private function buildLookupRequests(string $query, ?PDO $legacyPdo, ?array $catalogRows): array
    {
        $requests = [];
        $seen = [];

        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        if (preg_match('/^\d+-\d+$/', $trimmed)) {
            $key = 'glid:' . $trimmed;
            $seen[$key] = true;
            $requests[] = [
                'method' => 'GET',
                'path' => '/product/' . rawurlencode($trimmed),
                'payload' => null,
                'meta' => [
                    'type' => 'glid',
                    'target_base_code' => null,
                    'target_brand' => null,
                    'db_name' => null,
                ],
            ];
        }

        $directKey = 'code:' . strtoupper($trimmed);
        $seen[$directKey] = true;
        $requests[] = [
            'method' => 'POST',
            'path' => '/product/code',
            'payload' => ['code' => $trimmed],
            'meta' => [
                'type' => 'direct_code',
                'target_base_code' => null,
                'target_brand' => null,
                'db_name' => null,
            ],
        ];

        $rows = $catalogRows;
        if ($rows === null && $legacyPdo !== null) {
            try {
                $stmt = $legacyPdo->prepare(
                    'SELECT code_parts, mainart_code_parts, mainart_brands, brands, brand_id
                     FROM parts_catalog
                     WHERE code_parts = ?
                     LIMIT 30'
                );
                $stmt->execute([$trimmed]);
                $fetched = $stmt->fetchAll(PDO::FETCH_OBJ);
                $rows = is_array($fetched) ? $fetched : [];
            } catch (\Throwable) {
                $rows = [];
            }
        }

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (count($requests) >= self::MAX_LOOKUP_REQUESTS) {
                    break;
                }
                $mainCode = trim((string) ($row->mainart_code_parts ?? ''));
                if ($mainCode === '') {
                    continue;
                }
                $baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainCode) ?: $mainCode;
                $brandName = trim((string) ($row->mainart_brands ?? ''));
                $dbName = isset($row->brands) ? trim((string) $row->brands) : null;
                $brandId = trim((string) ($row->brand_id ?? ''));

                if ($brandId !== '') {
                    $key = 'teccom:' . $brandId . '|' . strtoupper($mainCode);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $requests[] = [
                        'method' => 'POST',
                        'path' => '/product/teccom',
                        'payload' => [
                            'artnr' => $mainCode,
                            'dlnr' => $brandId,
                        ],
                        'meta' => [
                            'type' => 'teccom',
                            'target_base_code' => $baseCode,
                            'target_brand' => $brandName !== '' ? $brandName : null,
                            'db_name' => $dbName !== '' ? $dbName : null,
                        ],
                    ];
                } else {
                    $key = 'code:' . strtoupper($mainCode);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $requests[] = [
                        'method' => 'POST',
                        'path' => '/product/code',
                        'payload' => [
                            'code' => $mainCode,
                        ],
                        'meta' => [
                            'type' => 'catalog_code',
                            'target_base_code' => $baseCode,
                            'target_brand' => $brandName !== '' ? $brandName : null,
                            'db_name' => $dbName !== '' ? $dbName : null,
                        ],
                    ];
                }
            }
        }

        return array_slice($requests, 0, self::MAX_LOOKUP_REQUESTS);
    }

    /**
     * @param list<array{method:string,path:string,payload:?array<string,mixed>,meta:array<string,mixed>}> $lookups
     * @return array{any_success:bool,unauthorized:bool,products:array<string,array<string,mixed>>,error:string}
     */
    private function executeLookupWave(
        array $lookups,
        string $username,
        string $password,
        int $timeout,
        int $connectTimeout
    ): array {
        $baseUrl = SupplierSearchConfig::szakalBaseUrl();
        $handles = [];

        foreach ($lookups as $idx => $req) {
            $ch = curl_init($baseUrl . $req['path']);
            $headers = [
                'Accept: application/json',
                'Accept-Language: ro',
            ];
            $opts = [
                CURLOPT_CUSTOMREQUEST => $req['method'],
                CURLOPT_USERPWD => $username . ':' . $password,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            ];
            if ($req['payload'] !== null) {
                $headers[] = 'Content-Type: application/json';
                $opts[CURLOPT_POSTFIELDS] = json_encode($req['payload'], JSON_UNESCAPED_UNICODE);
            }
            $opts[CURLOPT_HTTPHEADER] = $headers;
            curl_setopt_array($ch, $opts);
            SupplierSearchConfig::configureCurl($ch);
            $handles[$idx] = $ch;
        }

        $poolResults = [];
        try {
            CurlMultiPool::run($handles, (float) $timeout + 2.0, 0.25, $poolResults);
        } finally {
            foreach ($handles as $ch) {
                curl_close($ch);
            }
        }

        $productsByGlid = [];
        $anySuccess = false;
        $unauthorized = false;
        $lastErr = '';

        foreach ($lookups as $idx => $req) {
            $res = $poolResults[$idx] ?? ['body' => '', 'status' => 0, 'error' => 'Timeout pool Szakál'];
            $status = (int) ($res['status'] ?? 0);
            $curlErr = trim((string) ($res['error'] ?? ''));

            if ($status === 401) {
                $unauthorized = true;
                $lastErr = 'Autentificare Szakál Metál eșuată (HTTP 401).';
                continue;
            }
            if ($status === 404) {
                $anySuccess = true;
                continue;
            }
            if ($status < 200 || $status >= 300 || $curlErr !== '') {
                $lastErr = $curlErr !== '' ? $curlErr : ('Szakál HTTP ' . $status);
                continue;
            }

            $anySuccess = true;
            $decoded = json_decode((string) ($res['body'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }

            $meta = $req['meta'];
            $items = isset($decoded['glid']) ? [$decoded] : $decoded;
            foreach ($items as $prod) {
                if (!is_array($prod)) {
                    continue;
                }
                $glid = trim((string) ($prod['glid'] ?? ''));
                if ($glid === '') {
                    continue;
                }

                if (($meta['type'] ?? '') === 'catalog_code' && !empty($meta['target_brand'])) {
                    $prodMaker = trim((string) ($prod['maker'] ?? ''));
                    if (!$this->brandsMatch($prodMaker, (string) $meta['target_brand'])) {
                        continue;
                    }
                }

                if (!isset($productsByGlid[$glid])) {
                    $prod['_target_base_code'] = $meta['target_base_code'] ?? null;
                    $prod['_target_brand'] = $meta['target_brand'] ?? null;
                    $prod['_db_name'] = $meta['db_name'] ?? null;
                    $productsByGlid[$glid] = $prod;
                } else {
                    if (empty($productsByGlid[$glid]['_db_name']) && !empty($meta['db_name'])) {
                        $productsByGlid[$glid]['_db_name'] = $meta['db_name'];
                    }
                    if (empty($productsByGlid[$glid]['_target_brand']) && !empty($meta['target_brand'])) {
                        $productsByGlid[$glid]['_target_brand'] = $meta['target_brand'];
                    }
                }
            }
        }

        return [
            'any_success' => $anySuccess,
            'unauthorized' => $unauthorized && !$anySuccess,
            'products' => $productsByGlid,
            'error' => $lastErr,
        ];
    }

    /**
     * @param list<string> $glids
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function executeStockWave(
        array $glids,
        string $username,
        string $password,
        int $timeout,
        int $connectTimeout
    ): array {
        if ($glids === []) {
            return [];
        }

        $baseUrl = SupplierSearchConfig::szakalBaseUrl();
        $handles = [];

        foreach ($glids as $idx => $glid) {
            $ch = curl_init($baseUrl . '/stock_info');
            $payload = json_encode([
                'glid' => $glid,
                'quantity' => self::STOCK_QUERY_QUANTITY,
                'search_order' => 'fastest_first',
            ], JSON_UNESCAPED_UNICODE);

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_USERPWD => $username . ':' . $password,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            ]);
            SupplierSearchConfig::configureCurl($ch);
            $handles[$idx] = $ch;
        }

        $poolResults = [];
        try {
            CurlMultiPool::run($handles, (float) $timeout + 2.0, 0.25, $poolResults);
        } finally {
            foreach ($handles as $ch) {
                curl_close($ch);
            }
        }

        $stocksByGlid = [];
        foreach ($glids as $idx => $glid) {
            $res = $poolResults[$idx] ?? ['body' => '', 'status' => 0];
            $status = (int) ($res['status'] ?? 0);
            if ($status < 200 || $status >= 300) {
                continue;
            }
            $decoded = json_decode((string) ($res['body'] ?? ''), true);
            $stocks = is_array($decoded['stocks'] ?? null) ? $decoded['stocks'] : [];
            if ($stocks !== []) {
                $stocksByGlid[$glid] = $stocks;
            }
        }

        return $stocksByGlid;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{status:int,data:array<string,mixed>,error:string}
     */
    private function requestJson(string $method, string $path, ?array $payload = null): array
    {
        $creds = SupplierSearchConfig::szakalCredentials();
        if ($creds['username'] === '' || $creds['password'] === '') {
            return ['status' => 0, 'data' => [], 'error' => 'Szakál Metál nu este configurat.'];
        }

        $res = $this->doSingleRequest($method, $path, $payload, $creds['username'], $creds['password']);
        if ($res['status'] === 401 && !empty($creds['fallback_username']) && $creds['fallback_username'] !== $creds['username']) {
            $res = $this->doSingleRequest($method, $path, $payload, (string) $creds['fallback_username'], $creds['password']);
        }

        return $res;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{status:int,data:array<string,mixed>,error:string}
     */
    private function doSingleRequest(string $method, string $path, ?array $payload, string $username, string $password): array
    {
        $baseUrl = SupplierSearchConfig::szakalBaseUrl();
        $ch = curl_init($baseUrl . $path);
        $headers = [
            'Accept: application/json',
            'Accept-Language: ro',
        ];
        $opts = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 6,
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        SupplierSearchConfig::configureCurl($ch);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = trim((string) curl_error($ch));
        curl_close($ch);

        if ($raw === false || $err !== '') {
            return ['status' => $status, 'data' => [], 'error' => $err !== '' ? $err : 'Eroare rețea Szakál Metál.'];
        }

        $decoded = json_decode((string) $raw, true);
        $data = is_array($decoded) ? $decoded : [];
        $apiError = '';
        if ($status < 200 || $status >= 300) {
            $apiError = trim((string) ($data['message'] ?? ('HTTP ' . $status)));
        }

        return [
            'status' => $status,
            'data' => $data,
            'error' => $apiError,
        ];
    }

    private function brandsMatch(string $actual, string $expected): bool
    {
        $a = $this->normalizeBrand($actual);
        $b = $this->normalizeBrand($expected);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b || str_starts_with($a, $b) || str_starts_with($b, $a)) {
            return true;
        }

        return false;
    }

    private function normalizeBrand(string $brand): string
    {
        $upper = strtoupper(trim($brand));
        if (in_array($upper, ['DEPO', 'LORO', 'LORO TUNING', 'ABAKUS-DEPO', 'DEP', 'LOR', 'ABA'], true)) {
            return 'ABAKUS';
        }
        if ($upper === 'VALEO FIRST') {
            return 'VALEO';
        }
        if ($upper === 'MANN-FILTER') {
            return 'MANN';
        }

        return preg_replace('/[^A-Z0-9]/', '', $upper) ?? '';
    }
}
