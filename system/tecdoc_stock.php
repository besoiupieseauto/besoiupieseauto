<?php
declare(strict_types=1);

require_once __DIR__ . '/note-html.php';
require_once __DIR__ . '/search_logs.php';
require_once __DIR__ . '/tecdoc_vin.php';
require_once __DIR__ . '/product-code-normalize.php';
require_once __DIR__ . '/products_oem.php';
require_once __DIR__ . '/storefront-context.php';

const BESOiu_TECDOC_KEY = '';
const BESOiu_TECDOC_HOST = 'auto-parts-catalog.p.rapidapi.com';

if (!function_exists('tecdoc_catalog_lang_id')) {
    function tecdoc_catalog_lang_id(): int
    {
        return 21;
    }
}

if (!function_exists('tecdoc_catalog_country_id')) {
    function tecdoc_catalog_country_id(): int
    {
        return 63;
    }
}

function tecdoc_load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $envPath = tecdoc_admin_root() . '/.env';
    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    $loaded = true;
}

function tecdoc_rapidapi_key(): string
{
    tecdoc_load_env();
    foreach (['RAPIDAPI_AUTOPARTS_KEY', 'RAPIDAPI_TECDOC_KEY'] as $envKey) {
        $key = trim((string)($_ENV[$envKey] ?? getenv($envKey) ?: ''));
        if ($key !== '') {
            return $key;
        }
    }

    return BESOiu_TECDOC_KEY;
}

function tecdoc_rapidapi_is_user_key(): bool
{
    tecdoc_load_env();
    foreach (['RAPIDAPI_AUTOPARTS_KEY', 'RAPIDAPI_TECDOC_KEY'] as $envKey) {
        if (trim((string)($_ENV[$envKey] ?? getenv($envKey) ?: '')) !== '') {
            return true;
        }
    }

    return false;
}

function tecdoc_set_api_error(string $message, array $context = []): void
{
    $entry = [
        'message' => $message,
        'context' => $context,
        'at' => date('c'),
    ];
    $GLOBALS['TECDOC_LAST_API_ERROR'] = $entry;

    $logDir = tecdoc_admin_root() . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    file_put_contents(
        $logDir . '/rapidapi.log',
        date('[Y-m-d H:i:s] ') . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );
    error_log('[RapidAPI TecDoc] ' . $message);
    if (is_file(__DIR__ . '/system_errors.php')) {
        require_once __DIR__ . '/system_errors.php';
        besoiu_system_error_log('error', 'rapidapi', $message, $context);
    }
}

function tecdoc_last_api_error(): ?array
{
    $error = $GLOBALS['TECDOC_LAST_API_ERROR'] ?? null;
    return is_array($error) ? $error : null;
}

function tecdoc_clear_api_error(): void
{
    unset($GLOBALS['TECDOC_LAST_API_ERROR']);
}

function tecdoc_is_rate_limited(string $message, int $httpCode = 0): bool
{
    $message = mb_strtolower($message, 'UTF-8');

    return $httpCode === 429
        || str_contains($message, 'too many requests')
        || str_contains($message, 'rate limit');
}

/** Cotă lunară / abonament — oprește apelurile live până la reset. */
function tecdoc_is_hard_quota_exceeded(string $message, int $httpCode = 0): bool
{
    $message = mb_strtolower($message, 'UTF-8');

    return str_contains($message, 'monthly')
        || str_contains($message, 'subscription')
        || (str_contains($message, 'quota') && str_contains($message, 'exceeded'))
        || ($httpCode === 403 && str_contains($message, 'quota'));
}

function tecdoc_is_quota_exceeded(string $message, int $httpCode = 0): bool
{
    return tecdoc_is_hard_quota_exceeded($message, $httpCode)
        || tecdoc_is_rate_limited($message, $httpCode);
}

function tecdoc_cache_ttl_for_url(string $url): int
{
    if (preg_match('#/(models/list|types/type-id|products-groups-variant)#', $url) === 1) {
        return 86400 * 7;
    }
    if (preg_match('#/articles/list/#', $url) === 1) {
        return 86400 * 3;
    }
    if (preg_match('#/artlookup/#', $url) === 1) {
        return 86400 * 2;
    }
    if (preg_match('#/vin#i', $url) === 1) {
        return 86400 * 7;
    }

    return 86400;
}

function tecdoc_quota_flag_path(): string
{
    return dirname(__DIR__) . '/cache_tecdoc/.rapidapi_quota_exceeded';
}

function tecdoc_mark_api_unavailable(string $message): void
{
    $dir = dirname(tecdoc_quota_flag_path());
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(tecdoc_quota_flag_path(), json_encode([
        'message' => $message,
        'at' => date('c'),
    ], JSON_UNESCAPED_UNICODE));
}

function tecdoc_maybe_clear_stale_quota_flag(int $maxAgeSeconds = 3600): bool
{
    $flag = tecdoc_quota_flag_path();
    if (!is_file($flag)) {
        return false;
    }

    $raw = file_get_contents($flag);
    $data = json_decode(is_string($raw) ? $raw : '', true);
    if (!is_array($data)) {
        @unlink($flag);

        return true;
    }

    $at = strtotime((string) ($data['at'] ?? ''));
    if ($at > 0 && (time() - $at) > $maxAgeSeconds) {
        @unlink($flag);

        return true;
    }

    return false;
}

function tecdoc_api_unavailable_message(): string
{
    if (!tecdoc_api_is_unavailable()) {
        return '';
    }

    if (!besoiu_admin_storefront_context()) {
        return besoiu_storefront_quota_notice();
    }

    $flag = tecdoc_quota_flag_path();
    if (is_file($flag)) {
        $data = json_decode((string) file_get_contents($flag), true);
        $at = is_array($data) ? strtotime((string) ($data['at'] ?? '')) : 0;
        if ($at > 0) {
            $ago = max(0, time() - $at);

            return 'RapidAPI TecDoc blocat local (cotă depășită acum '
                . (int) floor($ago / 60) . ' min). Verifică planul RapidAPI sau așteaptă resetul (max 24h).';
        }
    }

    return tecdoc_quota_user_message();
}

function tecdoc_api_is_unavailable(): bool
{
    $flag = tecdoc_quota_flag_path();
    if (!is_file($flag)) {
        return false;
    }

    $raw = file_get_contents($flag);
    $data = json_decode(is_string($raw) ? $raw : '', true);
    if (!is_array($data)) {
        return true;
    }

    $at = strtotime((string)($data['at'] ?? ''));
    if ($at > 0 && (time() - $at) > 86400) {
        @unlink($flag);
        return false;
    }

    return true;
}

function tecdoc_quota_user_message(): string
{
    return besoiu_storefront_quota_notice();
}

function tecdoc_ip_probe_cache_path(): string
{
    return dirname(__DIR__) . '/cache_tecdoc/.dashboard_ip_probe.json';
}

function tecdoc_server_public_ip(): string
{
    static $cachedIp = null;
    if (is_string($cachedIp) && $cachedIp !== '') {
        return $cachedIp;
    }

    $curl = curl_init('https://api.ipify.org?format=json');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $body = curl_exec($curl);
    curl_close($curl);

    $decoded = is_string($body) ? json_decode($body, true) : null;
    $ip = is_array($decoded) ? trim((string) ($decoded['ip'] ?? '')) : '';
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
        $cachedIp = $ip;
        return $ip;
    }

    return 'necunoscut';
}

function tecdoc_is_ip_access_denied(string $message, int $httpCode = 0): bool
{
    $message = mb_strtolower($message, 'UTF-8');
    if ($message === '') {
        return $httpCode === 403;
    }

    if (str_contains($message, 'not subscribed')
        || str_contains($message, 'subscribed to this api')
        || str_contains($message, 'quota')
        || str_contains($message, 'exceeded')
        || str_contains($message, 'too many requests')
        || str_contains($message, 'rate limit')
    ) {
        return false;
    }

    foreach (['ip address', 'whitelist', 'not allowed', 'blocked', 'forbidden', 'access denied'] as $needle) {
        if (str_contains($message, $needle)) {
            return true;
        }
    }

    return $httpCode === 403;
}

/** @return array{ip_valid:bool,api_ok:bool,server_ip:string,http_code:int,message:string,operator_message:string,checked_at:string,error_detail:string} */
function tecdoc_probe_ip_status(bool $forceRefresh = false): array
{
    $cachePath = tecdoc_ip_probe_cache_path();
    if (!$forceRefresh && is_file($cachePath)) {
        $cached = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($cached) && (int) ($cached['expires'] ?? 0) > time() && is_array($cached['data'] ?? null)) {
            return $cached['data'];
        }
    }

    $serverIp = tecdoc_server_public_ip();
    $url = 'https://' . BESOiu_TECDOC_HOST
        . '/models/list/type-id/1/manufacturer-id/16/lang-id/21/country-filter-id/63';
    $response = tecdoc_http_get($url);
    $httpCode = (int) ($response['http_code'] ?? 0);
    $body = (string) ($response['body'] ?? '');
    $decoded = json_decode($body, true);
    $apiMessage = tecdoc_extract_api_error_message($body, $httpCode);
    $curlError = trim((string) ($response['error'] ?? ''));
    $apiOk = $httpCode === 200
        && is_array($decoded)
        && tecdoc_response_is_valid($decoded)
        && (isset($decoded['models']) || tecdoc_array_is_list_compat($decoded));

    if ($apiOk) {
        $ipValid = true;
        $message = 'IP TecDoc valid — API răspunde corect.';
        $operatorMessage = '';
    } elseif ($curlError !== '' || $httpCode === 0) {
        $ipValid = false;
        $message = 'Conexiune TecDoc eșuată de pe IP-ul serverului.';
        $operatorMessage = 'Schimbă IP-ul pentru ca sistemul să funcționeze';
    } elseif (tecdoc_is_quota_exceeded($apiMessage, $httpCode)
        || str_contains(mb_strtolower($apiMessage, 'UTF-8'), 'not subscribed')
    ) {
        $ipValid = true;
        $message = 'IP valid — API atins, dar răspunsul indică cotă sau abonament.';
        $operatorMessage = '';
    } elseif (tecdoc_is_ip_access_denied($apiMessage, $httpCode)) {
        $ipValid = false;
        $message = 'IP-ul serverului nu are acces la API TecDoc.';
        $operatorMessage = 'Schimbă IP-ul pentru ca sistemul să funcționeze';
    } else {
        $ipValid = $httpCode >= 200 && $httpCode < 500;
        $message = $ipValid
            ? 'IP valid — serverul poate contacta API TecDoc.'
            : 'API TecDoc indisponibil de pe IP-ul curent.';
        $operatorMessage = $ipValid ? '' : 'Schimbă IP-ul pentru ca sistemul să funcționeze';
    }

    $data = [
        'ip_valid' => $ipValid,
        'api_ok' => $apiOk,
        'server_ip' => $serverIp,
        'http_code' => $httpCode,
        'message' => $message,
        'operator_message' => $operatorMessage,
        'checked_at' => date('Y-m-d H:i:s'),
        'error_detail' => $apiOk ? '' : ($curlError !== '' ? $curlError : $apiMessage),
    ];

    $dir = dirname($cachePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($cachePath, json_encode([
        'expires' => time() + 60,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE));

    return $data;
}

function tecdoc_read_stale_cache(string $cacheFile, int $maxAgeSeconds = 2592000): ?string
{
    if (!is_file($cacheFile)) {
        return null;
    }
    if ((time() - filemtime($cacheFile)) > $maxAgeSeconds) {
        return null;
    }

    $body = file_get_contents($cacheFile);
    return is_string($body) && $body !== '' ? $body : null;
}

function tecdoc_extract_api_error_message($response, int $httpCode = 0): string
{
    $body = is_string($response) ? $response : '';
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        foreach (['message', 'error', 'detail', 'title'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                return trim($decoded[$key]);
            }
        }
    }

    if ($httpCode === 401 || $httpCode === 403) {
        return 'Cheie RapidAPI invalida sau fara acces la auto-parts-catalog.';
    }
    if ($httpCode === 429) {
        return 'Limita RapidAPI depasita (429).';
    }
    if ($httpCode >= 400) {
        return 'Eroare RapidAPI HTTP ' . $httpCode;
    }

    return $body !== '' ? mb_substr($body, 0, 180) : 'Raspuns RapidAPI invalid';
}

