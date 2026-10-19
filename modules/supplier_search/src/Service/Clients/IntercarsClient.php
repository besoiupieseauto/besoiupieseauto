<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Clients;

use Besoiu\Core\Http\CurlMultiPool;
use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;

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
            SupplierSearchConfig::intercarsRememberSession(false);
            if ($this->triggerScraperRelogin()) {
                $creds = $this->loadCredentials();
                if ($creds !== null) {
                    $firstAttempt = $this->fetchProducts($query, $creds, $timeout, $connectTimeout);
                }
            }
        }

        if (!$firstAttempt['success']) {
            $error = $firstAttempt['error'];
            if (in_array($firstAttempt['status'], [401, 403], true)) {
                $error = 'InterCars sesiune expirată (HTTP ' . $firstAttempt['status'] . '). S-a deschis login e-Cat — reîncearcă după autentificare.';
            }

            return [
                'success' => false,
                'data' => ['products' => [], 'prices' => [], 'availability' => []],
                'error' => $error,
            ];
        }

        SupplierSearchConfig::intercarsRememberSession(true);

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
            'Accept-Language: ro',
            'Content-Type: application/json',
            'Origin: https://ro.e-cat.intercars.eu',
            'Referer: https://ro.e-cat.intercars.eu/',
            'x-api-key: ' . trim((string) ($rawHeaders['x-api-key'] ?? 'b2b-ecat-frontend')),
            'x-source: ' . trim((string) ($rawHeaders['x-source'] ?? 'listing-microfrontend')),
            'User-Agent: ' . trim((string) ($rawHeaders['User-Agent'] ?? $rawHeaders['user-agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36')),
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

    public function pingSession(int $timeout = 4): bool
    {
        $creds = $this->loadCredentials();
        if ($creds === null) {
            return false;
        }

        $attempt = $this->fetchProducts('test', $creds, $timeout, 2);

        return $attempt['success'];
    }

    private function triggerScraperRelogin(): bool
    {
        $beforeMtime = $this->credsMtime();
        $this->startHeadedLogin();

        $deadline = time() + 22;
        while (time() < $deadline) {
            clearstatcache(true);
            $creds = $this->loadCredentials();
            if ($creds !== null && $this->credsMtime() !== $beforeMtime) {
                $ping = $this->fetchProducts('test', $creds, 5, 2);
                if ($ping['success']) {
                    SupplierSearchConfig::intercarsRememberSession(true);
                    return true;
                }
            }
            usleep(800000);
        }

        $creds = $this->loadCredentials();
        if ($creds === null) {
            return false;
        }
        $ping = $this->fetchProducts('test', $creds, 5, 2);
        if ($ping['success']) {
            SupplierSearchConfig::intercarsRememberSession(true);
            return true;
        }

        return false;
    }

    private function startHeadedLogin(): void
    {
        if ($this->loginAlreadyRunning()) {
            return;
        }

        $python = $this->pythonBin();
        $helper = 'D:\\laragon\\www\\scraper\\intercars_login_helper.py';
        if (is_file($python) && is_file($helper)) {
            $cmd = 'start "" /B ' . escapeshellarg($python) . ' ' . escapeshellarg($helper);
            @pclose(@popen($cmd, 'r'));
            return;
        }

        $url = 'http://127.0.0.1/scraper/api.php?action=intercars_login';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function loginAlreadyRunning(): bool
    {
        $stateFile = 'D:\\laragon\\www\\scraper\\state\\intercars_login_state.json';
        if (!is_file($stateFile)) {
            return false;
        }
        $state = json_decode((string) file_get_contents($stateFile), true);
        if (!is_array($state)) {
            return false;
        }
        $status = (string) ($state['status'] ?? '');
        $ts = (int) ($state['ts'] ?? 0);
        if ($ts < time() - 90) {
            return false;
        }

        return in_array($status, ['starting', 'opening_browser', 'waiting_login', 'autofilling', 'harvesting_token'], true);
    }

    private function credsMtime(): int
    {
        $path = SupplierSearchConfig::intercarsCredsPath();

        return is_file($path) ? (int) filemtime($path) : 0;
    }

    private function pythonBin(): string
    {
        $candidates = [
            'C:\\Users\\Radu\\AppData\\Local\\Programs\\Python\\Python314\\python.exe',
            'C:\\Users\\Radu\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            'python.exe',
            'python',
        ];
        foreach ($candidates as $bin) {
            if ($bin !== 'python' && $bin !== 'python.exe' && is_file($bin)) {
                return $bin;
            }
        }

        return 'python';
    }

    /**
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    public function listCart(int $timeout = 12): array
    {
        $script = $this->runCartScript('list', '', '', 1, max(30, $timeout));

        return [
            'success' => (bool) ($script['success'] ?? false),
            'lines' => is_array($script['lines'] ?? null) ? $script['lines'] : [],
            'error' => (string) ($script['error'] ?? ''),
            'status' => !empty($script['success']) ? 200 : 0,
        ];
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

        $script = $this->runCartScript('add', $towKod, $towKod, $qty, max(60, $timeout));

        return [
            'success' => (bool) ($script['success'] ?? false) || trim((string) ($script['line_id'] ?? '')) !== '',
            'id' => (string) ($script['line_id'] ?? ''),
            'lines' => is_array($script['lines'] ?? null) ? $script['lines'] : [],
            'error' => (string) ($script['error'] ?? ''),
            'status' => !empty($script['success']) ? 200 : 0,
        ];
    }

    /**
     * Citește pagina de checkout fără a trimite comanda.
     *
     * @return array{success:bool,ready:bool,error:string,status:int}
     */
    public function previewCheckout(int $timeout = 12): array
    {
        $script = $this->runCartScript('preview', '', '', 1, max(45, $timeout));

        return [
            'success' => (bool) ($script['success'] ?? false),
            'ready' => (bool) ($script['success'] ?? false),
            'error' => (string) ($script['error'] ?? ''),
            'status' => !empty($script['success']) ? 200 : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rehearseCart(string $code, string $query, int $qty = 1, int $timeout = 90): array
    {
        return $this->runCartScript('rehearse', $code, $query !== '' ? $query : $code, max(1, $qty), $timeout);
    }

    /**
     * @return array<string, mixed>
     */
    private function runCartScript(string $mode, string $code, string $query, int $qty, int $timeout): array
    {
        $script = dirname(__DIR__, 5) . '/admin/scripts/intercars_cart_rehearse.py';
        if (!is_file($script)) {
            $script = (defined('BESOIU_ADMIN') ? (string) BESOIU_ADMIN : dirname(__DIR__, 5) . '/admin') . '/scripts/intercars_cart_rehearse.py';
        }
        if (!is_file($script)) {
            return ['success' => false, 'ok' => false, 'line_id' => '', 'lines' => [], 'steps' => [], 'error' => 'Lipsește intercars_cart_rehearse.py'];
        }

        $cmd = [
            $this->pythonBin(),
            $script,
            '--mode',
            $mode,
            '--code',
            $code,
            '--query',
            $query !== '' ? $query : $code,
            '--qty',
            (string) max(1, $qty),
        ];
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $spec, $pipes, dirname($script), ['IC_HEADED' => '1']);
        if (!is_resource($proc)) {
            return ['success' => false, 'ok' => false, 'line_id' => '', 'lines' => [], 'steps' => [], 'error' => 'Nu am putut porni simularea Intercars.'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + max(30, $timeout);
        while (microtime(true) < $deadline) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (empty($status['running'])) {
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                break;
            }
            usleep(200000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $running = proc_get_status($proc)['running'] ?? false;
        if ($running) {
            proc_terminate($proc);
        }
        proc_close($proc);

        $decoded = json_decode(trim($stdout), true);
        if (!is_array($decoded)) {
            $hint = trim($stderr);
            if ($hint === '') {
                $hint = trim($stdout);
            }

            return [
                'success' => false,
                'ok' => false,
                'line_id' => '',
                'lines' => [],
                'steps' => [],
                'error' => 'Simularea Intercars nu a întors JSON valid.' . ($hint !== '' ? ' ' . substr($hint, 0, 220) : ''),
            ];
        }
        $decoded['success'] = !empty($decoded['success']) || !empty($decoded['ok']);
        $decoded['submitted'] = false;

        return $decoded;
    }
}
