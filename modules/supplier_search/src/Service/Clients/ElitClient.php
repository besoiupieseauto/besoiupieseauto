<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Clients;

use Besoiu\Core\Http\CurlMultiPool;
use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;
use Besoiu\Modules\SupplierSearch\Service\WebshopCartMirrorService;

/**
 * Client live pentru Elit România (www.elit.ro).
 * Folosește sesiunea din cache / elit_state.json și face auto-login HTTP la expirare.
 */
final class ElitClient
{
    private const MAX_PRODUCTS = 25;
    private const SESSION_TTL = 28800; // 8 ore

    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * Caută un cod pe Elit.ro și returnează lista de produse împreună cu prețurile de achiziție și disponibilitatea live.
     *
     * @return array{success:bool,data:array{products:array<int,array<string,mixed>>,prices:array<string,array<string,mixed>>,disponibility:array<string,array<string,mixed>>},error:string}
     */
    public function search(string $query, int $timeout = 12, int $connectTimeout = 4): array
    {
        $cookieHeader = $this->getOrCreateSessionCookie($timeout, $connectTimeout);
        if ($cookieHeader === '') {
            return [
                'success' => false,
                'data' => ['products' => [], 'prices' => [], 'disponibility' => []],
                'error' => $this->lastError !== '' ? $this->lastError : 'Nu s-a putut inițializa sesiunea Elit.',
            ];
        }

        $searchRes = $this->searchProducts($query, $cookieHeader, $timeout, $connectTimeout);
        if (!$searchRes['success']) {
            // Re-login și retry o dată dacă sesiunea a expirat
            $cookieHeader = $this->performHttpLogin($timeout, $connectTimeout);
            if ($cookieHeader !== '') {
                $searchRes = $this->searchProducts($query, $cookieHeader, $timeout, $connectTimeout);
            }
        }

        if (!$searchRes['success']) {
            return [
                'success' => false,
                'data' => ['products' => [], 'prices' => [], 'disponibility' => []],
                'error' => $searchRes['error'],
            ];
        }

        $products = $searchRes['products'];
        if ($products === []) {
            return [
                'success' => true,
                'data' => ['products' => [], 'prices' => [], 'disponibility' => []],
                'error' => '',
            ];
        }

        $products = array_slice($products, 0, self::MAX_PRODUCTS);
        $productIds = [];
        foreach ($products as $prod) {
            $id = trim((string) ($prod['Id'] ?? ''));
            if ($id !== '') {
                $productIds[$id] = true;
            }
        }

        if ($productIds === []) {
            return [
                'success' => true,
                'data' => ['products' => $products, 'prices' => [], 'disponibility' => []],
                'error' => '',
            ];
        }

        $idList = array_map('strval', array_keys($productIds));
        $enrichment = $this->fetchPricesAndDisponibility($idList, $cookieHeader, $timeout, $connectTimeout);

        // Dacă nu am primit niciun preț, e posibil ca sesiunea să nu fie autentificată (Login=False)
        if ($enrichment['prices'] === [] && count($idList) > 0) {
            $freshCookie = $this->performHttpLogin($timeout, $connectTimeout);
            if ($freshCookie !== '') {
                $enrichment = $this->fetchPricesAndDisponibility($idList, $freshCookie, $timeout, $connectTimeout);
            }
        }

        return [
            'success' => true,
            'data' => [
                'products' => $products,
                'prices' => $enrichment['prices'],
                'disponibility' => $enrichment['disponibility'],
            ],
            'error' => '',
        ];
    }

    private function getOrCreateSessionCookie(int $timeout, int $connectTimeout): string
    {
        $cacheFile = SupplierSearchConfig::cacheDir() . '/elit_session.json';
        if (is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (
                is_array($cached)
                && !empty($cached['cookie'])
                && (str_contains((string) $cached['cookie'], 'Login=True') || str_contains((string) $cached['cookie'], 'PersistentLogon'))
                && (int) ($cached['expires_at'] ?? 0) > time() + 60
            ) {
                return (string) $cached['cookie'];
            }
        }

        // Încearcă din elit_state.json al scraper-ului dacă are Login=True
        $statePath = SupplierSearchConfig::elitStatePath();
        if (is_file($statePath) && (time() - (int) @filemtime($statePath)) < self::SESSION_TTL) {
            $state = json_decode((string) file_get_contents($statePath), true);
            if (is_array($state) && is_array($state['cookies'] ?? null)) {
                $pairs = [];
                foreach ($state['cookies'] as $c) {
                    if (!is_array($c)) {
                        continue;
                    }
                    $domain = (string) ($c['domain'] ?? '');
                    if ($domain !== '' && !str_contains($domain, 'elit.ro')) {
                        continue;
                    }
                    $name = trim((string) ($c['name'] ?? ''));
                    $val = (string) ($c['value'] ?? '');
                    if ($name !== '') {
                        $pairs[$name] = $name . '=' . $val;
                    }
                }
                $cookieStr = implode('; ', array_values($pairs));
                if (str_contains($cookieStr, 'Login=True')) {
                    $this->saveSessionCookie($cookieStr);
                    return $cookieStr;
                }
            }
        }

        return $this->performHttpLogin($timeout, $connectTimeout);
    }