function tecdoc_admin_root(): string
{
    return dirname(__DIR__) . '/admin';
}

function tecdoc_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $adminRoot = tecdoc_admin_root();
    $envPath = $adminRoot . '/.env';
    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    $config = require $adminRoot . '/config/config.php';
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function tecdoc_clean_text($value): string
{
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim(strip_tags($value));
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function tecdoc_normalize_code(string $code): string
{
    return besoiu_normalize_product_code($code);
}

function tecdoc_produse_has_column(PDO $pdo, string $column, bool $forceRefresh = false): bool
{
    static $cache = [];
    if ($forceRefresh) {
        unset($cache[$column]);
    }
    if (isset($cache[$column])) {
        return $cache[$column];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'produse\' AND COLUMN_NAME = ?'
    );
    $stmt->execute([$column]);
    $cache[$column] = (int) $stmt->fetchColumn() > 0;

    return $cache[$column];
}

/** Asigură coloana pVitrina (migrare 044) — necesară pentru vitrina homepage. */
function tecdoc_ensure_vitrina_column(PDO $pdo): bool
{
    if (tecdoc_produse_has_column($pdo, 'pVitrina')) {
        return true;
    }
    try {
        $pdo->exec(
            "ALTER TABLE produse
             ADD COLUMN pVitrina TINYINT(1) NOT NULL DEFAULT 0
             COMMENT 'Afisare vitrina homepage' AFTER pBadge"
        );
        tecdoc_produse_has_column($pdo, 'pVitrina', true);
        return true;
    } catch (Throwable $e) {
        error_log('[tecdoc] ensure pVitrina: ' . $e->getMessage());
        return false;
    }
}

/** Asigură coloana pSpecial — produse speciale homepage (ulei, lichide etc.). */
function tecdoc_ensure_special_column(PDO $pdo): bool
{
    if (tecdoc_produse_has_column($pdo, 'pSpecial')) {
        return true;
    }
    try {
        $after = tecdoc_produse_has_column($pdo, 'pVitrina') ? 'pVitrina' : 'pBadge';
        $pdo->exec(
            "ALTER TABLE produse
             ADD COLUMN pSpecial TINYINT(1) NOT NULL DEFAULT 0
             COMMENT 'Afisare produse speciale homepage' AFTER `{$after}`"
        );
        tecdoc_produse_has_column($pdo, 'pSpecial', true);
        return true;
    } catch (Throwable $e) {
        error_log('[tecdoc] ensure pSpecial: ' . $e->getMessage());
        return false;
    }
}

function tecdoc_sql_normalized_pcode_expr(): string
{
    return besoiu_sql_normalized_pcode_expr('pCode');
}

/** @param array<int, string> $parts @param array<int, mixed> $params */
function tecdoc_append_oem_code_norm_conditions(PDO $pdo, array &$parts, array &$params, string $norm, string $likeNorm): void
{
    if ($norm === '') {
        return;
    }

    if (tecdoc_produse_has_column($pdo, 'pCodeNorm')) {
        $parts[] = 'pCodeNorm LIKE ?';
        $parts[] = 'pCodeNorm = ?';
    } else {
        $expr = tecdoc_sql_normalized_pcode_expr();
        $parts[] = $expr . ' LIKE ?';
        $parts[] = $expr . ' = ?';
    }

    array_push($params, $likeNorm, $norm);
}

require_once __DIR__ . '/tecdoc_api_cache.php';

function tecdoc_cached_response(string $url, int $ttl = 0): string
{
    if ($ttl <= 0) {
        $ttl = tecdoc_cache_ttl_for_url($url);
    }

    $dbCached = tecdoc_db_cache_get($url, $ttl);
    if ($dbCached !== null) {
        if (!tecdoc_cache_body_is_error($dbCached)) {
            tecdoc_clear_api_error();
        }
        return $dbCached;
    }

    $cacheDir = dirname(__DIR__) . '/cache_tecdoc/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    $cacheFile = $cacheDir . md5($url) . '.json';
    if (is_file($cacheFile) && time() - filemtime($cacheFile) < $ttl) {
        $cached = (string) file_get_contents($cacheFile);
        if (!tecdoc_cache_body_is_error($cached)) {
            tecdoc_db_cache_set($url, $cached, $ttl);
            tecdoc_clear_api_error();
            return $cached;
        }
        @unlink($cacheFile);
    }

    if (tecdoc_api_is_unavailable()) {
        $stale = tecdoc_read_stale_cache($cacheFile);
        if ($stale !== null && !tecdoc_cache_body_is_error($stale)) {
            return $stale;
        }
        tecdoc_set_api_error(tecdoc_quota_user_message(), ['url' => $url, 'cached_only' => true]);
        return json_encode(['message' => tecdoc_quota_user_message(), 'error' => 'quota_exceeded'], JSON_UNESCAPED_UNICODE);
    }

    $response = tecdoc_http_get($url);
    $httpCode = (int)($response['http_code'] ?? 0);
    $body = (string)($response['body'] ?? '[]');
    if (!empty($response['error'])) {
        tecdoc_set_api_error('cURL: ' . (string)$response['error'], ['url' => $url]);
        $stale = tecdoc_read_stale_cache($cacheFile);
        if ($stale !== null && !tecdoc_cache_body_is_error($stale)) {
            return $stale;
        }
        return json_encode(['error' => (string)$response['error']], JSON_UNESCAPED_UNICODE);
    }

    $decoded = json_decode($body, true);
    $apiMessage = tecdoc_extract_api_error_message($body, $httpCode);
    $hardQuota = tecdoc_is_hard_quota_exceeded($apiMessage, $httpCode);
    $rateLimited = tecdoc_is_rate_limited($apiMessage, $httpCode);

    if ($rateLimited && !$hardQuota) {
        usleep(1500000);
        $response = tecdoc_http_get($url);
        $httpCode = (int)($response['http_code'] ?? 0);
        $body = (string)($response['body'] ?? '[]');
        $decoded = json_decode($body, true);
        $apiMessage = tecdoc_extract_api_error_message($body, $httpCode);
        $hardQuota = tecdoc_is_hard_quota_exceeded($apiMessage, $httpCode);
        $rateLimited = tecdoc_is_rate_limited($apiMessage, $httpCode);
    }

    if ($httpCode >= 400 || (is_array($decoded) && isset($decoded['error']) && $decoded['error'] !== '')) {
        $message = $apiMessage;
        if ($httpCode === 403 && !tecdoc_rapidapi_is_user_key()) {
            $message = 'Cheie RapidAPI lipsă. Adaugă RAPIDAPI_AUTOPARTS_KEY în admin/.env și abonează-te la auto-parts-catalog.';
        } elseif ($httpCode === 403) {
            $message = str_contains(mb_strtolower($apiMessage, 'UTF-8'), 'not subscribed')
                ? 'Contul RapidAPI nu este abonat la auto-parts-catalog. Deschide https://rapidapi.com/makingdatameaningful/api/auto-parts-catalog și apasă Subscribe.'
                : 'Contul RapidAPI nu are acces la auto-parts-catalog. Verifică abonamentul pe rapidapi.com.';
        } elseif ($hardQuota) {
            $message = tecdoc_quota_user_message();
            tecdoc_mark_api_unavailable($message);
        } elseif ($rateLimited) {
            $message = 'Limită temporară RapidAPI. Se folosește cache local sau stocul din magazin.';
        }
        tecdoc_set_api_error(
            $message,
            ['url' => $url, 'http_code' => $httpCode, 'body' => mb_substr($body, 0, 500)]
        );

        $stale = tecdoc_read_stale_cache($cacheFile);
        if ($stale !== null && !tecdoc_cache_body_is_error($stale)) {
            return $stale;
        }

        return json_encode(['message' => $message, 'error' => 'quota_exceeded'], JSON_UNESCAPED_UNICODE);
    }
    if (is_array($decoded) && isset($decoded['error']) && $decoded['error'] === 'rate_limit_exceeded') {
        $stale = tecdoc_read_stale_cache($cacheFile);
        if ($stale !== null && !tecdoc_cache_body_is_error($stale)) {
            return $stale;
        }
        return $body;
    }
    if (is_array($decoded) && isset($decoded['message']) && tecdoc_is_hard_quota_exceeded((string)$decoded['message'], $httpCode)) {
        tecdoc_mark_api_unavailable((string)$decoded['message']);
        $stale = tecdoc_read_stale_cache($cacheFile);
        if ($stale !== null && !tecdoc_cache_body_is_error($stale)) {
            return $stale;
        }
        return json_encode(['message' => tecdoc_quota_user_message(), 'error' => 'quota_exceeded'], JSON_UNESCAPED_UNICODE);
    }
    if (is_array($decoded) && tecdoc_response_is_valid($decoded)) {
        file_put_contents($cacheFile, $body);
        tecdoc_db_cache_set($url, $body, $ttl);
        tecdoc_clear_api_error();
    }

    return $body;
}

function tecdoc_http_get(string $url): array
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'x-rapidapi-host: ' . BESOiu_TECDOC_HOST,
            'x-rapidapi-key: ' . tecdoc_rapidapi_key(),
        ],
    ]);
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode >= 200 && $httpCode < 400 && $error === '') {
        $budgetLib = dirname(__DIR__) . '/admin/system/api_token_budget.php';
        if (is_file($budgetLib)) {
            require_once $budgetLib;
            api_token_budget_log('rapidapi_tecdoc', 1, 'tecdoc_http_get', (string) (parse_url($url, PHP_URL_PATH) ?: 'request'));
        }
    }

    return [
        'body' => is_string($body) ? $body : '[]',
        'http_code' => $httpCode,
        'error' => $error !== '' ? $error : null,
    ];
}

function tecdoc_response_is_valid(array $decoded): bool
{
    if (isset($decoded['error']) && $decoded['error'] !== '') {
        return false;
    }
    if (isset($decoded['message']) && is_string($decoded['message'])) {
        $message = strtolower($decoded['message']);
        if (str_contains($message, 'does not exist') || str_contains($message, 'not found')) {
            return false;
        }
        if (str_contains($message, 'not subscribed') || str_contains($message, 'subscribed to this api')) {
            return false;
        }
        if (str_contains($message, 'too many requests') || str_contains($message, 'quota') || str_contains($message, 'exceeded')) {
            return false;
        }
    }

    return true;
}

function tecdoc_cache_body_is_error(string $body): bool
{
    $body = trim($body);
    if ($body === '') {
        return true;
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return false;
    }
    if (isset($decoded['error']) && $decoded['error'] !== '') {
        return true;
    }
    if (isset($decoded['message']) && is_string($decoded['message'])) {
        return tecdoc_is_quota_exceeded($decoded['message'])
            || str_contains(mb_strtolower($decoded['message'], 'UTF-8'), 'not subscribed');
    }

    return !tecdoc_response_is_valid($decoded);
}

