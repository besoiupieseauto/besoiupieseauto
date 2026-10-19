<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Clients;

use Besoiu\Core\Http\CurlMultiPool;
use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;
use Besoiu\Modules\SupplierSearch\Service\WebshopCartMirrorService;

/**
 * Client API InterCars e-Cat (ecat-pct.intercars.eu).
 * Folosește credențialele salvate de scraper în ic_creds.json.
 */
final class IntercarsClient
{
    private const MAX_PRODUCTS = 25;

    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * Caută produse pe InterCars și aduce în paralel prețurile angro + disponibilitatea/livrarea.
     *
     * @return array{success:bool,data:array{products:array<int,array<string,mixed>>,prices:array<string,array<string,mixed>>,availability:array<string,array<string,mixed>>},error:string}
     */
    public function search(string $query, int $timeout = 12, int $connectTimeout = 4): array
    {
        $creds = $this->loadCredentials();
        if ($creds === null) {
            return [
                'success' => false,
                'data' => ['products' => [], 'prices' => [], 'availability' => []],
                'error' => $this->lastError !== '' ? $this->lastError : 'Credențiale InterCars indisponibile (ic_creds.json).',
            ];
        }

        $firstAttempt = $this->fetchProducts($query, $creds, $timeout, $connectTimeout);
        if (!$firstAttempt['success'] && in_array($firstAttempt['status'], [401, 403], true)) {
            if ($this->triggerScraperRelogin()) {
                $creds = $this->loadCredentials();
                if ($creds !== null) {
                    $firstAttempt = $this->fetchProducts($query, $creds, $timeout, $connectTimeout);
                }
            }
        }

        if (!$firstAttempt['success']) {
            return [
                'success' => false,
                'data' => ['products' => [], 'prices' => [], 'availability' => []],
                'error' => $firstAttempt['error'],
            ];
        }

        $products = $firstAttempt['products'];
        if ($products === []) {
            return [
                'success' => true,
                'data' => ['products' => [], 'prices' => [], 'availability' => []],
                'error' => '',
            ];
        }

        $products = array_slice($products, 0, self::MAX_PRODUCTS);
        $towKods = [];
        foreach ($products as $prod) {
            $tk = trim((string) ($prod['towKod'] ?? ''));
            if ($tk !== '') {
                $towKods[$tk] = true;
            }
        }

        if ($towKods === []) {
            return [
                'success' => true,
                'data' => ['products' => $products, 'prices' => [], 'availability' => []],
                'error' => '',
            ];
        }

        $towKodList = array_keys($towKods);
        $enrichment = $this->fetchPricesAndAvailability($towKodList, $creds, $timeout, $connectTimeout);

        return [
            'success' => true,
            'data' => [
                'products' => $products,
                'prices' => $enrichment['prices'],
                'availability' => $enrichment['availability'],
            ],
            'error' => '',
        ];
    }

    /**
     * @return array{headers:list<string>,cookie:string}|null
     */
    private function loadCredentials(): ?array
    {
        $path = SupplierSearchConfig::intercarsCredsPath();
        if (!is_file($path)) {
            $this->lastError = 'Fișierul ic_creds.json nu a fost găsit.';
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            $this->lastError = 'Fișierul ic_creds.json este invalid.';
            return null;
        }

        $rawHeaders = is_array($decoded['headers'] ?? null) ? $decoded['headers'] : [];
        $auth = trim((string) ($rawHeaders['Authorization'] ?? $rawHeaders['authorization'] ?? ''));
        if ($auth === '') {
            $this->lastError = 'Lipsește tokenul Authorization din ic_creds.json.';
            return null;
        }

        $headers = [
            'Authorization: ' . $auth,
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/json',
            'Origin: https://e-cat.intercars.eu',
            'Referer: https://e-cat.intercars.eu/',
            'x-api-key: ' . trim((string) ($rawHeaders['x-api-key'] ?? 'b2b-ecat-frontend')),
            'x-source: ' . trim((string) ($rawHeaders['x-source'] ?? 'listing-microfrontend')),
            'User-Agent: ' . trim((string) ($rawHeaders['User-Agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36')),
        ];

        $cookiePairs = [];
        $cookies = is_array($decoded['cookies'] ?? null) ? $decoded['cookies'] : [];
        foreach ($cookies as $c) {
            if (!is_array($c)) {
                continue;
            }
            $name = trim((string) ($c['name'] ?? ''));
            $val = (string) ($c['value'] ?? '');
            if ($name !== '') {
                $cookiePairs[] = $name . '=' . $val;
            }
        }

        return [
            'headers' => $headers,
            'cookie' => implode('; ', $cookiePairs),
        ];
    }

    /**
     * @param array{headers:list<string>,cookie:string} $creds
     * @return array{success:bool,products:array<int,array<string,mixed>>,status:int,error:string}
     */
    private function fetchProducts(string $query, array $creds, int $timeout, int $connectTimeout): array
    {
        $baseUrl = SupplierSearchConfig::intercarsBaseUrl();
        $cust = SupplierSearchConfig::intercarsCustomerCode();
        $branch = SupplierSearchConfig::intercarsBranch();

        $url = $baseUrl . '/products?' . http_build_query([
            'pageSize' => self::MAX_PRODUCTS,
            'pageNumber' => 0,
            'customerCode' => $cust,
            'customerBusinessUnitCode' => $branch,
            'sort' => 'default',
            'x-source' => 'listing-microfrontend',
            'q' => $query,
        ]);

        $ch = curl_init($url);
        SupplierSearchConfig::configureCurl($ch);
        $opts = [
            CURLOPT_HTTPHEADER => $creds['headers'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ];
        if ($creds['cookie'] !== '') {
            $opts[CURLOPT_COOKIE] = $creds['cookie'];
        }
        curl_setopt_array($ch, $opts);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = trim((string) curl_error($ch));
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            return [
                'success' => false,
                'products' => [],
                'status' => $status,
                'error' => $curlError !== '' ? $curlError : 'Eroare rețea InterCars.',
            ];
        }

        if ($status < 200 || $status >= 300) {
            return [
                'success' => false,
                'products' => [],
                'status' => $status,
                'error' => 'InterCars HTTP ' . $status,
            ];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'products' => [],
                'status' => $status,
                'error' => 'Răspuns JSON invalid de la InterCars.',
            ];
        }

        $products = is_array($decoded['products'] ?? null) ? $decoded['products'] : [];

        return [
            'success' => true,
            'products' => $products,
            'status' => $status,
            'error' => '',
        ];
    }

