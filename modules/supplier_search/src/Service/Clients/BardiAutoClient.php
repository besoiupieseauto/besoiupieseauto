<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Clients;

use Besoiu\Core\Http\CurlMultiPool;
use Besoiu\Modules\SupplierSearch\Service\Parsers\BardiAutoParser;
use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;
use Besoiu\Modules\SupplierSearch\Service\WebshopCartMirrorService;

/**
 * Căutare live pe webshop-ul Bardi Auto (www.bardiauto.ro/webshop).
 * Folosește sesiunea B2B salvată local. Nu plasează comenzi.
 */
final class BardiAutoClient
{
    private const MAX_DETAILS = 6;
    private const SESSION_TTL = 28800;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * @return array{success:bool,data:array{products:array<int,array<string,mixed>>},error:string}
     */
    public function search(string $query, int $timeout = 12, int $connectTimeout = 4): array
    {
        $empty = ['products' => []];
        $query = trim($query);
        if (strlen($query) < 3) {
            return ['success' => true, 'data' => $empty, 'error' => ''];
        }

        $creds = SupplierSearchConfig::bardiCredentials();
        if ($creds['username'] === '' || $creds['password'] === '') {
            return [
                'success' => false,
                'data' => $empty,
                'error' => 'Credențiale Bardi Auto lipsă (BARDI_USERNAME / BARDI_PASSWORD).',
            ];
        }

        $cookieFile = $this->cookieFile();
        if (!$this->sessionIsFresh($cookieFile) && !$this->login($creds, $cookieFile, $timeout, $connectTimeout)) {
            return [
                'success' => false,
                'data' => $empty,
                'error' => $this->lastError !== '' ? $this->lastError : 'Autentificare Bardi Auto eșuată.',
            ];
        }

        $matches = $this->fetchMatches($query, $cookieFile, $timeout, $connectTimeout);
        if ($matches === null) {
            if ($this->login($creds, $cookieFile, $timeout, $connectTimeout)) {
                $matches = $this->fetchMatches($query, $cookieFile, $timeout, $connectTimeout);
            }
        }
        if ($matches === null) {
            return [
                'success' => false,
                'data' => $empty,
                'error' => $this->lastError !== '' ? $this->lastError : 'Căutare Bardi Auto eșuată.',
            ];
        }
        if ($matches === []) {
            return ['success' => true, 'data' => $empty, 'error' => ''];
        }

        $targets = $this->pickTargets($matches, $query);
        $products = $this->fetchDetails($targets, $cookieFile, $timeout, $connectTimeout);
        if ($products === null) {
            if ($this->login($creds, $cookieFile, $timeout, $connectTimeout)) {
                $products = $this->fetchDetails($targets, $cookieFile, $timeout, $connectTimeout);
            }
        }
        if ($products === null) {
            return [
                'success' => false,
                'data' => $empty,
                'error' => $this->lastError !== '' ? $this->lastError : 'Fișa de preț Bardi Auto nu a putut fi citită.',
            ];
        }

        return [
            'success' => true,
            'data' => ['products' => $products],
            'error' => '',
        ];
    }