function tecdoc_array_is_list_compat(array $data): bool
{
    return $data === [] || array_keys($data) === range(0, count($data) - 1);
}

function tecdoc_items(array $data): array
{
    if (isset($data['articles']) && is_array($data['articles'])) {
        return $data['articles'];
    }
    if (tecdoc_array_is_list_compat($data)) {
        return $data;
    }
    foreach (['data', 'items', 'results'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            return $data[$key];
        }
    }

    if (
        isset($data['articleId'])
        || isset($data['articleNo'])
        || isset($data['urlImage'])
        || isset($data['articleSearchNo'])
    ) {
        return [$data];
    }

    return [];
}

function tecdoc_search_by_article_type(string $query, string $articleType): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $host = BESOiu_TECDOC_HOST;
    $encoded = rawurlencode($query);
    $langId = tecdoc_catalog_lang_id();
    $url = "https://$host/artlookup/search-for-cross-numbers/lang-id/$langId/article-type/$articleType/article-no/$encoded";
    $decoded = json_decode(tecdoc_cached_response($url), true);
    if (!is_array($decoded) || !tecdoc_response_is_valid($decoded)) {
        return [];
    }

    $items = [];
    foreach (tecdoc_items($decoded) as $item) {
        if (is_array($item)) {
            $items[] = $item;
        }
    }

    return $items;
}

function tecdoc_merge_unique_articles(array ...$lists): array
{
    $items = [];
    $seen = [];
    foreach ($lists as $list) {
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $articleId = (string)($item['articleId'] ?? $item['articleNo'] ?? $item['articleNumber'] ?? md5(json_encode($item)));
            if (isset($seen[$articleId])) {
                continue;
            }
            $seen[$articleId] = true;
            $items[] = $item;
        }
    }

    return $items;
}

function tecdoc_find_article_with_oem_priority(array $searchCodes, string $brand = ''): ?array
{
    $searchCodes = array_values(array_unique(array_filter(array_map('trim', $searchCodes))));
    if ($searchCodes === []) {
        return null;
    }

    $primaryCode = $searchCodes[0];
    $oemHits = [];
    foreach ($searchCodes as $searchCode) {
        $oemHits = array_merge($oemHits, tecdoc_search_by_article_type($searchCode, 'OENumber'));
    }

    if ($oemHits !== []) {
        return tecdoc_pick_best_article($oemHits, $primaryCode, $brand);
    }

    $items = [];
    foreach ($searchCodes as $searchCode) {
        foreach (['IAMNumber', 'ArticleNumber', 'TradeNumber', 'EAN'] as $articleType) {
            $batch = tecdoc_search_by_article_type($searchCode, $articleType);
            if ($batch === []) {
                continue;
            }
            $items = array_merge($items, $batch);
        }
        if ($items !== []) {
            break;
        }
    }

    $items = tecdoc_merge_unique_articles($items);
    if ($items === []) {
        foreach (array_filter([trim($brand . ' ' . $primaryCode), $primaryCode]) as $query) {
            foreach (tecdoc_search_candidates($query) as $item) {
                $items[] = $item;
            }
            if ($items !== []) {
                break;
            }
        }
    }

    return tecdoc_pick_best_article($items, $primaryCode, $brand);
}

function tecdoc_search_candidates(string $query, int $maxItems = 12): array
{
    $query = trim($query);
    if ($query === '' || tecdoc_api_is_unavailable()) {
        return [];
    }

    $host = BESOiu_TECDOC_HOST;
    $encoded = rawurlencode($query);
    $articleTypes = ['OENumber', 'ArticleNumber', 'IAMNumber', 'TradeNumber', 'EAN'];

    $items = [];
    $seen = [];
    foreach ($articleTypes as $articleType) {
        if (tecdoc_api_should_stop()) {
            break;
        }

        $langId = tecdoc_catalog_lang_id();
    $url = "https://$host/artlookup/search-for-cross-numbers/lang-id/$langId/article-type/$articleType/article-no/$encoded";
        $decoded = json_decode(tecdoc_cached_response($url), true);
        if (!is_array($decoded) || !tecdoc_response_is_valid($decoded)) {
            continue;
        }
        foreach (tecdoc_items($decoded) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $articleId = (string)($item['articleId'] ?? $item['articleNo'] ?? $item['articleNumber'] ?? md5(json_encode($item)));
            if (isset($seen[$articleId])) {
                continue;
            }
            $seen[$articleId] = true;
            $items[] = $item;
            if (count($items) >= $maxItems) {
                return $items;
            }
        }
    }

    return $items;
}

function tecdoc_article_name(array $article): string
{
    return tecdoc_clean_text(
        $article['articleProductName']
        ?? $article['articleName']
        ?? $article['genericArticleDescription']
        ?? $article['productName']
        ?? ''
    );
}

function tecdoc_article_specs(array $article): string
{
    $parts = [];
    foreach (['articleCriteria', 'criteria', 'attributes', 'articleAttributes'] as $key) {
        if (!isset($article[$key]) || !is_array($article[$key])) {
            continue;
        }
        foreach ($article[$key] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string)($item['criteriaDescription'] ?? $item['criteriaName'] ?? $item['name'] ?? ''));
            $value = trim((string)($item['formattedValue'] ?? $item['rawValue'] ?? $item['value'] ?? ''));
            if ($label !== '' && $value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }
    }

    return implode(' | ', array_slice($parts, 0, 8));
}

function tecdoc_brand_matches(string $expectedBrand, string $actualBrand): bool
{
    $expected = strtoupper(preg_replace('/[^A-Z0-9]/', '', $expectedBrand) ?? '');
    $actual = strtoupper(preg_replace('/[^A-Z0-9]/', '', $actualBrand) ?? '');
    if ($expected === '' || $actual === '') {
        return false;
    }

    return $expected === $actual
        || str_contains($actual, $expected)
        || str_contains($expected, $actual);
}

function tecdoc_pick_best_article(array $articles, string $code, string $brand = ''): ?array
{
    if ($articles === []) {
        return null;
    }

    $normCode = tecdoc_normalize_code($code);
    $best = null;
    $bestScore = -1;

    foreach ($articles as $article) {
        if (!is_array($article)) {
            continue;
        }

        $score = 0;
        $articleCode = tecdoc_normalize_code(tecdoc_article_number($article));
        $articleBrand = tecdoc_article_brand($article);

        if ($normCode !== '' && $articleCode === $normCode) {
            $score += 20;
        } elseif ($normCode !== '' && ($articleCode !== '' && (str_contains($articleCode, $normCode) || str_contains($normCode, $articleCode)))) {
            $score += 8;
        }

        if ($brand !== '' && tecdoc_brand_matches($brand, $articleBrand)) {
            $score += 25;
        }

        if (tecdoc_article_name($article) !== '') {
            $score += 3;
        }
        if (tecdoc_article_image($article) !== '') {
            $score += 2;
        }
        if (tecdoc_article_specs($article) !== '') {
            $score += 1;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $article;
        }
    }

    return $best ?? $articles[0];
}

function tecdoc_find_article_for_import(string $code, string $brand = ''): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    $queries = array_values(array_unique(array_filter([
        $code,
        trim($brand . ' ' . $code),
    ])));

    $items = [];
    foreach ($queries as $query) {
        foreach (tecdoc_search_candidates($query) as $item) {
            $items[] = $item;
        }
        if ($items !== []) {
            break;
        }
    }

    return tecdoc_pick_best_article($items, $code, $brand);
}

function tecdoc_article_number(array $article): string
{
    return (string)(
        $article['articleNumber']
        ?? $article['articleNo']
        ?? $article['articleSearchNo']
        ?? $article['number']
        ?? ''
    );
}

function tecdoc_article_brand(array $article): string
{
    return tecdoc_clean_text($article['brandName'] ?? $article['supplierName'] ?? $article['brand'] ?? '');
}

function tecdoc_article_oem_codes(array $article): array
{
    $codes = [];
    $articleNumber = trim(tecdoc_article_number($article));
    if ($articleNumber !== '') {
        $codes[] = $articleNumber;
    }

    foreach (['oemNumbers', 'oem', 'oeNumbers', 'references'] as $key) {
        if (!isset($article[$key])) {
            continue;
        }
        $value = $article[$key];
        if (is_string($value)) {
            foreach (preg_split('/[\s,|]+/', $value) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $codes[] = $part;
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item)) {
                    $codes[] = $item;
                } elseif (is_array($item)) {
                    $brand = trim((string)($item['brandName'] ?? $item['brand'] ?? $item['mfrName'] ?? ''));
                    $number = trim((string)($item['oemNumber'] ?? $item['number'] ?? $item['articleNumber'] ?? ''));
                    if ($number !== '') {
                        $codes[] = $brand !== '' ? ($brand . ' : ' . $number) : $number;
                    }
                }
            }
        }
    }

    $unique = [];
    foreach ($codes as $code) {
        $code = trim((string)$code);
        if ($code === '') {
            continue;
        }
        $key = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? $code);
        if ($key === '' || isset($unique[$key])) {
            continue;
        }
        $unique[$key] = $code;
    }

    return array_values($unique);
}

function tecdoc_ensure_supplier_price_logic_loaded(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $adminRoot = tecdoc_admin_root();
    $autoload = $adminRoot . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    $configPath = $adminRoot . '/config/config.php';
    if (is_file($configPath) && class_exists('Config\\Database', false)) {
        static $dbBootstrapped = false;
        if (!$dbBootstrapped) {
            $config = require $configPath;
            Config\Database::getInstance(
                $config['db_host'],
                $config['db_name'],
                $config['db_user'],
                $config['db_pass']
            );
            $dbBootstrapped = true;
        }
    }

    $lib = $adminRoot . '/src/Controllers/Produse/import_supplier_lib.php';
    if (is_file($lib)) {
        require_once $lib;
    }

    $loaded = true;
}

function tecdoc_supplier_priority_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    tecdoc_ensure_supplier_price_logic_loaded();
    if (function_exists('import_supplier_priority_map')) {
        $map = import_supplier_priority_map();
        if ($map !== []) {
            return $map;
        }
    }

    $map = [
        'AUTOTOTAL' => 1,
        'AUTONET' => 2,
        'MATEROM' => 3,
        'AUTOPARTNER' => 4,
        'ELIT' => 5,
        'INTERCARS' => 6,
    ];

    return $map;
}

function tecdoc_supplier_priority_rank(string $supplier): int
{
    $priorityMap = tecdoc_supplier_priority_map();
    if (function_exists('import_supplier_priority_rank')) {
        return import_supplier_priority_rank($supplier, $priorityMap);
    }

    $key = strtoupper(trim(str_replace([' ', '-'], '', $supplier)));
    return (int) ($priorityMap[$key] ?? 99);
}

function tecdoc_product_supplier_stock_status(array $product): string
{
    $stock = $product['stock'] ?? $product['pStock'] ?? '';
    if (!is_scalar($stock)) {
        return 'unknown';
    }

    tecdoc_ensure_supplier_price_logic_loaded();
    if (function_exists('import_parse_supplier_stock')) {
        $parsed = import_parse_supplier_stock((string) $stock);
        if ($parsed === null) {
            return 'unknown';
        }

        return $parsed > 0 ? 'positive' : 'zero';
    }

    $normalized = preg_replace('/[^0-9.,-]/', '', str_replace(',', '.', (string) $stock));
    if ($normalized === '' || !is_numeric($normalized)) {
        return 'unknown';
    }

    return (float) $normalized > 0 ? 'positive' : 'zero';
}