    /**
     * @param list<string> $towKods
     * @param array{headers:list<string>,cookie:string} $creds
     * @return array{prices:array<string,array<string,mixed>>,availability:array<string,array<string,mixed>>}
     */
    private function fetchPricesAndAvailability(array $towKods, array $creds, int $timeout, int $connectTimeout): array
    {
        $baseUrl = SupplierSearchConfig::intercarsBaseUrl();
        $cust = SupplierSearchConfig::intercarsCustomerCode();
        $branch = SupplierSearchConfig::intercarsBranch();

        $quantityLines = [];
        $availPayload = [];
        foreach ($towKods as $tk) {
            $quantityLines[] = ['towKod' => $tk, 'quantity' => 1];
            $availPayload[] = ['productCode' => $tk, 'quantity' => 10];
        }

        $priceUrl = $baseUrl . '/wholesale-prices';
        $priceBody = json_encode([
            'currencyCode' => 'RON',
            'customerCode' => $cust,
            'shipFromBusinessUnitCode' => $branch,
            'quantityLines' => $quantityLines,
        ], JSON_UNESCAPED_UNICODE);

        $availUrl = $baseUrl . '/availability?' . http_build_query([
            'customerCode' => $cust,
            'customerBusinessUnitCode' => $branch,
            'shipFromBusinessUnitCode' => $branch,
        ]);
        $availBody = json_encode($availPayload, JSON_UNESCAPED_UNICODE);

        $chPrice = curl_init($priceUrl);
        SupplierSearchConfig::configureCurl($chPrice);
        $optsPrice = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $priceBody,
            CURLOPT_HTTPHEADER => $creds['headers'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ];
        if ($creds['cookie'] !== '') {
            $optsPrice[CURLOPT_COOKIE] = $creds['cookie'];
        }
        curl_setopt_array($chPrice, $optsPrice);

        $chAvail = curl_init($availUrl);
        SupplierSearchConfig::configureCurl($chAvail);
        $optsAvail = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $availBody,
            CURLOPT_HTTPHEADER => $creds['headers'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ];
        if ($creds['cookie'] !== '') {
            $optsAvail[CURLOPT_COOKIE] = $creds['cookie'];
        }
        curl_setopt_array($chAvail, $optsAvail);

        $handles = [$chPrice, $chAvail];
        $poolResults = [];
        try {
            CurlMultiPool::run($handles, (float) $timeout + 2.0, 0.25, $poolResults);
        } finally {
            curl_close($chPrice);
            curl_close($chAvail);
        }

        $pricesByTowKod = [];
        $priceRes = $poolResults[0] ?? ['body' => '', 'status' => 0];
        if (($priceRes['status'] ?? 0) >= 200 && ($priceRes['status'] ?? 0) < 300) {
            $decodedPrices = json_decode((string) ($priceRes['body'] ?? ''), true);
            if (is_array($decodedPrices)) {
                foreach ($decodedPrices as $pRow) {
                    if (!is_array($pRow)) {
                        continue;
                    }
                    $tk = trim((string) ($pRow['towKod'] ?? ''));
                    if ($tk !== '') {
                        $pricesByTowKod[$tk] = $pRow;
                    }
                }
            }
        }

        $availByTowKod = [];
        $availRes = $poolResults[1] ?? ['body' => '', 'status' => 0];
        if (($availRes['status'] ?? 0) >= 200 && ($availRes['status'] ?? 0) < 300) {
            $decodedAvail = json_decode((string) ($availRes['body'] ?? ''), true);
            if (is_array($decodedAvail)) {
                foreach ($decodedAvail as $aRow) {
                    if (!is_array($aRow)) {
                        continue;
                    }
                    $tk = trim((string) ($aRow['productCode'] ?? $aRow['towKod'] ?? ''));
                    if ($tk !== '') {
                        $availByTowKod[$tk] = $aRow;
                    }
                }
            }
        }

        return [
            'prices' => $pricesByTowKod,
            'availability' => $availByTowKod,
        ];
    }