    private function performHttpLogin(int $timeout, int $connectTimeout): string
    {
        $creds = SupplierSearchConfig::elitCredentials();
        if ($creds['username'] === '' || $creds['password'] === '') {
            $this->lastError = 'Credențiale Elit lipsă (ELIT_USERNAME/ELIT_PASSWORD).';
            return '';
        }

        $baseUrl = SupplierSearchConfig::elitBaseUrl();
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
        // Pasul 1: GET homepage pentru __RequestVerificationToken + cookie-uri inițiale
        $ch = curl_init($baseUrl . '/HomePage/Car');
        SupplierSearchConfig::configureCurl($ch);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ]);

        $resp = curl_exec($ch);
        $curlErr = trim((string) curl_error($ch));
        curl_close($ch);

        if ($resp === false || $curlErr !== '') {
            $this->lastError = $curlErr !== '' ? $curlErr : 'Nu s-a putut accesa www.elit.ro.';
            return '';
        }

        $cookies = $this->extractCookiesFromRawResponse((string) $resp);
        $token = '';
        if (preg_match('/<form[^>]*action="[^"]*LogonNow"[^>]*>[\s\S]*?name="__RequestVerificationToken"[^>]*value="([^"]+)"/i', (string) $resp, $m)) {
            $token = $m[1];
        } elseif (preg_match('/id="logOnForm"[\s\S]*?name="__RequestVerificationToken"[^>]*value="([^"]+)"/i', (string) $resp, $m)) {
            $token = $m[1];
        } elseif (preg_match('/name="__RequestVerificationToken"[^>]*value="([^"]+)"/i', (string) $resp, $m)) {
            $token = $m[1];
        }

        if ($token === '') {
            $this->lastError = 'Nu s-a putut extrage tokenul CSRF de pe Elit.ro.';
            return '';
        }

        $initialCookieStr = $this->formatCookieArray($cookies);

        // Pasul 2: POST /Account/LogonNow
        $postFields = http_build_query([
            '__RequestVerificationToken' => $token,
            'UserName' => $creds['username'],
            'Password' => $creds['password'],
        ]);

        $chLogin = curl_init($baseUrl . '/Account/LogonNow');
        SupplierSearchConfig::configureCurl($chLogin);
        curl_setopt_array($chLogin, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_COOKIE => $initialCookieStr,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $baseUrl,
                'Referer: ' . $baseUrl . '/HomePage/Car',
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ]);

        $loginResp = curl_exec($chLogin);
        $loginErr = trim((string) curl_error($chLogin));
        curl_close($chLogin);

        if ($loginResp === false || $loginErr !== '') {
            $this->lastError = $loginErr !== '' ? $loginErr : 'Eroare la autentificarea Elit.ro.';
            return '';
        }

        $loginCookies = $this->extractCookiesFromRawResponse((string) $loginResp);
        foreach ($loginCookies as $k => $v) {
            $cookies[$k] = $v;
        }

        $finalCookieStr = $this->formatCookieArray($cookies);
        if (!str_contains($finalCookieStr, 'Login=True') && !str_contains($finalCookieStr, 'PersistentLogon')) {
            $this->lastError = 'Autentificarea Elit.ro a eșuat (credențiale respinse).';
            return '';
        }

        $this->saveSessionCookie($finalCookieStr);

        return $finalCookieStr;
    }

    /**
     * @return array<string, string>
     */
    private function extractCookiesFromRawResponse(string $rawResponse): array
    {
        $cookies = [];
        if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;\r\n]*)/mi', $rawResponse, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim($match[1]);
                $val = trim($match[2]);
                if ($name !== '') {
                    $cookies[$name] = $val;
                }
            }
        }

        return $cookies;
    }

    /**
     * @param array<string, string> $cookies
     */
    private function formatCookieArray(array $cookies): string
    {
        $pairs = [];
        foreach ($cookies as $name => $val) {
            $pairs[] = $name . '=' . $val;
        }

        return implode('; ', $pairs);
    }

    private function saveSessionCookie(string $cookieStr): void
    {
        $cacheFile = SupplierSearchConfig::cacheDir() . '/elit_session.json';
        @file_put_contents($cacheFile, json_encode([
            'cookie' => $cookieStr,
            'expires_at' => time() + self::SESSION_TTL,
            'updated_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{success:bool,products:array<int,array<string,mixed>>,error:string}
     */
    private function searchProducts(string $query, string $cookieHeader, int $timeout, int $connectTimeout): array
    {
        $baseUrl = SupplierSearchConfig::elitBaseUrl();
        $url = $baseUrl . '/Search2/Product/Data';
        $payload = json_encode([
            'searchText' => $query,
            'filterId' => '',
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        SupplierSearchConfig::configureCurl($ch);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_COOKIE => $cookieHeader,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json, text/plain, */*',
                'Origin: ' . $baseUrl,
                'Referer: ' . $baseUrl . '/HomePage/Car',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = trim((string) curl_error($ch));
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            return [
                'success' => false,
                'products' => [],
                'error' => $curlError !== '' ? $curlError : 'Eroare rețea Elit.',
            ];
        }

        if ($status < 200 || $status >= 300) {
            return [
                'success' => false,
                'products' => [],
                'error' => 'Elit HTTP ' . $status,
            ];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'products' => [],
                'error' => 'Răspuns invalid de la Elit.',
            ];
        }

        $products = is_array($decoded['products'] ?? null) ? $decoded['products'] : [];

        return [
            'success' => true,
            'products' => $products,
            'error' => '',
        ];
    }

    /**
     * @param list<string> $productIds
     * @return array{prices:array<string,array<string,mixed>>,disponibility:array<string,array<string,mixed>>}
     */
    private function fetchPricesAndDisponibility(array $productIds, string $cookieHeader, int $timeout, int $connectTimeout): array
    {
        $baseUrl = SupplierSearchConfig::elitBaseUrl();
        $payload = json_encode(['productIds' => array_values($productIds)], JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/plain, */*',
            'Origin: ' . $baseUrl,
            'Referer: ' . $baseUrl . '/HomePage/Car',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ];

        $chPrice = curl_init($baseUrl . '/UserPrice/Data');
        SupplierSearchConfig::configureCurl($chPrice);
        curl_setopt_array($chPrice, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_COOKIE => $cookieHeader,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ]);

        $chDisp = curl_init($baseUrl . '/ProductDisponibility/data');
        SupplierSearchConfig::configureCurl($chDisp);
        curl_setopt_array($chDisp, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_COOKIE => $cookieHeader,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ]);

        $handles = [$chPrice, $chDisp];
        $poolResults = [];
        try {
            CurlMultiPool::run($handles, (float) $timeout + 2.0, 0.25, $poolResults);
        } finally {
            curl_close($chPrice);
            curl_close($chDisp);
        }

        $pricesById = [];
        $priceRes = $poolResults[0] ?? ['body' => '', 'status' => 0];
        if (($priceRes['status'] ?? 0) >= 200 && ($priceRes['status'] ?? 0) < 300) {
            $decodedPrice = json_decode((string) ($priceRes['body'] ?? ''), true);
            $content = is_array($decodedPrice['Content'] ?? null) ? $decodedPrice['Content'] : [];
            foreach ($content as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = trim((string) ($row['ProductId'] ?? ''));
                if ($pid !== '') {
                    $pricesById[$pid] = $row;
                }
            }
        }

        $dispById = [];
        $dispRes = $poolResults[1] ?? ['body' => '', 'status' => 0];
        if (($dispRes['status'] ?? 0) >= 200 && ($dispRes['status'] ?? 0) < 300) {
            $decodedDisp = json_decode((string) ($dispRes['body'] ?? ''), true);
            $items = is_array($decodedDisp['items'] ?? null) ? $decodedDisp['items'] : [];
            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = trim((string) ($row['ProductId'] ?? ''));
                if ($pid !== '' && is_array($row['Disponibility'] ?? null)) {
                    $dispById[$pid] = $row['Disponibility'];
                }
            }
        }

        return [
            'prices' => $pricesById,
            'disponibility' => $dispById,
        ];
    }

    /**
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    public function listCart(int $timeout = 12): array
    {
        $cookie = $this->getOrCreateSessionCookie($timeout, 4);
        if ($cookie === '') {
            return ['success' => false, 'lines' => [], 'error' => $this->lastError, 'status' => 0];
        }

        return $this->fetchCurrentCart($cookie, $timeout);
    }

    /**
     * @return array{success:bool,id:string,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    public function addCartLine(string $productId, int $qty, int $timeout = 12): array
    {
        $lookup = trim($productId);
        $qty = max(1, $qty);
        if ($lookup === '') {
            return ['success' => false, 'id' => '', 'lines' => [], 'error' => 'Lipsește ID-ul produsului Elit.', 'status' => 0];
        }
        $cookie = $this->getOrCreateSessionCookie($timeout, 4);
        if ($cookie === '') {
            return ['success' => false, 'id' => '', 'lines' => [], 'error' => $this->lastError, 'status' => 0];
        }

        $resolved = $this->resolveProduct($lookup, $cookie, $timeout);
        $before = $this->fetchCurrentCart($cookie, $timeout);
        $added = ['success' => false, 'id' => '', 'error' => 'Nu s-a putut adăuga linia Elit.', 'status' => 0, 'raw' => []];

        if ($resolved['id'] !== '') {
            $added = $this->cartJson('POST', '/Cart/AddProduct', [
                'productId' => $resolved['id'],
                'amount' => $qty,
                'isExpressOrder' => false,
                'oeDeliveryId' => '',
                'note' => '',
                'ta2Location' => '',
                'ta2AvailableFrom' => '',
                'ta2CashAndCarry' => false,
                'bigRequired' => false,
            ], $cookie, $timeout);
        }

        $after = $this->fetchCurrentCart($cookie, $timeout);
        $newId = $this->resolveAddedLineId($added, $before['lines'] ?? [], $after['lines'] ?? [], $resolved, $lookup);
        if ($newId === '') {
            $added = $this->cartJson('POST', '/Cart/AddCode', [
                'code' => $resolved['code'] !== '' ? $resolved['code'] : $lookup,
                'amount' => $qty,
                'note' => '',
                'noteInvoice' => '',
            ], $cookie, $timeout);
            $after = $this->fetchCurrentCart($cookie, $timeout);
            $newId = $this->resolveAddedLineId($added, $before['lines'] ?? [], $after['lines'] ?? [], $resolved, $lookup);
        }

        return [
            'success' => $newId !== '',
            'id' => $newId,
            'lines' => $after['lines'] ?? [],
            'error' => $newId === '' ? (string) ($added['error'] ?? $after['error'] ?? 'Nu s-a putut identifica linia Elit.') : '',
            'status' => (int) ($added['status'] ?? $after['status'] ?? 0),
        ];
    }

    /** @return array{success:bool,ready:bool,error:string,status:int} */
    public function previewCheckout(int $timeout = 12): array
    {
        $cookie = $this->getOrCreateSessionCookie($timeout, 4);
        if ($cookie === '') {
            return ['success' => false, 'ready' => false, 'error' => $this->lastError, 'status' => 0];
        }

        $current = $this->fetchCurrentCart($cookie, $timeout);
        if (!$current['success']) {
            return ['success' => false, 'ready' => false, 'error' => $current['error'], 'status' => $current['status']];
        }

        $preview = $this->cartJson('POST', '/Cart/RecapitulationData', [], $cookie, $timeout);

        return [
            'success' => true,
            'ready' => true,
            'error' => '',
            'status' => (int) ($preview['status'] ?? $current['status']),
        ];
    }

    /**
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    private function fetchCurrentCart(string $cookie, int $timeout): array
    {
        return $this->cartJson('POST', '/Cart/Current', [], $cookie, $timeout);
    }

    /**
     * @return array{id:string,code:string}
     */
    private function resolveProduct(string $lookup, string $cookie, int $timeout): array
    {
        $search = $this->searchProducts($lookup, $cookie, $timeout, 4);
        if (!$search['success']) {
            $fresh = $this->performHttpLogin($timeout, 4);
            if ($fresh !== '') {
                $cookie = $fresh;
                $search = $this->searchProducts($lookup, $cookie, $timeout, 4);
            }
        }

        $wanted = strtoupper(preg_replace('/[^A-Z0-9]/', '', $lookup) ?? $lookup);
        $fallback = ['id' => '', 'code' => $lookup];
        foreach ($search['products'] ?? [] as $product) {
            if (!is_array($product)) {
                continue;
            }
            $id = trim((string) ($product['Id'] ?? ''));
            $code = trim((string) ($product['Code'] ?? ''));
            $ref = trim((string) ($product['ReferenceCode'] ?? ''));
            if ($id === '') {
                continue;
            }
            if ($id === $lookup) {
                return ['id' => $id, 'code' => $code !== '' ? $code : $lookup];
            }
            if ($fallback['id'] === '') {
                $fallback = ['id' => $id, 'code' => $code !== '' ? $code : $lookup];
            }
            foreach ([$code, $ref] as $candidate) {
                $norm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $candidate) ?? '');
                if ($wanted !== '' && $norm === $wanted) {
                    return ['id' => $id, 'code' => $code !== '' ? $code : $lookup];
                }
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{success:bool,id:string,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int,raw?:array<string,mixed>}
     */
    private function cartJson(string $method, string $path, ?array $payload, string $cookie, int $timeout): array
    {
        $base = SupplierSearchConfig::elitBaseUrl();
        $ch = curl_init($base . $path);
        SupplierSearchConfig::configureCurl($ch);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/plain, */*',
            'Origin: ' . $base,
            'Referer: ' . $base . '/Cart',
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ];
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_COOKIE => $cookie,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = trim((string) curl_error($ch));
        curl_close($ch);
        if ($body === false || $error !== '') {
            return ['success' => false, 'id' => '', 'lines' => [], 'error' => $error !== '' ? $error : 'Eroare rețea Elit.', 'status' => $status];
        }
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'id' => '', 'lines' => [], 'error' => 'Elit HTTP ' . $status, 'status' => $status];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            $ok = trim((string) $body) === '' || trim((string) $body) === '""';

            return [
                'success' => $ok,
                'id' => '',
                'lines' => [],
                'error' => $ok ? '' : 'Răspuns invalid de la Elit.',
                'status' => $status,
            ];
        }

        $lineId = trim((string) ($decoded['CartItemId'] ?? $decoded['cartItemId'] ?? ''));

        return [
            'success' => true,
            'id' => $lineId,
            'lines' => $this->extractElitLines($decoded),
            'error' => trim((string) ($decoded['Message'] ?? $decoded['message'] ?? '')),
            'status' => $status,
            'raw' => $decoded,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{id:string,code:string,qty:int}>
     */
    private function extractElitLines(array $payload): array
    {
        $selected = is_array($payload['SelectedCart'] ?? null) ? $payload['SelectedCart'] : [];
        $items = $selected['Items'] ?? $payload['Items'] ?? $payload['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return WebshopCartMirrorService::extractLines($payload);
        }

        return WebshopCartMirrorService::extractLines(['items' => $items]);
    }

    /**
     * @param array{id?:string} $added
     * @param list<array{id:string,code:string,qty:int}> $before
     * @param list<array{id:string,code:string,qty:int}> $after
     * @param array{id:string,code:string} $resolved
     */
    private function resolveAddedLineId(array $added, array $before, array $after, array $resolved, string $lookup): string
    {
        $newId = trim((string) ($added['id'] ?? ''));
        if ($newId !== '') {
            return $newId;
        }
        $newId = $this->diffNewLineId($before, $after, $resolved['code'] !== '' ? $resolved['code'] : $lookup);
        if ($newId !== '') {
            return $newId;
        }
        if ($resolved['id'] === '') {
            return '';
        }
        foreach ($after as $line) {
            if ((string) ($line['code'] ?? '') === $resolved['id'] || (string) ($line['id'] ?? '') === $resolved['id']) {
                return (string) ($line['id'] ?? $resolved['id']);
            }
        }

        return '';
    }

    /**
     * @param list<array{id:string,code:string,qty:int}> $before
     * @param list<array{id:string,code:string,qty:int}> $after
     */
    private function diffNewLineId(array $before, array $after, string $code): string
    {
        $seen = [];
        foreach ($before as $line) {
            $seen[(string) ($line['id'] ?? '')] = true;
        }
        foreach ($after as $line) {
            $id = trim((string) ($line['id'] ?? ''));
            if ($id !== '' && !isset($seen[$id])) {
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