function tecdoc_product_passes_supplier_overlap_rules(array $product): bool
{
    $supplier = strtoupper(trim(str_replace([' ', '-'], '', (string) ($product['supplier'] ?? $product['pSupplier'] ?? ''))));
    if ($supplier === '') {
        return true;
    }

    tecdoc_ensure_supplier_price_logic_loaded();
    $service = function_exists('import_price_logic_service') ? import_price_logic_service() : null;
    if ($service === null) {
        return true;
    }

    try {
        if ($service->isSupplierOmitted($supplier)) {
            return false;
        }

        $config = $service->getConfig();
        $stockStatus = tecdoc_product_supplier_stock_status($product);

        return $service->passesStockStatus($stockStatus, (string) ($config['stock_verify'] ?? 'skip_zero'));
    } catch (Throwable $exception) {
        return true;
    }
}

function tecdoc_supplier_overlap_group_key(array $product): string
{
    $code = tecdoc_normalize_code((string) ($product['code'] ?? ''));
    if ($code === '') {
        return 'id:' . (string) ($product['randomn_id'] ?? $product['id'] ?? '');
    }

    tecdoc_ensure_supplier_price_logic_loaded();
    if (function_exists('import_normalize_supplier_brand')) {
        $brandNorm = str_replace(' ', '', import_normalize_supplier_brand((string) ($product['brand'] ?? $product['pBrand'] ?? '')));

        return $code . '|' . $brandNorm;
    }

    $brandNorm = strtoupper(preg_replace('/\s+/', '', (string) ($product['brand'] ?? $product['pBrand'] ?? '')) ?? '');

    return $code . '|' . $brandNorm;
}

/** @return array{price:float,supplier:string,brand:string} */
function tecdoc_supplier_price_entry(array $product): array
{
    return [
        'price' => (float) ($product['price_numeric'] ?? 0),
        'supplier' => (string) ($product['supplier'] ?? $product['pSupplier'] ?? ''),
        'brand' => (string) ($product['brand'] ?? $product['pBrand'] ?? ''),
    ];
}

function tecdoc_should_prefer_supplier_product(array $candidate, array $current): bool
{
    tecdoc_ensure_supplier_price_logic_loaded();
    if (function_exists('import_price_index_should_replace')) {
        return import_price_index_should_replace(
            tecdoc_supplier_price_entry($current),
            (float) ($candidate['price_numeric'] ?? 0),
            (string) ($candidate['supplier'] ?? $candidate['pSupplier'] ?? ''),
            tecdoc_supplier_priority_map(),
            (string) ($candidate['brand'] ?? $candidate['pBrand'] ?? '')
        );
    }

    $candidatePrice = (float) ($candidate['price_numeric'] ?? 0);
    $currentPrice = (float) ($current['price_numeric'] ?? 0);

    if ($candidatePrice + 0.0001 < $currentPrice) {
        return true;
    }

    if (abs($candidatePrice - $currentPrice) <= 0.0001) {
        return tecdoc_supplier_priority_rank((string) ($candidate['supplier'] ?? ''))
            < tecdoc_supplier_priority_rank((string) ($current['supplier'] ?? ''));
    }

    return false;
}

/** @param array<int, array<string, mixed>> $products */
function tecdoc_deduplicate_products_by_supplier_price(array $products): array
{
    if ($products === []) {
        return $products;
    }

    $winners = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }

        if (!tecdoc_product_passes_supplier_overlap_rules($product)) {
            continue;
        }

        $groupKey = tecdoc_supplier_overlap_group_key($product);

        if (!isset($winners[$groupKey])) {
            $winners[$groupKey] = $product;
            continue;
        }

        if (tecdoc_should_prefer_supplier_product($product, $winners[$groupKey])) {
            $winners[$groupKey] = $product;
        }
    }

    return array_values($winners);
}

function tecdoc_row_price_numeric(array $row): float
{
    if (isset($row['price_numeric']) && is_numeric($row['price_numeric'])) {
        return (float) $row['price_numeric'];
    }

    $priceRaw = (string) ($row['pPrice'] ?? $row['price'] ?? '');
    $priceNormalized = preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $priceRaw));

    return is_numeric($priceNormalized) ? (float) $priceNormalized : 0.0;
}

/** @return array<string, mixed> */
function tecdoc_catalog_row_as_supplier_product(array $row, int $sourceIndex): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'randomn_id' => (string) ($row['randomn_id'] ?? $row['id'] ?? ''),
        'code' => (string) ($row['pCode'] ?? $row['code'] ?? ''),
        'brand' => (string) ($row['pBrand'] ?? $row['brand'] ?? ''),
        'supplier' => (string) ($row['pSupplier'] ?? $row['supplier'] ?? ''),
        'price_numeric' => tecdoc_row_price_numeric($row),
        'stock' => (string) ($row['pStock'] ?? $row['stock'] ?? $row['pShipping'] ?? ''),
        '__catalog_index' => $sourceIndex,
    ];
}

/** @param array<int, array<string, mixed>> $rows */
function tecdoc_deduplicate_catalog_rows_by_supplier_price(array $rows): array
{
    if ($rows === []) {
        return $rows;
    }

    $entries = [];
    foreach ($rows as $index => $row) {
        if (is_array($row)) {
            $entries[] = tecdoc_catalog_row_as_supplier_product($row, (int) $index);
        }
    }

    if ($entries === []) {
        return $rows;
    }

    $winners = tecdoc_deduplicate_products_by_supplier_price($entries);
    $deduped = [];
    foreach ($winners as $winner) {
        $idx = (int) ($winner['__catalog_index'] ?? -1);
        if ($idx >= 0 && isset($rows[$idx])) {
            $deduped[] = $rows[$idx];
        }
    }

    return $deduped;
}

/**
 * Același cod+brand la mai mulți furnizori → rândul cu prețul câștigător (regula admin).
 *
 * @param array<string, mixed> $row
 * @param array<int, array<string, mixed>> $candidates
 * @return array<string, mixed>
 */
function tecdoc_resolve_catalog_row_best_supplier_price(array $row, array $candidates): array
{
    if ($candidates === []) {
        return $row;
    }

    $all = [$row];
    $currentId = trim((string) ($row['randomn_id'] ?? $row['id'] ?? ''));
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $candidateId = trim((string) ($candidate['randomn_id'] ?? $candidate['id'] ?? ''));
        if ($candidateId !== '' && $candidateId === $currentId) {
            continue;
        }

        $all[] = $candidate;
    }

    if (count($all) <= 1) {
        return $row;
    }

    $winner = tecdoc_deduplicate_catalog_rows_by_supplier_price($all);

    return $winner[0] ?? $row;
}

/** @return array<int, array<string, mixed>> */
function tecdoc_find_catalog_rows_by_code_brand(PDO $pdo, string $code, string $brand, int $limit = 20): array
{
    $codeNorm = tecdoc_normalize_code($code);
    if ($codeNorm === '') {
        return [];
    }

    tecdoc_ensure_supplier_price_logic_loaded();
    if (function_exists('import_normalize_supplier_brand')) {
        $brandNorm = str_replace(' ', '', import_normalize_supplier_brand($brand));
    } else {
        $brandNorm = strtoupper(preg_replace('/\s+/', '', $brand) ?? '');
    }

    $limit = max(1, min(50, $limit));
    $expr = tecdoc_sql_normalized_pcode_expr();
    $hasCodeNorm = tecdoc_produse_has_column($pdo, 'pCodeNorm');
    $codeParts = [$expr . ' = ?'];
    $codeParams = [$codeNorm];
    if ($hasCodeNorm) {
        $codeParts[] = 'pCodeNorm = ?';
        $codeParams[] = $codeNorm;
    }
    $codeWhere = '(' . implode(' OR ', $codeParts) . ')';

    if ($brandNorm !== '') {
        $stmt = $pdo->prepare(
            "SELECT * FROM produse
             WHERE status <> '0'
               AND {$codeWhere}
               AND UPPER(REPLACE(REPLACE(TRIM(COALESCE(pBrand, '')), ' ', ''), '-', '')) = ?
             LIMIT {$limit}"
        );
        $stmt->execute(array_merge($codeParams, [$brandNorm]));
    } else {
        $stmt = $pdo->prepare(
            "SELECT * FROM produse
             WHERE status <> '0'
               AND {$codeWhere}
             LIMIT {$limit}"
        );
        $stmt->execute($codeParams);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @param array<string, mixed>|null $product @return array<string, mixed>|null */
function tecdoc_resolve_product_page_row(?array $product): ?array
{
    if ($product === null || !empty($product['_scraper'])) {
        return $product;
    }

    $code = trim((string) ($product['pCode'] ?? ''));
    $brand = trim((string) ($product['pBrand'] ?? ''));
    if ($code === '') {
        return $product;
    }

    try {
        $pdo = tecdoc_db();
        $siblings = tecdoc_find_catalog_rows_by_code_brand($pdo, $code, $brand);
        if (count($siblings) <= 1) {
            return $product;
        }

        return tecdoc_resolve_catalog_row_best_supplier_price($product, $siblings);
    } catch (Throwable $exception) {
        error_log('[tecdoc_resolve_product_page] ' . $exception->getMessage());

        return $product;
    }
}

function tecdoc_product_image(array $row): string
{
    $images = json_decode((string)($row['pImages'] ?? '[]'), true);
    return is_array($images) && !empty($images[0]) ? (string)$images[0] : 'assets/images/products/1.jpg';
}

function tecdoc_public_row(array $row, string $source, ?array $article = null): array
{
    $priceRaw = (string)($row['pPrice'] ?? '');
    $priceNormalized = preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $priceRaw));
    $priceNumeric = is_numeric($priceNormalized) ? (float) $priceNormalized : 0.0;

    return [
        'id' => (int)($row['id'] ?? 0),
        'randomn_id' => (string)($row['randomn_id'] ?? $row['id'] ?? ''),
        'source' => $source,
        'name' => tecdoc_clean_text($row['pName'] ?? 'Piesa auto'),
        'code' => tecdoc_clean_text($row['pCode'] ?? ''),
        'brand' => tecdoc_clean_text($row['pBrand'] ?? ''),
        'supplier' => tecdoc_clean_text($row['pSupplier'] ?? ''),
        'category' => tecdoc_clean_text($row['pCategory'] ?? ''),
        'price' => tecdoc_clean_text($row['pPrice'] ?? ''),
        'price_numeric' => $priceNumeric,
        'price_label' => $priceNumeric > 0 ? number_format($priceNumeric, 2, '.', '') . ' RON' : 'La cerere',
        'stock' => tecdoc_clean_text($row['pStock'] ?? ''),
        'image' => tecdoc_product_image($row),
        'note' => besoiu_note_is_html(trim((string)($row['pNote'] ?? '')))
            ? besoiu_note_sanitize_html(trim((string)($row['pNote'] ?? '')))
            : trim((string)($row['pNote'] ?? '')),
        'note_plain' => besoiu_note_plain_text(trim((string)($row['pNote'] ?? ''))),
        'badge' => trim((string)($row['pBadge'] ?? '')),
        'pBadge' => trim((string)($row['pBadge'] ?? '')),
        'tecdoc_article' => $article ? tecdoc_article_number($article) : '',
        'tecdoc_brand' => $article ? tecdoc_article_brand($article) : '',
        'tecdoc_specs' => $article ? tecdoc_article_specs($article) : '',
        'local_linked' => true,
        'rapidapi_code' => $article ? tecdoc_article_number($article) : '',
        'rapidapi_brand' => $article ? tecdoc_article_brand($article) : '',
    ];
}