    private function triggerScraperRelogin(): bool
    {
        $url = 'http://127.0.0.1/scraper/api.php?action=login_supplier';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['supplier' => 'intercars']),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 300) {
            $decoded = json_decode((string) $body, true);
            if (is_array($decoded) && (($decoded['status'] ?? '') === 'ok' || !empty($decoded['success']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    public function listCart(int $timeout = 12): array
    {
        return $this->cartRequest('GET', '/cart', null, $timeout);
    }

    /**
     * @return array{success:bool,id:string,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    public function addCartLine(string $towKod, int $qty, int $timeout = 12): array
    {
        $towKod = trim($towKod);
        $qty = max(1, $qty);
        if ($towKod === '') {
            return ['success' => false, 'id' => '', 'lines' => [], 'error' => 'Lipsește towKod InterCars.', 'status' => 0];
        }

        $before = $this->listCart($timeout);
        $added = $this->cartRequest('POST', '/cart', [
            'customerCode' => SupplierSearchConfig::intercarsCustomerCode(),
            'customerBusinessUnitCode' => SupplierSearchConfig::intercarsBranch(),
            'items' => [['towKod' => $towKod, 'productCode' => $towKod, 'quantity' => $qty]],
        ], $timeout);
        $after = $added['success'] ? $added : $this->listCart($timeout);
        $newId = $this->diffNewLineId($before['lines'] ?? [], $after['lines'] ?? [], $towKod);

        return [
            'success' => $newId !== '' || $added['success'],
            'id' => $newId,
            'lines' => $after['lines'] ?? [],
            'error' => $newId === '' ? (string) ($added['error'] ?? $after['error'] ?? 'Nu s-a putut identifica linia adăugată.') : '',
            'status' => (int) ($added['status'] ?? $after['status'] ?? 0),
        ];
    }

    /**
     * Citește pagina de checkout fără a trimite comanda.
     *
     * @return array{success:bool,ready:bool,error:string,status:int}
     */
    public function previewCheckout(int $timeout = 12): array
    {
        $result = $this->cartRequest('GET', '/cart/checkout', null, $timeout);
        if (!$result['success']) {
            $result = $this->cartRequest('GET', '/checkout', null, $timeout);
        }

        return [
            'success' => $result['success'],
            'ready' => $result['success'],
            'error' => $result['error'],
            'status' => $result['status'],
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    private function cartRequest(string $method, string $path, ?array $payload, int $timeout): array
    {
        $creds = $this->loadCredentials();
        if ($creds === null) {
            return ['success' => false, 'lines' => [], 'error' => $this->lastError, 'status' => 0];
        }

        $cust = SupplierSearchConfig::intercarsCustomerCode();
        $branch = SupplierSearchConfig::intercarsBranch();
        $url = SupplierSearchConfig::intercarsBaseUrl() . $path;
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query([
            'customerCode' => $cust,
            'customerBusinessUnitCode' => $branch,
        ]);

        $headers = $creds['headers'];
        $headers[] = 'x-source: cart-microfrontend';

        $ch = curl_init($url);
        SupplierSearchConfig::configureCurl($ch);
        $opts = [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($creds['cookie'] !== '') {
            $opts[CURLOPT_COOKIE] = $creds['cookie'];
        }
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = trim((string) curl_error($ch));
        curl_close($ch);

        if ($body === false || $error !== '') {
            return ['success' => false, 'lines' => [], 'error' => $error !== '' ? $error : 'Eroare rețea InterCars.', 'status' => $status];
        }
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'lines' => [], 'error' => 'InterCars HTTP ' . $status, 'status' => $status];
        }

        $decoded = json_decode((string) $body, true);

        return [
            'success' => true,
            'lines' => WebshopCartMirrorService::extractLines(is_array($decoded) ? $decoded : ['raw' => (string) $body]),
            'error' => '',
            'status' => $status,
        ];
    }

    /**
     * @param list<array{id:string,code:string,qty:int}> $before
     * @param list<array{id:string,code:string,qty:int}> $after
     */
    private function diffNewLineId(array $before, array $after, string $code): string
    {
        $beforeIds = [];
        foreach ($before as $line) {
            $beforeIds[(string) ($line['id'] ?? '')] = true;
        }
        foreach ($after as $line) {
            $id = trim((string) ($line['id'] ?? ''));
            if ($id !== '' && !isset($beforeIds[$id])) {
                return $id;
            }
        }
        foreach ($after as $line) {
            if (strcasecmp((string) ($line['code'] ?? ''), $code) === 0 && trim((string) ($line['id'] ?? '')) !== '') {
                return (string) $line['id'];
            }
        }

        return '';
    }
}