    /** @param array{username:string,password:string} $creds */
    private function login(array $creds, string $cookieFile, int $timeout, int $connectTimeout): bool
    {
        $this->lastError = '';
        if (is_file($cookieFile)) {
            @unlink($cookieFile);
        }

        $base = SupplierSearchConfig::bardiBaseUrl();
        $home = $this->request($base . '/', $cookieFile, $timeout, $connectTimeout, true);
        if ($home['status'] < 200 || $home['status'] >= 400 || $home['error'] !== '') {
            $this->lastError = $home['error'] !== '' ? $home['error'] : 'Bardi Auto nu răspunde.';
            return false;
        }

        $login = $this->request($base . '/bejelentkezes', $cookieFile, $timeout, $connectTimeout, true, [
            'email' => $creds['username'],
            'jelszo' => $creds['password'],
            'kezdooldal' => $base . '/',
        ]);
        if ($login['error'] !== '') {
            $this->lastError = $login['error'];
            return false;
        }

        $check = $this->request($base . '/', $cookieFile, $timeout, $connectTimeout, false);
        $body = $check['body'];
        if (!str_contains($body, '/logout') && !str_contains($body, 'isLoggedIn = "1"') && !str_contains($body, 'isLoggedIn":"1"')) {
            $this->lastError = 'Autentificarea Bardi Auto a eșuat.';
            @unlink($cookieFile);
            return false;
        }

        @touch($cookieFile);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchMatches(string $query, string $cookieFile, int $timeout, int $connectTimeout): ?array
    {
        $base = SupplierSearchConfig::bardiBaseUrl();
        $response = $this->request(
            $base . '/general-search/search',
            $cookieFile,
            $timeout,
            $connectTimeout,
            false,
            [
                'string' => $query,
                'id' => (string) (int) (microtime(true) * 1000),
                'type' => 'perfect-matches',
            ],
            [
                'Accept: application/json, text/javascript, */*; q=0.01',
                'X-Requested-With: XMLHttpRequest',
                'Referer: ' . $base . '/cikkszam/kereso',
            ]
        );
        if ($response['error'] !== '' || $response['status'] < 200 || $response['status'] >= 300) {
            $this->lastError = $response['error'] !== '' ? $response['error'] : 'Bardi Auto HTTP ' . $response['status'];
            return null;
        }
        if ($this->looksLikeAuthWall($response['body'])) {
            $this->lastError = 'Sesiunea Bardi Auto a expirat.';
            return null;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            $this->lastError = 'Răspuns invalid de la căutarea Bardi Auto.';
            return null;
        }
        if (isset($decoded['recaptcha'])) {
            $this->lastError = 'Bardi Auto cere verificarea sesiunii.';
            return null;
        }

        $rows = $decoded['data']['result'] ?? $decoded['result'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function pickTargets(array $rows, string $query): array
    {
        $wanted = BardiAutoParser::normalizeCode($query);
        $exact = [];
        $rest = [];
        foreach ($rows as $row) {
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $pn = BardiAutoParser::normalizeCode((string) ($row['part_number'] ?? $row['cikk'] ?? ''));
            $type = strtolower((string) ($row['type'] ?? ''));
            if ($pn === $wanted || $type === 'parts') {
                $exact[] = $row;
            } elseif (!empty($row['important'])) {
                $rest[] = $row;
            }
        }

        $picked = [];
        $seen = [];
        foreach (array_merge($exact, $rest) as $row) {
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $picked[] = $row;
            if (count($picked) >= self::MAX_DETAILS) {
                break;
            }
        }

        return $picked;
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchDetails(array $targets, string $cookieFile, int $timeout, int $connectTimeout): ?array
    {
        if ($targets === []) {
            return [];
        }

        $base = SupplierSearchConfig::bardiBaseUrl();
        $handles = [];
        foreach ($targets as $index => $row) {
            $path = ltrim(trim((string) ($row['url'] ?? '')), '/');
            $url = $base . '/' . $path;
            $url .= str_contains($url, '?') ? '&api=yes' : '?api=yes';
            $ch = curl_init($url);
            SupplierSearchConfig::configureCurl($ch);
            curl_setopt_array($ch, [
                CURLOPT_HTTPGET => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_COOKIEFILE => $cookieFile,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json, text/plain, */*',
                    'X-Requested-With: XMLHttpRequest',
                    'gba-ws-token: no-token',
                    'Referer: ' . $base . '/',
                ],
            ]);
            $handles[$index] = $ch;
        }

        $pool = [];
        try {
            CurlMultiPool::run($handles, (float) $timeout + 2.0, 0.25, $pool);
        } finally {
            foreach ($handles as $ch) {
                curl_close($ch);
            }
        }

        $products = [];
        $authWall = false;
        foreach ($targets as $index => $row) {
            $res = $pool[$index] ?? ['body' => '', 'status' => 0];
            $status = (int) ($res['status'] ?? 0);
            $body = (string) ($res['body'] ?? '');
            if ($status < 200 || $status >= 300 || $body === '') {
                continue;
            }
            if ($this->looksLikeAuthWall($body)) {
                $authWall = true;
                continue;
            }
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (isset($decoded['recaptcha'])) {
                $authWall = true;
                continue;
            }
            $wanted = (string) ($row['part_number'] ?? $row['cikk'] ?? '');
            foreach ($this->extractProducts($decoded, $wanted) as $product) {
                $product['_match_brand'] = (string) ($row['product'] ?? '');
                $product['_match_name'] = $row['name'] ?? '';
                $product['_match_code'] = $wanted;
                $products[] = $product;
            }
        }

        if ($products === [] && $authWall) {
            $this->lastError = 'Sesiunea Bardi Auto a expirat la citirea prețului.';
            return null;
        }

        return $products;
    }

    /**
     * Fișa directă are `products`. Echivalențele au `items[].products`.
     * Păstrăm articolul cerut, nu toată lista de înlocuitori.
     *
     * @param array<string, mixed> $decoded
     * @return array<int, array<string, mixed>>
     */
    private function extractProducts(array $decoded, string $wantedPn): array
    {
        $flat = [];
        $lists = [];
        if (is_array($decoded['products'] ?? null)) {
            $lists[] = $decoded['products'];
        }
        $items = $decoded['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item) && is_array($item['products'] ?? null)) {
                    $lists[] = $item['products'];
                }
            }
        }

        foreach ($lists as $list) {
            foreach ($list as $product) {
                if (!is_array($product)) {
                    continue;
                }
                if (isset($product['pn']) || isset($product['brand'])) {
                    $flat[] = $product;
                    continue;
                }
                foreach ($product as $inner) {
                    if (is_array($inner) && (isset($inner['pn']) || isset($inner['brand']))) {
                        $flat[] = $inner;
                    }
                }
            }
        }

        $wanted = BardiAutoParser::normalizeCode($wantedPn);
        if ($wanted !== '') {
            $matched = [];
            foreach ($flat as $product) {
                $pn = BardiAutoParser::normalizeCode((string) ($product['pn'] ?? ''));
                if ($pn === $wanted) {
                    $matched[] = $product;
                }
            }
            if ($matched !== []) {
                return $matched;
            }
        }

        return $flat === [] ? [] : [reset($flat)];
    }

    private function sessionIsFresh(string $cookieFile): bool
    {
        return is_file($cookieFile) && filesize($cookieFile) > 32 && (time() - (int) filemtime($cookieFile)) < self::SESSION_TTL;
    }

    private function cookieFile(): string
    {
        return SupplierSearchConfig::cacheDir() . '/bardi_cookiejar.txt';
    }

    private function looksLikeAuthWall(string $body): bool
    {
        if (str_contains($body, '"recaptcha"')) {
            return true;
        }
        if (str_contains($body, 'id="login_form_on_hb"') && !str_contains($body, '/logout')) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, string>|null $post
     * @param list<string> $headers
     * @return array{body:string,status:int,error:string}
     */
    private function request(
        string $url,
        string $cookieFile,
        int $timeout,
        int $connectTimeout,
        bool $writeCookie,
        ?array $post = null,
        array $headers = []
    ): array {
        $ch = curl_init($url);
        SupplierSearchConfig::configureCurl($ch);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($writeCookie) {
            $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        }
        if ($post !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = trim((string) curl_error($ch));
        curl_close($ch);

        return [
            'body' => is_string($body) ? $body : '',
            'status' => $status,
            'error' => $error,
        ];
    }

    /**
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int,cart_id?:string}
     */
    public function listCart(int $timeout = 12): array
    {
        $response = $this->cartJson('/cart', null, $timeout);
        if (!$response['success']) {
            return [
                'success' => false,
                'lines' => [],
                'error' => $response['error'],
                'status' => $response['status'],
            ];
        }

        return [
            'success' => true,
            'lines' => $response['lines'],
            'error' => '',
            'status' => $response['status'],
            'cart_id' => $response['cart_id'],
        ];
    }

    /**
     * @return array{success:bool,id:string,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    public function addCartLine(string $partNumber, int $qty, int $timeout = 12): array
    {
        $partNumber = trim($partNumber);
        $qty = max(1, $qty);
        if ($partNumber === '') {
            return ['success' => false, 'id' => '', 'lines' => [], 'error' => 'Lipsește codul Bardi.', 'status' => 0];
        }

        $resolved = $this->resolveAddTarget($partNumber, $timeout);
        if ($resolved['id'] === '') {
            return [
                'success' => false,
                'id' => '',
                'lines' => [],
                'error' => $resolved['error'] !== '' ? $resolved['error'] : 'Nu s-a găsit productId Bardi pentru ' . $partNumber . '.',
                'status' => 0,
            ];
        }

        $before = $this->listCart($timeout);
        $added = $this->cartJson('/cart/add_item_to_cart', [
            'id' => $resolved['id'],
            'url' => $resolved['url'],
            'quantity' => (string) $qty,
            'sum_quantity' => '0',
            'part_number' => $resolved['id'],
            'added_by_key' => '0',
            'category' => '',
            'cart_id' => (string) ($before['cart_id'] ?? '0'),
            'car_data' => '0',
            'akodid' => '',
            'akod' => '',
            'bkod' => '',
        ], $timeout);
        $after = $this->listCart($timeout);
        $wanted = $resolved['pn'] !== '' ? $resolved['pn'] : $partNumber;
        $newId = $this->diffNewLineId($before['lines'] ?? [], $after['lines'] ?? [], $wanted);
        if ($newId === '') {
            $newId = $this->lineIdFromAddResponse($added, $resolved['id'], $wanted);
        }
        if ($newId === '') {
            foreach ($after['lines'] as $line) {
                if (strcasecmp((string) ($line['code'] ?? ''), $wanted) === 0 || strcasecmp((string) ($line['id'] ?? ''), $wanted) === 0) {
                    $newId = (string) ($line['id'] ?? $wanted);
                    break;
                }
            }
        }

        return [
            'success' => $newId !== '' && $added['success'],
            'id' => $newId,
            'lines' => $after['lines'] !== [] ? $after['lines'] : $added['lines'],
            'error' => $newId === '' ? (string) ($added['error'] !== '' ? $added['error'] : 'Nu s-a putut identifica linia Bardi.') : '',
            'status' => $added['status'],
        ];
    }

    /** @return array{success:bool,ready:bool,error:string,status:int} */
    public function previewCheckout(int $timeout = 12): array
    {
        $listed = $this->listCart($timeout);

        return [
            'success' => $listed['success'],
            'ready' => $listed['success'],
            'error' => $listed['success'] ? '' : $listed['error'],
            'status' => $listed['status'],
        ];
    }

    /**
     * @return array{id:string,url:string,pn:string,error:string}
     */
    private function resolveAddTarget(string $partNumber, int $timeout): array
    {
        if (strlen($partNumber) >= 20 && preg_match('/^[A-F0-9]+$/i', $partNumber) === 1) {
            return [
                'id' => $partNumber,
                'url' => '/webshop/cikkszam/kereso',
                'pn' => $partNumber,
                'error' => '',
            ];
        }

        $search = $this->search($partNumber, $timeout, 4);
        if (!$search['success']) {
            return ['id' => '', 'url' => '', 'pn' => $partNumber, 'error' => (string) ($search['error'] ?? 'Căutarea Bardi a eșuat.')];
        }

        $wanted = BardiAutoParser::normalizeCode($partNumber);
        foreach ($search['data']['products'] ?? [] as $product) {
            if (!is_array($product)) {
                continue;
            }
            $pn = BardiAutoParser::normalizeCode((string) ($product['pn'] ?? ''));
            $button = is_array($product['buttons']['addToCart'] ?? null) ? $product['buttons']['addToCart'] : [];
            $productId = trim((string) ($button['productId'] ?? $product['id'] ?? ''));
            $url = trim((string) ($button['currentUrl'] ?? ''));
            if ($productId === '') {
                continue;
            }
            if ($wanted === '' || $pn === $wanted || $pn === '') {
                return [
                    'id' => $productId,
                    'url' => $url !== '' ? $url : '/webshop/cikkszam/kereso',
                    'pn' => $pn !== '' ? $pn : $partNumber,
                    'error' => '',
                ];
            }
        }

        return ['id' => '', 'url' => '', 'pn' => $partNumber, 'error' => 'Produsul Bardi nu are buton de coș.'];
    }

    /**
     * @param array<string, string>|null $post
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int,cart_id:string,raw?:array<string,mixed>}
     */
    private function cartJson(string $path, ?array $post, int $timeout): array
    {
        $base = SupplierSearchConfig::bardiBaseUrl();
        $response = $this->request($base . $path, $this->cookieFile(), $timeout, 4, $post !== null, $post, [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . $base . '/kosar',
        ]);
        if ($response['error'] !== '' || $response['status'] < 200 || $response['status'] >= 300) {
            return [
                'success' => false,
                'lines' => [],
                'error' => $response['error'] !== '' ? $response['error'] : 'Bardi HTTP ' . $response['status'],
                'status' => $response['status'],
                'cart_id' => '',
            ];
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'lines' => [],
                'error' => 'Răspuns invalid de la Bardi.',
                'status' => $response['status'],
                'cart_id' => '',
            ];
        }

        $html = (string) ($decoded['content'] ?? '');
        $lines = $this->parseBardiCartHtml($html);
        if ($lines === []) {
            $lines = WebshopCartMirrorService::extractLines($decoded);
        }

        $header = is_array($decoded['cartHeaderInfo'] ?? null) ? $decoded['cartHeaderInfo'] : [];
        $headerCount = (int) ($header['items_count']['total'] ?? $header['items_count'] ?? 0);

        return [
            'success' => (int) ($decoded['result_code'] ?? 0) === 0,
            'lines' => $lines,
            'error' => trim((string) ($decoded['message'] ?? '')),
            'status' => $response['status'],
            'cart_id' => $this->extractBardiCartId($html, $decoded),
            'raw' => $decoded,
            'header_count' => $headerCount,
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractBardiCartId(string $html, array $decoded): string
    {
        if (preg_match('/sessionCartId\s*=\s*[\'"](\d+)[\'"]/', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/data-cart_id="(\d+)"/', $html, $m)) {
            return $m[1];
        }
        $header = is_array($decoded['cartHeaderInfo'] ?? null) ? $decoded['cartHeaderInfo'] : [];

        return trim((string) ($header['cart_id'] ?? $header['id'] ?? ''));
    }

    /**
     * @param array{raw?:array<string,mixed>} $added
     */
    private function lineIdFromAddResponse(array $added, string $productId, string $partNumber): string
    {
        $raw = is_array($added['raw'] ?? null) ? $added['raw'] : [];
        $html = (string) ($raw['content'] ?? '');
        foreach ($this->parseBardiCartHtml($html) as $line) {
            $id = trim((string) ($line['id'] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }
        if ($productId !== '') {
            return $productId;
        }

        return $partNumber;
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

    /**
     * @return list<array{id:string,code:string,qty:int}>
     */
    private function parseBardiCartHtml(string $html): array
    {
        $lines = [];
        if (preg_match_all('/<tr\b[^>]*class="[^"]*cart-content-row[^"]*"[^>]*>/i', $html, $rows)) {
            foreach ($rows[0] as $row) {
                if (!preg_match('/data-id="([^"]+)"/i', $row, $idMatch)) {
                    continue;
                }
                $id = trim($idMatch[1]);
                if ($id === '' || isset($lines[$id])) {
                    continue;
                }
                $qty = 1;
                if (preg_match('/data-quantity="(\d+)"/i', $row, $qtyMatch)) {
                    $qty = max(1, (int) $qtyMatch[1]);
                }
                $lines[$id] = ['id' => $id, 'code' => $id, 'qty' => $qty];
            }
        }
        if ($lines === [] && preg_match_all('/<tr\b[^>]*data-id="([^"]+)"[^>]*data-quantity="(\d+)"/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $row) {
                $id = trim((string) $row[1]);
                if ($id === '' || isset($lines[$id])) {
                    continue;
                }
                $lines[$id] = ['id' => $id, 'code' => $id, 'qty' => max(1, (int) $row[2])];
            }
        }

        return array_values($lines);
    }
}