function tecdoc_enrich_public_row(array $row, ?array $article = null): array
{
    $product = tecdoc_public_row($row, 'produse', $article);

    if ($article) {
        $tdName = tecdoc_article_name($article);
        if ($tdName !== '' && in_array($product['name'], ['', 'Piesa auto'], true)) {
            $product['name'] = $tdName;
        }
    }

    return $product;
}

/**
 * Corelare obligatorie RapidAPI → produs local (cod normalizat + brand).
 * Preia din BD: denumire, preț, imagine, descriere (pNote). Fără potrivire → null (nu se afișează).
 *
 * @param array<string, mixed> $article
 * @param array<string, mixed> $filters
 * @return array<string, mixed>|null
 */
function tecdoc_correlate_article_to_local_product(PDO $pdo, array $article, array $filters = []): ?array
{
    $rapidBrand = tecdoc_article_brand($article);
    $candidateCodes = [];
    $articleNumber = trim(tecdoc_article_number($article));

    if ($articleNumber !== '' && strcasecmp($articleNumber, 'N/A') !== 0) {
        $candidateCodes[] = $articleNumber;
    }

    foreach (tecdoc_article_oem_codes($article) as $oemCode) {
        $oemCode = trim((string) $oemCode);
        if ($oemCode === '') {
            continue;
        }

        if (str_contains($oemCode, ':')) {
            [$oemBrandPart, $oemNumberPart] = array_pad(explode(':', $oemCode, 2), 2, '');
            $oemNumberPart = trim($oemNumberPart);
            $oemBrandPart = trim($oemBrandPart);
            if ($oemNumberPart === '') {
                continue;
            }
            if ($rapidBrand !== '' && $oemBrandPart !== '' && !tecdoc_brand_matches($rapidBrand, $oemBrandPart)) {
                continue;
            }
            $candidateCodes[] = $oemNumberPart;
            continue;
        }

        $candidateCodes[] = $oemCode;
    }

    $seenNorm = [];
    $rowsById = [];

    foreach ($candidateCodes as $code) {
        $norm = tecdoc_normalize_code($code);
        if ($norm === '' || isset($seenNorm[$norm])) {
            continue;
        }
        $seenNorm[$norm] = true;

        foreach (tecdoc_find_catalog_rows_by_code_brand($pdo, $code, $rapidBrand) as $row) {
            if ($rapidBrand !== '' && !tecdoc_brand_matches($rapidBrand, (string) ($row['pBrand'] ?? ''))) {
                continue;
            }
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId > 0) {
                $rowsById[$rowId] = $row;
            }
        }
    }

    if ($rowsById === []) {
        return null;
    }

    $rows = array_values($rowsById);
    $winner = count($rows) === 1
        ? $rows[0]
        : tecdoc_resolve_catalog_row_best_supplier_price($rows[0], $rows);

    $product = tecdoc_enrich_public_row($winner, $article);
    if (!tecdoc_matches_filters($product, $filters)) {
        return null;
    }

    return $product;
}

function tecdoc_parse_vehicle_articles_response($data): array
{
    if (!is_array($data)) {
        return [];
    }
    if (isset($data['error']) && $data['error'] === 'rate_limit_exceeded') {
        return ['__rate_limit__' => true, 'payload' => $data];
    }
    if (isset($data['error']) && $data['error'] === 'quota_exceeded') {
        return ['__quota_exceeded__' => true, 'message' => (string)($data['message'] ?? tecdoc_quota_user_message())];
    }
    if (isset($data['message']) && is_string($data['message']) && tecdoc_is_hard_quota_exceeded($data['message'])) {
        tecdoc_mark_api_unavailable($data['message']);
        return ['__quota_exceeded__' => true, 'message' => tecdoc_quota_user_message()];
    }

    $items = [];
    if (isset($data['articles']) && is_array($data['articles'])) {
        $items = $data['articles'];
    } elseif (tecdoc_array_is_list_compat($data)) {
        $items = $data;
    } elseif (isset($data['data']) && is_array($data['data'])) {
        $items = $data['data'];
    }

    $articles = [];
    foreach ($items as $art) {
        if (!is_array($art)) {
            continue;
        }
        $img = $art['s3image'] ?? ($art['images'][0]['url'] ?? null);
        $articles[] = [
            'supplierName' => $art['supplierName'] ?? $art['brand'] ?? $art['brandName'] ?? '',
            'brandName' => $art['supplierName'] ?? $art['brand'] ?? $art['brandName'] ?? '',
            'articleProductName' => $art['articleProductName'] ?? $art['articleName'] ?? $art['genericArticleDescription'] ?? '',
            'articleName' => $art['articleProductName'] ?? $art['articleName'] ?? $art['genericArticleDescription'] ?? '',
            'articleNo' => $art['articleNo'] ?? $art['articleNumber'] ?? '',
            'articleNumber' => $art['articleNo'] ?? $art['articleNumber'] ?? '',
            's3image' => $img,
            'img' => $img,
            'urlImage' => $img,
            'images' => isset($art['images']) && is_array($art['images']) ? $art['images'] : [],
            'articleCriteria' => $art['articleCriteria'] ?? $art['criteria'] ?? [],
        ];
    }

    return $articles;
}

function tecdoc_fetch_vehicle_articles(int $carId, int $nodeId): array
{
    if ($carId <= 0 || $nodeId <= 0) {
        return [];
    }

    $host = BESOiu_TECDOC_HOST;
    $langId = tecdoc_catalog_lang_id();
    $url = "https://$host/articles/list/type-id/1/vehicle-id/$carId/category-id/$nodeId/lang-id/$langId";
    $response = tecdoc_cached_response($url);
    $data = json_decode($response, true);

    return tecdoc_parse_vehicle_articles_response($data);
}

function tecdoc_find_web_products_for_article(PDO $pdo, array $article, array $filters = []): array
{
    $product = tecdoc_correlate_article_to_local_product($pdo, $article, $filters);

    return $product !== null ? [$product] : [];
}

function tecdoc_local_stock_brands_cache_path(): string
{
    return dirname(__DIR__) . '/cache_tecdoc/.local_stock_brands.json';
}

/** @return array<int, string> */
function tecdoc_local_stock_brand_labels(PDO $pdo, bool $forceRefresh = false): array
{
    static $memory = null;
    if (!$forceRefresh && is_array($memory)) {
        return $memory;
    }

    $cachePath = tecdoc_local_stock_brands_cache_path();
    if (!$forceRefresh && is_file($cachePath)) {
        $cached = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($cached) && (int) ($cached['expires'] ?? 0) > time() && is_array($cached['brands'] ?? null)) {
            $memory = array_values(array_filter(array_map('strval', $cached['brands'])));

            return $memory;
        }
    }

    $stmt = $pdo->query(
        "SELECT TRIM(pBrand) AS label
         FROM produse
         WHERE status <> '0'
           AND TRIM(COALESCE(pBrand, '')) <> ''
         GROUP BY TRIM(pBrand)
         ORDER BY label ASC"
    );

    $brands = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label !== '') {
            $brands[] = $label;
        }
    }

    $dir = dirname($cachePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($cachePath, json_encode([
        'expires' => time() + 300,
        'brands' => $brands,
    ], JSON_UNESCAPED_UNICODE));

    $memory = $brands;

    return $brands;
}

function tecdoc_article_has_local_stock_brand(array $article, array $localBrands): bool
{
    if ($localBrands === []) {
        return true;
    }

    $articleBrand = tecdoc_article_brand($article);
    if ($articleBrand === '') {
        return true;
    }

    foreach ($localBrands as $localBrand) {
        if (tecdoc_brand_matches((string) $localBrand, $articleBrand)) {
            return true;
        }
    }

    return false;
}

/** @param array<int, array<string, mixed>> $articles @return array<int, array<string, mixed>> */
function tecdoc_filter_tecdoc_articles_to_local_brands(array $articles, PDO $pdo): array
{
    if ($articles === [] || isset($articles['__rate_limit__']) || isset($articles['__quota_exceeded__'])) {
        return $articles;
    }

    $localBrands = tecdoc_local_stock_brand_labels($pdo);
    if ($localBrands === []) {
        return $articles;
    }

    return array_values(array_filter($articles, static function (array $article) use ($localBrands): bool {
        return tecdoc_article_has_local_stock_brand($article, $localBrands);
    }));
}

/** @param array<int, array<string, mixed>> $products @return array<int, string> */
function tecdoc_collect_stock_brand_labels(array $products): array
{
    $labels = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $brand = trim((string) ($product['brand'] ?? $product['pBrand'] ?? ''));
        if ($brand !== '') {
            $labels[$brand] = $brand;
        }
    }

    return array_values($labels);
}

/** @param array<int, array<string, mixed>> $articles @return array<int, string> */
function tecdoc_collect_tecdoc_article_brand_labels(array $articles): array
{
    if ($articles === [] || isset($articles['__rate_limit__']) || isset($articles['__quota_exceeded__'])) {
        return [];
    }

    $labels = [];
    foreach ($articles as $article) {
        if (!is_array($article)) {
            continue;
        }
        $brand = tecdoc_article_brand($article);
        if ($brand !== '') {
            $labels[$brand] = $brand;
        }
    }

    return array_values($labels);
}

/** @param array<int, string> $apiBrands @return array<int, string> */
function tecdoc_filter_brand_labels_to_local_stock(array $apiBrands, PDO $pdo): array
{
    if ($apiBrands === []) {
        return [];
    }

    $localBrands = tecdoc_local_stock_brand_labels($pdo);
    if ($localBrands === []) {
        return [];
    }

    $matched = [];
    foreach ($apiBrands as $apiBrand) {
        foreach ($localBrands as $localBrand) {
            if (tecdoc_brand_matches((string) $localBrand, (string) $apiBrand)) {
                $matched[$localBrand] = $localBrand;
                break;
            }
        }
    }

    return array_values($matched);
}

