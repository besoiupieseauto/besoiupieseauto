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
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    public function listCart(int $timeout = 12): array
    {
        $cookieFile = $this->cookieFile();
        $response = $this->request(SupplierSearchConfig::bardiBaseUrl() . '/kosar', $cookieFile, $timeout, 4, false, null, [
            'Accept: application/json, text/html',
        ]);
        if ($response['error'] !== '' || $response['status'] < 200 || $response['status'] >= 300) {
            return [
                'success' => false,
                'lines' => [],
                'error' => $response['error'] !== '' ? $response['error'] : 'Bardi HTTP ' . $response['status'],
                'status' => $response['status'],
            ];
        }
        $decoded = json_decode($response['body'], true);

        return [
            'success' => true,
            'lines' => WebshopCartMirrorService::extractLines(is_array($decoded) ? $decoded : $this->parseBardiCartHtml($response['body'])),
            'error' => '',
            'status' => $response['status'],
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
        $cookieFile = $this->cookieFile();
        $before = $this->listCart($timeout);
        $added = $this->request(SupplierSearchConfig::bardiBaseUrl() . '/kosar/hozzaad', $cookieFile, $timeout, 4, true, [
            'cikk' => $partNumber,
            'mennyiseg' => (string) $qty,
        ]);
        $after = $this->listCart($timeout);
        $newId = '';
        $beforeIds = [];
        foreach ($before['lines'] ?? [] as $line) {
            $beforeIds[(string) ($line['id'] ?? '')] = true;
        }
        foreach ($after['lines'] ?? [] as $line) {
            $id = trim((string) ($line['id'] ?? ''));
            if ($id !== '' && !isset($beforeIds[$id])) {
                $newId = $id;
                break;
            }
        }

        return [
            'success' => $newId !== '' || ($added['status'] >= 200 && $added['status'] < 400 && $added['error'] === ''),
            'id' => $newId,
            'lines' => $after['lines'] ?? [],
            'error' => $newId === '' ? ($added['error'] !== '' ? $added['error'] : 'Nu s-a putut identifica linia Bardi.') : '',
            'status' => $added['status'],
        ];
    }

    /** @return array{success:bool,ready:bool,error:string,status:int} */
    public function previewCheckout(int $timeout = 12): array
    {
        $response = $this->request(SupplierSearchConfig::bardiBaseUrl() . '/kosar/rendeles', $this->cookieFile(), $timeout, 4, false);
        $ok = $response['error'] === '' && $response['status'] >= 200 && $response['status'] < 400;

        return [
            'success' => $ok,
            'ready' => $ok,
            'error' => $ok ? '' : ($response['error'] !== '' ? $response['error'] : 'Bardi HTTP ' . $response['status']),
            'status' => $response['status'],
        ];
    }

    /** @return array<string, mixed> */
    private function parseBardiCartHtml(string $html): array
    {
        $lines = [];
        if (preg_match_all('/data-cart-id="([^"]+)"[^>]*data-cikk="([^"]+)"[^>]*data-qty="(\d+)"/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $row) {
                $lines[] = ['id' => $row[1], 'code' => $row[2], 'qty' => (int) $row[3]];
            }
        }

        return ['items' => $lines];
    }
}