function tecdoc_vehicle_articles_in_stock(int $carId, int $nodeId, array $filters = []): array
{
    $pdo = tecdoc_db();
    $hasBdFilters = trim((string)($filters['category'] ?? '')) !== ''
        || trim((string)($filters['subcategory'] ?? '')) !== ''
        || trim((string)($filters['oem'] ?? '')) !== ''
        || trim((string)($filters['name'] ?? '')) !== '';

    if ($hasBdFilters) {
        return tecdoc_bd_stock_search($filters, 80, $carId, $nodeId);
    }

    $rawArticles = tecdoc_fetch_vehicle_articles($carId, $nodeId);
    $scannedTecdoc = is_array($rawArticles) && !isset($rawArticles['__rate_limit__']) && !isset($rawArticles['__quota_exceeded__'])
        ? count($rawArticles)
        : 0;
    $apiBrands = tecdoc_collect_tecdoc_article_brand_labels($rawArticles);
    $articles = tecdoc_filter_tecdoc_articles_to_local_brands($rawArticles, $pdo);

    if (isset($articles['__rate_limit__']) || isset($articles['__quota_exceeded__'])) {
        $local = tecdoc_list_web_products($pdo, $filters, 48);
        return [
            'success' => true,
            'source' => 'bd',
            'fallback' => 'local',
            'notice' => (string)($articles['message'] ?? tecdoc_quota_user_message()),
            'count' => count($local),
            'scanned' => 0,
            'products' => $local,
        ];
    }

    $products = [];
    $seen = [];

    foreach ($articles as $article) {
        foreach (tecdoc_find_web_products_for_article($pdo, $article, $filters) as $product) {
            $key = (string)($product['randomn_id'] ?? $product['id'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $products[] = $product;
        }
    }

    if ($products === [] && (tecdoc_api_is_unavailable() || tecdoc_api_should_stop())) {
        $local = tecdoc_list_web_products($pdo, $filters, 48);
        if ($local !== []) {
            return [
                'success' => true,
                'source' => 'bd',
                'fallback' => 'local',
                'notice' => tecdoc_quota_user_message(),
                'count' => count($local),
                'scanned' => 0,
                'products' => $local,
            ];
        }
    }

    $products = tecdoc_deduplicate_products_by_supplier_price($products);
    $stockBrands = tecdoc_collect_stock_brand_labels($products);
    if ($stockBrands === [] && $apiBrands !== []) {
        $stockBrands = tecdoc_filter_brand_labels_to_local_stock($apiBrands, $pdo);
    }
    $notice = '';
    if ($scannedTecdoc > 0 && count($articles) === 0 && $products === []) {
        $notice = 'TecDoc a returnat piese, dar niciun brand nu există în stocul magazinului pentru această selecție.';
    }

    $payload = [
        'success' => true,
        'source' => 'tecdoc',
        'count' => count($products),
        'scanned' => count($articles),
        'scanned_tecdoc' => $scannedTecdoc,
        'products' => $products,
        'api_brands' => $apiBrands,
        'stock_brands' => $stockBrands,
    ];
    if ($notice !== '') {
        $payload['notice'] = $notice;
    }

    return $payload;
}

function tecdoc_matches_filters(array $product, array $filters): bool
{
    $text = mb_strtolower(implode(' ', [
        $product['name'] ?? '',
        $product['code'] ?? '',
        $product['brand'] ?? '',
        $product['supplier'] ?? '',
        $product['category'] ?? '',
    ]), 'UTF-8');

    foreach (['name', 'oem', 'vin', 'category'] as $key) {
        $needle = mb_strtolower(trim((string)($filters[$key] ?? '')), 'UTF-8');
        if ($needle !== '' && mb_strpos($text, $needle, 0, 'UTF-8') === false) {
            return false;
        }
    }
    return true;
}

function tecdoc_query_bd_products(PDO $pdo, array $filters, int $limit = 80): array
{
    $sql = "SELECT * FROM produse WHERE status <> '0'";
    $params = [];

    $category = trim((string)($filters['category'] ?? ''));
    if ($category !== '') {
        $sql .= ' AND TRIM(pCategory) = ?';
        $params[] = $category;
    }

    $subcategory = trim((string)($filters['subcategory'] ?? ''));
    if ($subcategory !== '') {
        $like = '%' . $subcategory . '%';
        $sql .= ' AND TRIM(COALESCE(pSubcategory, \'\')) LIKE ?';
        $params[] = $like;
    }

    $marca = trim((string)($filters['marca'] ?? ''));
    if ($marca !== '') {
        $like = '%' . $marca . '%';
        $sql .= ' AND (
            TRIM(COALESCE(pMarca, \'\')) LIKE ?
            OR TRIM(COALESCE(pMarca, \'\')) = \'\'
            OR pName LIKE ?
            OR pNote LIKE ?
        )';
        array_push($params, $like, $like, $like);
    }

    $oem = trim((string)($filters['oem'] ?? ''));
    if ($oem !== '') {
        $like = '%' . $oem . '%';
        $norm = products_oem_normalize($oem);
        $likeNorm = $norm !== '' ? '%' . $norm . '%' : $like;
        $oemParts = [
            'pCode LIKE ?',
            'pName LIKE ?',
            'pNote LIKE ?',
            'pOem LIKE ?',
        ];
        array_push($params, $like, $like, $like, $like);

        if ($norm !== '') {
            tecdoc_append_oem_code_norm_conditions($pdo, $oemParts, $params, $norm, $likeNorm);
        }

        if ($norm !== '' && products_oem_table_exists($pdo)) {
            $oemParts[] = 'id IN (
                SELECT product_id FROM products_oem
                WHERE oem_norm = ? OR oem_norm LIKE ? OR oem_code LIKE ?
            )';
            array_push($params, $norm, $likeNorm, $like);
        }

        $sql .= ' AND (' . implode(' OR ', $oemParts) . ')';
    }

    $name = trim((string)($filters['name'] ?? ''));
    if ($name !== '' && $oem === '') {
        $like = '%' . $name . '%';
        $sql .= ' AND (pName LIKE ? OR pSubcategory LIKE ? OR pNote LIKE ? OR pCode LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $products = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $products[] = tecdoc_enrich_public_row($row);
    }

    return $products;
}

function tecdoc_collect_tecdoc_codes_for_node(int $carId, int $nodeId): array
{
    $codes = [];
    if ($carId <= 0 || $nodeId <= 0 || tecdoc_api_is_unavailable()) {
        return $codes;
    }

    $articles = tecdoc_fetch_vehicle_articles($carId, $nodeId);
    if (isset($articles['__rate_limit__']) || isset($articles['__quota_exceeded__'])) {
        return $codes;
    }

    $articles = tecdoc_filter_tecdoc_articles_to_local_brands($articles, tecdoc_db());

    foreach ($articles as $article) {
        foreach (tecdoc_article_oem_codes($article) as $code) {
            $key = tecdoc_normalize_code($code);
            if ($key !== '') {
                $codes[$key] = true;
            }
        }
        $articleNo = tecdoc_normalize_code(tecdoc_article_number($article));
        if ($articleNo !== '') {
            $codes[$articleNo] = true;
        }
    }

    return $codes;
}

function tecdoc_filter_products_by_tecdoc_codes(array $products, array $tecdocCodes, string $oem = ''): array
{
    if ($tecdocCodes === []) {
        return $products;
    }

    $normOem = tecdoc_normalize_code($oem);

    return array_values(array_filter($products, static function (array $product) use ($tecdocCodes, $normOem): bool {
        $code = tecdoc_normalize_code((string)($product['code'] ?? ''));
        if ($code === '' || !isset($tecdocCodes[$code])) {
            return false;
        }
        if ($normOem !== '' && $code !== $normOem && !str_contains($code, $normOem)) {
            return false;
        }

        return true;
    }));
}

function tecdoc_bd_stock_search(array $filters, int $limit = 80, int $carId = 0, int $nodeId = 0): array
{
    $pdo = tecdoc_db();
    $products = tecdoc_query_bd_products($pdo, $filters, $limit);
    $bdCount = count($products);
    $subcategory = trim((string)($filters['subcategory'] ?? ''));
    $oem = trim((string)($filters['oem'] ?? ''));
    $notice = '';

    // Subcategorii din BD: nu intersectăm cu nod TecDoc (ex. „Biela” la „Cuzineti Biela”).
    if ($subcategory !== '') {
        $nodeId = 0;
    }

    if ($nodeId > 0 && $carId > 0) {
        $tecdocCodes = tecdoc_collect_tecdoc_codes_for_node($carId, $nodeId);
        if ($tecdocCodes !== []) {
            $filtered = tecdoc_filter_products_by_tecdoc_codes($products, $tecdocCodes, $oem);
            if ($filtered === [] && $bdCount > 0) {
                $products = tecdoc_query_bd_products($pdo, $filters, $limit);
                $notice = 'Afișăm produsele din stoc pentru „' . $subcategory . '”. Potrivirea automată TecDoc pentru vehiculul selectat nu a găsit coduri corespondente — verifică compatibilitatea pe fișa produsului.';
            } else {
                $products = $filtered;
            }
        }
    } elseif ($oem !== '') {
        $normOem = tecdoc_normalize_code($oem);
        $products = array_values(array_filter($products, static function (array $product) use ($normOem): bool {
            $code = tecdoc_normalize_code((string)($product['code'] ?? ''));
            return $code !== '' && ($code === $normOem || str_contains($code, $normOem));
        }));
    }

    if ($notice === '' && $subcategory !== '' && $products === []) {
        $notice = besoiu_admin_storefront_context()
            ? 'Nu există produse în stoc pentru subcategoria „' . $subcategory . '”. Verifică denumirea subcategoriei în admin (pSubcategory).'
            : 'Nu există produse în stoc pentru subcategoria „' . $subcategory . '”.';
    }

    $products = tecdoc_deduplicate_products_by_supplier_price($products);

    return [
        'success' => true,
        'source' => 'bd',
        'count' => count($products),
        'scanned' => 0,
        'subcategory' => $subcategory,
        'products' => $products,
        'stock_brands' => tecdoc_collect_stock_brand_labels($products),
        'notice' => besoiu_storefront_public_notice($notice),
    ];
}

function tecdoc_direct_local_search(PDO $pdo, array $filters, int $limit = 40): array
{
    $query = trim((string)($filters['oem'] ?? ''));
    if ($query === '') $query = trim((string)($filters['name'] ?? ''));
    if ($query === '') $query = trim((string)($filters['category'] ?? ''));
    if ($query === '') $query = trim((string)($filters['vin'] ?? ''));
    if ($query === '') return [];

    $like = '%' . $query . '%';
    $norm = products_oem_normalize($query);
    $likeNorm = $norm !== '' ? '%' . $norm . '%' : $like;
    $conditions = [
        'pName LIKE ?',
        'pCode LIKE ?',
        'pBrand LIKE ?',
        'pMarca LIKE ?',
        'pSupplier LIKE ?',
        'pCategory LIKE ?',
        'pSubcategory LIKE ?',
        'pNote LIKE ?',
        'pOem LIKE ?',
    ];
    $params = array_fill(0, count($conditions), $like);

    if ($norm !== '') {
        tecdoc_append_oem_code_norm_conditions($pdo, $conditions, $params, $norm, $likeNorm);
    }

    if ($norm !== '' && products_oem_table_exists($pdo)) {
        $conditions[] = 'id IN (
            SELECT product_id FROM products_oem
            WHERE oem_norm = ? OR oem_norm LIKE ? OR oem_code LIKE ?
        )';
        array_push($params, $norm, $likeNorm, $like);
    }

    $sql = '
        SELECT * FROM produse
        WHERE status <> \'0\'
          AND (' . implode(' OR ', $conditions) . ')
        ORDER BY id DESC
        LIMIT ' . max(1, min(100, $limit));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $products = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $product = tecdoc_enrich_public_row($row);
        if (tecdoc_matches_filters($product, $filters)) {
            $products[] = $product;
        }
    }

    return $products;
}

function tecdoc_list_web_products(PDO $pdo, array $filters = [], int $limit = 60): array
{
    $sql = "SELECT * FROM produse WHERE status <> '0' ORDER BY id DESC LIMIT " . max(1, min(200, $limit));
    $products = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $product = tecdoc_enrich_public_row($row);
        if (tecdoc_matches_filters($product, $filters)) {
            $products[] = $product;
        }
    }

    return tecdoc_deduplicate_products_by_supplier_price($products);
}

/** SQL: ulei, lichide, adesive, consumabile — nu piese mecanice de schimb. */
function tecdoc_consumable_product_match_sql(): string
{
    return "(
             LOWER(pName) LIKE '%ulei%'
             OR LOWER(pName) LIKE '%lichid%'
             OR LOWER(pName) LIKE '%lubrif%'
             OR LOWER(pName) LIKE '%antigel%'
             OR LOWER(pName) LIKE '%aditiv%'
             OR LOWER(pName) LIKE '%adesiv%'
             OR LOWER(pName) LIKE '%coolant%'
             OR LOWER(pName) LIKE '%vaselin%'
             OR LOWER(pSubcategory) LIKE '%ulei%'
             OR LOWER(pSubcategory) LIKE '%lichid%'
             OR LOWER(pSubcategory) LIKE '%adesiv%'
             OR LOWER(pCategory) LIKE '%ulei%'
             OR LOWER(pCategory) LIKE '%lichid%'
             OR LOWER(pCategory) LIKE '%adesiv%'
           )";
}

/** SQL: exclude piese de schimb (cuzineti, frane etc.) chiar dacă sunt în categorie greșită. */
function tecdoc_mechanical_part_exclude_sql(): string
{
    return "LOWER(pName) NOT LIKE '%cuzinet%'
             AND LOWER(pName) NOT LIKE '%biela%'
             AND LOWER(pName) NOT LIKE '%lagar%'
             AND LOWER(pName) NOT LIKE '%lagăr%'
             AND LOWER(pName) NOT LIKE '%disc fran%'
             AND LOWER(pName) NOT LIKE '%ambreiaj%'
             AND LOWER(pName) NOT LIKE '%piston%'
             AND LOWER(pName) NOT LIKE '%segment%'
             AND LOWER(pName) NOT LIKE '%filtru%'
             AND LOWER(pSubcategory) NOT LIKE '%cuzinet%'
             AND LOWER(pSubcategory) NOT LIKE '%biela%'
             AND LOWER(pSubcategory) NOT LIKE '%fran%'";
}

/**
 * Produse recomandate homepage: ulei, adesive, lichide — prioritizate pVitrina din admin.
 *
 * @return array<int, array<string, mixed>>
 */
function tecdoc_list_vitrina_products(PDO $pdo, int $limit = 8): array
{
    tecdoc_ensure_vitrina_column($pdo);
    $limit = max(1, min(10, $limit));
    $activeWhere = "COALESCE(NULLIF(TRIM(CAST(status AS CHAR)), ''), '0') NOT IN ('0', 'inactive', 'deleted')";
    $hasVitrina = tecdoc_produse_has_column($pdo, 'pVitrina');

    if ($hasVitrina) {
        $stmt = $pdo->prepare(
            "SELECT * FROM produse
             WHERE {$activeWhere}
               AND pVitrina = 1
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        $products = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $products[] = tecdoc_enrich_public_row($row);
        }
        if ($products !== []) {
            return tecdoc_deduplicate_products_by_supplier_price($products);
        }
    }

    $consumableMatch = tecdoc_consumable_product_match_sql();
    $excludeMechanical = tecdoc_mechanical_part_exclude_sql();
    $vitrinaOrder = $hasVitrina
        ? 'CASE WHEN pVitrina = 1 THEN 0 ELSE 1 END,'
        : '';

    $stmt = $pdo->prepare(
        "SELECT * FROM produse
         WHERE {$activeWhere}
           AND {$consumableMatch}
           AND {$excludeMechanical}
         ORDER BY
           {$vitrinaOrder}
           CASE WHEN LOWER(pName) LIKE '%ulei%' THEN 0
                WHEN LOWER(pName) LIKE '%adesiv%' THEN 1
                WHEN LOWER(pName) LIKE '%lichid%' THEN 2
                ELSE 3 END,
           id DESC
         LIMIT {$limit}"
    );
    $stmt->execute();
    $products = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $products[] = tecdoc_enrich_public_row($row);
    }

    return tecdoc_deduplicate_products_by_supplier_price($products);
}

/**
 * Produse speciale homepage: bifate pSpecial sau din categorii ulei/lichide/consumabile.
 *
 * @return array<int, array<string, mixed>>
 */
function tecdoc_list_special_products(PDO $pdo, int $limit = 8): array
{
    tecdoc_ensure_special_column($pdo);
    $limit = max(1, min(12, $limit));
    $activeWhere = "COALESCE(NULLIF(TRIM(CAST(status AS CHAR)), ''), '0') NOT IN ('0', 'inactive', 'deleted')";

    if (tecdoc_produse_has_column($pdo, 'pSpecial')) {
        $stmt = $pdo->prepare(
            "SELECT * FROM produse
             WHERE {$activeWhere}
               AND pSpecial = 1
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        $products = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $products[] = tecdoc_enrich_public_row($row);
        }
        if ($products !== []) {
            return tecdoc_deduplicate_products_by_supplier_price($products);
        }
    }

    $nameMatch = tecdoc_consumable_product_match_sql();
    $excludeMechanical = tecdoc_mechanical_part_exclude_sql();

    $stmt = $pdo->prepare(
        "SELECT * FROM produse
         WHERE {$activeWhere}
           AND {$nameMatch}
           AND {$excludeMechanical}
         ORDER BY
           CASE WHEN LOWER(pName) LIKE '%ulei%' THEN 0
                WHEN LOWER(pName) LIKE '%lichid%' THEN 1
                ELSE 2 END,
           id DESC
         LIMIT {$limit}"
    );
    $stmt->execute();
    $products = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $products[] = tecdoc_enrich_public_row($row);
    }
    if ($products !== []) {
        return tecdoc_deduplicate_products_by_supplier_price($products);
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM produse
         WHERE {$activeWhere}
           AND (
             LOWER(pCategory) LIKE '%ulei%'
             OR LOWER(pCategory) LIKE '%lichid%'
           )
           AND {$excludeMechanical}
         ORDER BY id DESC
         LIMIT {$limit}"
    );
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $products[] = tecdoc_enrich_public_row($row);
    }
    if ($products !== []) {
        return tecdoc_deduplicate_products_by_supplier_price($products);
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM produse
         WHERE {$activeWhere}
           AND (
             LOWER(pCategory) LIKE '%ulei%'
             OR LOWER(pCategory) LIKE '%lichid%'
             OR LOWER(pCategory) LIKE '%adesiv%'
             OR LOWER(pCategory) LIKE '%consumabil%'
           )
           AND {$excludeMechanical}
         ORDER BY id DESC
         LIMIT {$limit}"
    );
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $products[] = tecdoc_enrich_public_row($row);
    }

    return tecdoc_deduplicate_products_by_supplier_price($products);
}

/** @return array<string, mixed> */
function tecdoc_vitrina_products_payload(int $limit = 8): array
{
    $pdo = tecdoc_db();
    $products = tecdoc_list_vitrina_products($pdo, $limit);
    $source = 'vitrina';

    if ($products === []) {
        require_once __DIR__ . '/scraper-home.php';
        $products = besoiu_scraper_products_for_vitrina($limit);
        if ($products !== []) {
            $source = 'scraper_oils';
        }
    }

    require_once __DIR__ . '/home-vitrina-render.php';
    $products = array_map('besoiu_vitrina_enrich_public_product', $products);

    return [
        'success' => true,
        'source' => $source,
        'count' => count($products),
        'scanned' => count($products),
        'products' => $products,
    ];
}

function tecdoc_find_local_for_article(PDO $pdo, array $article, array $filters): array
{
    $products = [];
    foreach (tecdoc_find_stock_products_for_article($pdo, $article, $filters) as $product) {
        $products[] = $product;
    }

    return $products;
}

/**
 * Potrivire strictă articol TecDoc → produse locale (pCode, pCodeNorm, products_oem, brand+cod).
 *
 * @return array<int, array<string, mixed>>
 */
function tecdoc_find_stock_products_for_article(PDO $pdo, array $article, array $filters = []): array
{
    $product = tecdoc_correlate_article_to_local_product($pdo, $article, $filters);

    return $product !== null ? [$product] : [];
}

/**
 * Căutare OEM: interogare TecDoc → filtrare strictă stoc local.
 *
 * @param array<string, string> $filters
 * @return array<string, mixed>
 */
function tecdoc_search_oem_in_stock(string $code, array $filters = [], int $limit = 80): array
{
    $queryRaw = trim($code);
    $code = besoiu_normalize_product_code($queryRaw);
    if ($code === '' || mb_strlen($code) < 2) {
        return [
            'success' => false,
            'message' => 'Cod OEM invalid.',
            'query' => $queryRaw,
            'count' => 0,
            'scanned' => 0,
            'products' => [],
        ];
    }

    $pdo = tecdoc_db();
    $filters['oem'] = $code;
    $limit = max(1, min(120, $limit));

    $products = [];
    $seen = [];
    $scanned = 0;
    $source = 'tecdoc';
    $notice = null;

    if (!tecdoc_api_is_unavailable()) {
        $candidates = tecdoc_search_candidates($code);
        $scanned = count($candidates);
        $candidates = tecdoc_filter_tecdoc_articles_to_local_brands($candidates, $pdo);

        foreach ($candidates as $article) {
            if (!is_array($article)) {
                continue;
            }

            foreach (tecdoc_find_stock_products_for_article($pdo, $article, $filters) as $product) {
                $key = (string) ($product['randomn_id'] ?? $product['id'] ?? '');
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $products[] = $product;
                if (count($products) >= $limit) {
                    break 2;
                }
            }
        }
    } else {
        $source = 'bd';
        $notice = tecdoc_quota_user_message();
    }

    if ($products === []) {
        $source = 'bd';
        foreach (tecdoc_direct_local_search($pdo, $filters, $limit) as $product) {
            $key = (string) ($product['randomn_id'] ?? $product['id'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $products[] = $product;
            if (count($products) >= $limit) {
                break;
            }
        }

        if ($notice === null && $scanned > 0 && $products === []) {
            $localBrands = tecdoc_local_stock_brand_labels($pdo);
            $notice = $localBrands !== []
                ? 'Piesele TecDoc găsite nu există în stocul local sau brandul nu este disponibil în magazin.'
                : 'Piesele TecDoc găsite nu există în stocul local.';
        } elseif ($notice === null && $scanned === 0 && $products === [] && !tecdoc_api_is_unavailable()) {
            $notice = 'Nu am găsit piese TecDoc pentru codul introdus.';
        }
    }

    $products = tecdoc_deduplicate_products_by_supplier_price($products);

    $result = [
        'success' => true,
        'source' => $source,
        'query' => $code,
        'count' => count($products),
        'scanned' => $scanned,
        'products' => $products,
        'stock_brands' => tecdoc_collect_stock_brand_labels($products),
    ];

    if ($notice !== null && $notice !== '') {
        $result['notice'] = $notice;
    }

    if (function_exists('search_log_write')) {
        search_log_write(
            $pdo,
            'oem',
            $code,
            count($products) > 0,
            null,
            null,
            count($products),
            count($products) > 0 ? null : (string) ($result['notice'] ?? 'Fără rezultate'),
            search_log_scan_meta_from_context($filters, $result, $products)
        );
    }

    return $result;
}

function tecdoc_public_search(array $filters): array
{
    $vinRaw = trim((string) ($filters['vin'] ?? ''));
    if ($vinRaw !== '' && tecdoc_is_vin_query($vinRaw)) {
        return tecdoc_public_search_by_vin($filters);
    }

    return tecdoc_public_search_core($filters);
}

function tecdoc_public_search_core(array $filters): array
{
    $pdo = tecdoc_db();
    $carId = (int)($filters['car_id'] ?? 0);
    $nodeId = (int)($filters['node_id'] ?? 0);
    $oem = trim((string)($filters['oem'] ?? ''));

    $subcategory = trim((string)($filters['subcategory'] ?? ''));
    if ($subcategory !== '') {
        return tecdoc_bd_stock_search($filters, 80, $carId, 0);
    }

    if ($carId > 0 && $nodeId > 0 && $oem !== '') {
        return tecdoc_bd_stock_search($filters, 80, $carId, $nodeId);
    }

    $products = tecdoc_query_bd_products($pdo, $filters, 80);

    if ($products === []) {
        foreach (tecdoc_direct_local_search($pdo, $filters, 60) as $product) {
            $products[] = $product;
        }
    }

    $seen = [];
    $deduped = [];
    foreach ($products as $product) {
        $key = (string)($product['randomn_id'] ?? $product['id'] ?? '');
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $product;
    }
    $products = tecdoc_deduplicate_products_by_supplier_price($deduped);

    $query = trim((string)($filters['oem'] ?? ''));
    if ($query === '') {
        $query = trim((string)($filters['name'] ?? ''));
    }

    if ($products === [] && $query !== '' && !tecdoc_api_is_unavailable()) {
        foreach (tecdoc_search_candidates($query) as $article) {
            foreach (tecdoc_find_local_for_article($pdo, $article, $filters) as $product) {
                $key = (string)($product['randomn_id'] ?? $product['id'] ?? '');
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $products[] = $product;
            }
        }
    }

    $result = [
        'success' => true,
        'source' => 'bd',
        'count' => count($products),
        'scanned' => 0,
        'products' => $products,
        'stock_brands' => tecdoc_collect_stock_brand_labels($products),
    ];

    if (tecdoc_api_is_unavailable() && $products !== []) {
        $result['fallback'] = 'local';
        $result['notice'] = tecdoc_quota_user_message();
    }

    if ($query !== '' && function_exists('search_log_write')) {
        $oemQuery = trim((string) ($filters['oem'] ?? ''));
        $queryType = $oemQuery !== '' ? 'oem' : 'name';
        $carId = (int) ($filters['car_id'] ?? 0);
        $vehicleLabel = search_log_vehicle_from_filters($filters);
        $found = count($products) > 0;
        $meta = search_log_scan_meta_from_context($filters, $result, $products);

        search_log_write(
            $pdo,
            $queryType,
            $query,
            $found,
            $carId > 0 ? $carId : null,
            $vehicleLabel,
            count($products),
            $found ? null : (string) ($result['notice'] ?? 'Fără rezultate'),
            $meta
        );
    }

    return $result;
}

function tecdoc_article_image(array $article): string
{
    $image = $article['urlImage']
        ?? $article['s3image']
        ?? $article['img']
        ?? ($article['images'][0]['url'] ?? null)
        ?? '';

    return is_string($image) ? trim($image) : '';
}

function tecdoc_article_ttc_id(array $article): string
{
    foreach (['articleId', 'genericArticleId', 'articleNo', 'articleNumber'] as $key) {
        $id = trim((string)($article[$key] ?? ''));
        if ($id !== '') {
            return $id;
        }
    }

    return '';
}

function tecdoc_pick_article_with_image(array $articles, string $code, string $brand = ''): ?array
{
    $best = null;
    $bestScore = -1;
    foreach ($articles as $article) {
        if (!is_array($article) || tecdoc_article_image($article) === '') {
            continue;
        }

        $score = 10;
        if ($brand !== '' && tecdoc_brand_matches($brand, tecdoc_article_brand($article))) {
            $score += 20;
        }
        $articleCode = tecdoc_normalize_code(tecdoc_article_number($article));
        $normCode = tecdoc_normalize_code($code);
        if ($normCode !== '' && $articleCode === $normCode) {
            $score += 15;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $article;
        }
    }

    return $best;
}

function tecdoc_search_first_article_with_image(string $code, string $brand = ''): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    $queries = array_values(array_unique(array_filter([
        $code,
        trim($brand . ' ' . $code),
    ])));

    foreach ($queries as $query) {
        foreach (['IAMNumber', 'ArticleNumber', 'TradeNumber'] as $articleType) {
            $match = tecdoc_pick_article_with_image(
                tecdoc_search_by_article_type($query, $articleType),
                $code,
                $brand
            );
            if ($match !== null) {
                return $match;
            }
        }
    }

    $match = tecdoc_pick_article_with_image(
        tecdoc_search_by_article_type($code, 'OENumber'),
        $code,
        $brand
    );

    return $match;
}

function tecdoc_code_variants(string $code): array
{
    $code = trim($code);
    if ($code === '') {
        return [];
    }

    $variants = [$code];
    foreach (['mm', 'MM', 'std', 'STD', 'STD.', 'MM.'] as $suffix) {
        if (strlen($code) > strlen($suffix) && str_ends_with($code, $suffix)) {
            $variants[] = substr($code, 0, -strlen($suffix));
        }
    }

    $norm = tecdoc_normalize_code($code);
    if ($norm !== '' && !in_array($norm, $variants, true)) {
        $variants[] = $norm;
    }

    $unique = [];
    foreach ($variants as $variant) {
        $variant = trim((string)$variant);
        if ($variant === '') {
            continue;
        }
        $key = tecdoc_normalize_code($variant);
        if ($key === '' || isset($unique[$key])) {
            continue;
        }
        $unique[$key] = $variant;
    }

    return array_values($unique);
}

function tecdoc_api_should_stop(): bool
{
    if (tecdoc_api_is_unavailable()) {
        return true;
    }

    $err = tecdoc_last_api_error();
    if (!is_array($err)) {
        return false;
    }

    $message = (string)($err['message'] ?? '');
    $httpCode = (int)(($err['context']['http_code'] ?? 0));

    return tecdoc_is_hard_quota_exceeded($message, $httpCode);
}

function tecdoc_find_image_payload_from_search_codes(
    array $searchCodes,
    string $primaryCode,
    string $brand = '',
    array $articleTypes = ['OENumber', 'IAMNumber', 'ArticleNumber', 'TradeNumber'],
    int $maxCodes = 0
): array {
    $empty = [
        'url' => '',
        'article_id' => '',
        'article_brand' => '',
        'article' => null,
        'matched_query' => '',
    ];

    $searchCodes = array_values(array_unique(array_filter(array_map('trim', $searchCodes))));
    if ($searchCodes === []) {
        return $empty;
    }

    if ($maxCodes > 0) {
        $searchCodes = array_slice($searchCodes, 0, $maxCodes);
    }

    $queries = [];
    foreach ($searchCodes as $searchCode) {
        $queries[] = $searchCode;
        $brandQuery = trim($brand . ' ' . $searchCode);
        if ($brandQuery !== '' && $brandQuery !== $searchCode) {
            $queries[] = $brandQuery;
        }
    }
    $queries = array_values(array_unique(array_filter($queries)));

    foreach ($queries as $query) {
        foreach ($articleTypes as $articleType) {
            if (tecdoc_api_should_stop()) {
                return $empty;
            }

            $items = tecdoc_search_by_article_type($query, $articleType);
            if ($items === []) {
                continue;
            }

            $article = tecdoc_pick_article_with_image($items, $primaryCode, $brand);
            if ($article === null) {
                $article = tecdoc_pick_best_article($items, $primaryCode, $brand);
            }
            if ($article === null) {
                continue;
            }

            $url = tecdoc_article_image($article);
            $articleId = tecdoc_article_ttc_id($article);
            if ($url === '' && $articleId === '') {
                continue;
            }

            return [
                'url' => $url,
                'article_id' => $articleId,
                'article_brand' => tecdoc_article_brand($article),
                'article' => $article,
                'matched_query' => $query,
            ];
        }
    }

    return $empty;
}

function tecdoc_collect_image_search_codes(string $code, array $extraCodes = []): array
{
    $searchCodes = [];
    $seen = [];

    $add = static function (string $candidate) use (&$searchCodes, &$seen): void {
        foreach (tecdoc_code_variants($candidate) as $variant) {
            $variant = trim((string)$variant);
            if ($variant === '') {
                continue;
            }
            $key = tecdoc_normalize_code($variant);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $searchCodes[] = $variant;
        }
    };

    foreach ($extraCodes as $candidate) {
        $add((string) $candidate);
    }
    $add($code);

    return $searchCodes;
}

function tecdoc_find_image_payload_fast(string $code, string $brand = '', array $extraCodes = []): array
{
    $code = trim($code);
    $searchCodes = tecdoc_collect_image_search_codes($code, $extraCodes);
    if ($searchCodes === []) {
        return [
            'url' => '',
            'article_id' => '',
            'article_brand' => '',
            'article' => null,
            'matched_query' => '',
        ];
    }

    return tecdoc_find_image_payload_from_search_codes(
        $searchCodes,
        $code,
        $brand,
        ['OENumber', 'IAMNumber', 'ArticleNumber'],
        15
    );
}

function tecdoc_find_image_payload(string $code, string $brand = '', array $extraCodes = []): array
{
    $code = trim($code);
    $searchCodes = tecdoc_collect_image_search_codes($code, $extraCodes);
    if ($searchCodes === []) {
        return [
            'url' => '',
            'article_id' => '',
            'article_brand' => '',
            'article' => null,
            'matched_query' => '',
        ];
    }

    return tecdoc_find_image_payload_from_search_codes($searchCodes, $code, $brand);
}

function tecdoc_find_image_url(string $code, string $brand = ''): string
{
    return (string)(tecdoc_find_image_payload($code, $brand)['url'] ?? '');
}

function serpapi_find_image_url(string $query): string
{
    $query = trim($query);
    if ($query === '') return '';

    $apiKey = (string)($_ENV['SERPAPI_KEY'] ?? getenv('SERPAPI_KEY') ?: '');
    if ($apiKey === '') return '';

    $vendorFile = dirname(__DIR__) . '/admin/vendor/serpapi/google-search-results-php/google-search-results.php';
    if (!class_exists('GoogleSearchResults') && is_file($vendorFile)) {
        require_once $vendorFile;
    }
    if (!class_exists('GoogleSearchResults')) return '';

    try {
        $search = new GoogleSearchResults($apiKey);
        $data = $search->get_json([
            'engine' => 'google_images',
            'q' => $query,
            'hl' => 'ro',
            'gl' => 'ro',
            'safe' => 'off',
        ]);

        foreach (($data->images_results ?? []) as $imageResult) {
            $url = (string)($imageResult->original ?? $imageResult->thumbnail ?? '');
            if ($url !== '' && preg_match('~^https?://~i', $url)) {
                return $url;
            }
        }
    } catch (Throwable $e) {
        return '';
    }

    return '';
}

function tecdoc_download_image(string $url, string $code): string
{
    if ($url === '') return '';
    $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $code) ?: md5($url);
    $relative = '/uploads/products/tecdoc/' . $safeCode . '.jpg';
    $target = dirname(__DIR__) . $relative;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'],
    ]);
    $data = curl_exec($curl);
    curl_close($curl);
    if (!is_string($data) || strlen($data) < 200) return '';
    if (@getimagesizefromstring($data) === false) return '';
    file_put_contents($target, $data);
    return $relative;
}

function tecdoc_attach_import_image(array $product): array
{
    $existing = json_decode((string)($product['pImages'] ?? '[]'), true);
    if (is_array($existing) && !empty($existing[0])) return $product;
    $code = trim((string)($product['pCode'] ?? ''));
    if ($code === '') return $product;
    $image = tecdoc_download_image(tecdoc_find_image_url($code, (string)($product['pBrand'] ?? '')), $code);
    if ($image !== '') {
        $product['pImages'] = json_encode([$image], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return $product;
}