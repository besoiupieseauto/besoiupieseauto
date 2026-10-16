<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service;

use Config\Database;
use Besoiu\Core\Supplier\SupplierCatalogRepository;
use PDO;

/**
 * Config credențiale furnizori M2 (env + tabel furnizori).
 */
final class SupplierSearchConfig
{
    public static function backendRoot(): string
    {
        if (defined('BESOIU_BACKEND')) {
            return (string) BESOIU_BACKEND;
        }

        return dirname(__DIR__, 4) . '/app/Backend';
    }

    public static function adminRoot(): string
    {
        if (defined('BESOIU_ADMIN')) {
            return (string) BESOIU_ADMIN;
        }

        return dirname(__DIR__, 4) . '/admin';
    }

    public static function ensureImportSupplierLibLoaded(): void
    {
        if (function_exists('import_furnizori_catalog')) {
            return;
        }

        require_once self::backendRoot() . '/src/Controllers/Produse/import_supplier_lib.php';
    }

    /** @return array<string, mixed> */
    public static function secretsForCode(string $code): array
    {
        static $secrets = null;
        if ($secrets === null) {
            self::ensureImportSupplierLibLoaded();
            $loaded = import_furnizori_load_secrets();
            $secrets = is_array($loaded) ? $loaded : [];
        }

        $normalized = function_exists('mb_strtoupper')
            ? mb_strtoupper(trim($code), 'UTF-8')
            : strtoupper(trim($code));

        return is_array($secrets[$normalized] ?? null) ? $secrets[$normalized] : [];
    }

    private static function envValue(string $key): string
    {
        $fromProcess = trim((string) ($_ENV[$key] ?? ''));
        if ($fromProcess === '') {
            $fromGetenv = getenv($key);
            $fromProcess = $fromGetenv === false ? '' : trim((string) $fromGetenv);
        }
        if ($fromProcess !== '') {
            return $fromProcess;
        }

        static $file = null;
        if ($file === null) {
            $file = [];
            $path = dirname(self::backendRoot()) . '/Config/.env';
            if (is_file($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    [$name, $value] = explode('=', $line, 2);
                    $file[trim($name)] = trim($value, " \t\"'");
                }
            }
        }

        return trim((string) ($file[$key] ?? ''));
    }

    /** @param array<string, mixed> $entry */
    public static function materomTokenFromEntry(array $entry = []): string
    {
        $fromEnv = trim((string) (
            $_ENV['MATEROM_TOKEN_TIMISOARA']
            ?? $_ENV['MATEROM_API_TOKEN']
            ?? $_ENV['MATEROM_TOKEN']
            ?? $_ENV['MATEROM_TOKEN_UTVIN']
            ?? ''
        ));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $secrets = self::secretsForCode('MATEROM');

        return trim((string) (
            $secrets['search_token']
            ?? $secrets['api_token']
            ?? $entry['api_token']
            ?? ''
        ));
    }

    public static function materomToken(): string
    {
        return self::materomTokenFromEntry();
    }

    public static function materomBaseUrl(): string
    {
        $config = self::all();

        return rtrim(trim((string) ($_ENV['MATEROM_API_BASE_URL'] ?? $config['materom_base_url'] ?? '')), '/');
    }

    /** @return array<string, mixed>|null */
    public static function autopartnerFurnizor(): ?array
    {
        $row = null;
        try {
            $model = new SupplierCatalogRepository();
            $found = $model->findByCode('AUTOPARTNER');
            if (is_array($found)) {
                $row = $found;
            }
        } catch (\Throwable) {
            $row = null;
        }

        $secrets = self::secretsForCode('AUTOPARTNER');
        if ($row === null && $secrets === []) {
            return null;
        }

        $merged = array_merge([
            'code' => 'AUTOPARTNER',
            'name' => 'Auto Partner',
            'status' => 'active',
        ], $row ?? []);
        foreach ($secrets as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (!array_key_exists($key, $merged) || trim((string) $merged[$key]) === '') {
                $merged[$key] = $value;
            }
        }

        $hasApi = trim((string) ($merged['api_base_url'] ?? '')) !== ''
            || trim((string) ($merged['api_token'] ?? '')) !== ''
            || trim((string) ($merged['api_client_code'] ?? '')) !== ''
            || trim((string) ($merged['conn_username'] ?? '')) !== '';

        return $hasApi ? $merged : null;
    }

    public static function legacyPdo(): ?PDO
    {
        if (!Database::hasConnection('legacy')) {
            return null;
        }

        try {
            return Database::getDB('legacy');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function autonetTaxCode(): string
    {
        $fromEnv = trim((string) ($_ENV['AUTONET_TAX_CODE'] ?? ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $secrets = self::secretsForCode('AUTONET');

        $fromSecrets = trim((string) ($secrets['search_tax_code'] ?? $secrets['tax_code'] ?? ''));
        if ($fromSecrets !== '') {
            return $fromSecrets;
        }

        // Default Laravel AutonetService (caiet comenzi) — folosit când lipsește .env
        return 'RO31298897';
    }

    public static function autonetSecurityToken(): string
    {
        $fromEnv = trim((string) ($_ENV['AUTONET_SECURITY_TOKEN'] ?? ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $secrets = self::secretsForCode('AUTONET');

        $fromSecrets = trim((string) ($secrets['search_security_token'] ?? $secrets['security_token'] ?? ''));
        if ($fromSecrets !== '') {
            return $fromSecrets;
        }

        return '08VGHXNPAH';
    }

    public static function autonetBranch(): string
    {
        return trim((string) ($_ENV['AUTONET_BRANCH'] ?? 'MAG1'));
    }

    public static function autonetBaseUrl(): string
    {
        $staging = filter_var($_ENV['AUTONET_USE_STAGING'] ?? false, FILTER_VALIDATE_BOOL);

        return $staging ? 'https://wes-stage.autonet-group.com' : 'https://wes.autonet-group.com';
    }

    /** @return array{username:string,password:string} */
    public static function autototalCredentials(): array
    {
        $secrets = self::secretsForCode('AUTOTOTAL');

        return [
            'username' => self::envValue('AUTOTOTAL_USERNAME')
                ?: self::envValue('AUTOTOTAL_USER_TIMISOARA')
                ?: trim((string) ($secrets['search_username'] ?? $secrets['conn_username'] ?? '')),
            'password' => self::envValue('AUTOTOTAL_PASSWORD')
                ?: self::envValue('AUTOTOTAL_PASS_TIMISOARA')
                ?: trim((string) ($secrets['search_password'] ?? $secrets['conn_password'] ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return require self::backendRoot() . '/config/supplier_search.php';
    }

    public static function autototalAvailabilityBaseUrl(): string
    {
        return rtrim(trim((string) ($_ENV['AUTOTOTAL_AVAILABILITY_URL'] ?? 'https://atx.autototal.ro:15063')), '/');
    }

    /** @return array{username:string,password:string} */
    public static function elitCredentials(): array
    {
        $secrets = self::secretsForCode('ELIT');
        $username = self::envValue('ELIT_USERNAME')
            ?: self::envValue('ELIT_USER')
            ?: trim((string) ($secrets['search_username'] ?? ''));
        $password = self::envValue('ELIT_PASSWORD')
            ?: self::envValue('ELIT_PASS')
            ?: trim((string) ($secrets['search_password'] ?? ''));

        if ($username === '' || $password === '') {
            $scraperCfg = self::scraperConfig();
            $elitAcc = $scraperCfg['sites']['elit'] ?? $scraperCfg['accounts']['elit'] ?? [];
            if ($username === '') {
                $username = trim((string) ($elitAcc['username'] ?? 'besoiutm2015'));
            }
            if ($password === '') {
                $password = trim((string) ($elitAcc['password'] ?? 'Ro31298897'));
            }
        }

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    public static function elitBaseUrl(): string
    {
        return rtrim(trim((string) ($_ENV['ELIT_BASE_URL'] ?? 'https://www.elit.ro')), '/');
    }

    public static function elitStatePath(): string
    {
        $fromEnv = self::envValue('ELIT_STATE_PATH');
        if ($fromEnv !== '' && is_file($fromEnv)) {
            return $fromEnv;
        }

        $candidates = [
            'D:\\laragon\\www\\scraper\\debug_probe\\elit_state.json',
            dirname(self::backendRoot(), 3) . DIRECTORY_SEPARATOR . 'scraper' . DIRECTORY_SEPARATOR . 'debug_probe' . DIRECTORY_SEPARATOR . 'elit_state.json',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $candidates[0];
    }

    public static function intercarsBaseUrl(): string
    {
        return rtrim(trim((string) ($_ENV['INTERCARS_BASE_URL'] ?? 'https://ecat-pct.intercars.eu/RO')), '/');
    }

    public static function intercarsCustomerCode(): string
    {
        return trim((string) ($_ENV['INTERCARS_CUSTOMER_CODE'] ?? 'R39196'));
    }

    public static function intercarsBranch(): string
    {
        return trim((string) ($_ENV['INTERCARS_BRANCH'] ?? 'R86'));
    }

    public static function intercarsCredsPath(): string
    {
        $fromEnv = self::envValue('INTERCARS_CREDS_PATH');
        if ($fromEnv !== '' && is_file($fromEnv)) {
            return $fromEnv;
        }

        $candidates = [
            'D:\\laragon\\www\\maparea\\intercars\\ic_creds.json',
            dirname(self::backendRoot(), 3) . DIRECTORY_SEPARATOR . 'maparea' . DIRECTORY_SEPARATOR . 'intercars' . DIRECTORY_SEPARATOR . 'ic_creds.json',
            self::cacheDir() . '/ic_creds.json',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $candidates[0];
    }

    public static function bardiBaseUrl(): string
    {
        $fromEnv = self::envValue('BARDI_BASE_URL');

        return rtrim($fromEnv !== '' ? $fromEnv : 'https://www.bardiauto.ro/webshop', '/');
    }

    /** @return array{username:string,password:string} */
    public static function bardiCredentials(): array
    {
        $secrets = self::secretsForCode('BARDI');
        $username = self::envValue('BARDI_USERNAME')
            ?: trim((string) ($secrets['search_username'] ?? ''));
        $password = self::envValue('BARDI_PASSWORD')
            ?: trim((string) ($secrets['search_password'] ?? ''));

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    public static function bardiHasCredentials(): bool
    {
        $creds = self::bardiCredentials();

        return $creds['username'] !== '' && $creds['password'] !== '';
    }

    public static function intercarsHasCredentials(): bool
    {
        $path = self::intercarsCredsPath();
        if (!is_file($path)) {
            return false;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return false;
        }

        $headers = $decoded['headers'] ?? [];
        if (is_array($headers) && !empty($headers['Authorization'])) {
            return true;
        }

        return !empty($decoded['cookies']);
    }

    public static function szakalBaseUrl(): string
    {
        $secrets = self::secretsForCode('SZAKAL');
        $config = self::all();

        return rtrim(trim((string) (
            self::envValue('SZAKAL_API_BASE_URL')
            ?: self::envValue('SZAKAL_BASE_URL')
            ?: ($secrets['api_base_url'] ?? '')
            ?: ($config['szakal_base_url'] ?? 'https://webservice.szakalmetal.hu')
        )), '/');
    }

    /** @return array{username:string,fallback_username:string,password:string} */
    public static function szakalCredentials(): array
    {
        $secrets = self::secretsForCode('SZAKAL');
        $username = self::envValue('SZAKAL_USERNAME')
            ?: self::envValue('SZAKAL_USER')
            ?: trim((string) ($secrets['search_username'] ?? ''));
        $fallbackUsername = self::envValue('SZAKAL_FALLBACK_USERNAME')
            ?: trim((string) ($secrets['search_fallback_username'] ?? 'besoiupieseauto'));
        $password = self::envValue('SZAKAL_PASSWORD')
            ?: self::envValue('SZAKAL_PASS')
            ?: trim((string) ($secrets['search_password'] ?? ''));

        if ($username === '' || $password === '') {
            $scraperCfg = self::scraperConfig();
            $szakalAcc = $scraperCfg['sites']['szakal'] ?? $scraperCfg['accounts']['szakal'] ?? $scraperCfg['szakal'] ?? [];
            if ($username === '') {
                $username = trim((string) ($szakalAcc['username'] ?? $szakalAcc['user'] ?? 'besoiu2003'));
            }
            if ($password === '') {
                $password = trim((string) ($szakalAcc['password'] ?? $szakalAcc['pass'] ?? 'Ro31298897'));
            }
        }

        return [
            'username' => $username,
            'fallback_username' => $fallbackUsername,
            'password' => $password,
        ];
    }

    public static function szakalHasCredentials(): bool
    {
        $creds = self::szakalCredentials();

        return $creds['username'] !== '' && $creds['password'] !== '';
    }

    /** @return array<string, mixed> */
    private static function scraperConfig(): array
    {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }

        $cfg = [];
        $candidates = [
            'D:\\laragon\\www\\scraper\\config.json',
            dirname(self::backendRoot(), 3) . DIRECTORY_SEPARATOR . 'scraper' . DIRECTORY_SEPARATOR . 'config.json',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $decoded = json_decode((string) file_get_contents($path), true);
                if (is_array($decoded)) {
                    $cfg = $decoded;
                    break;
                }
            }
        }

        return $cfg;
    }

    public static function configureCurl(\CurlHandle $handle): void
    {
        $ca = self::caBundle();
        if ($ca === null) {
            return;
        }

        curl_setopt($handle, CURLOPT_CAINFO, $ca);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
    }

    private static function caBundle(): ?string
    {
        $ini = trim((string) ini_get('curl.cainfo'));
        if ($ini !== '' && is_file($ini)) {
            return null;
        }

        $candidates = ['D:\\laragon\\etc\\ssl\\cacert.pem'];
        if (PHP_BINARY !== '') {
            $candidates[] = dirname(PHP_BINARY, 4) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'cacert.pem';
            $candidates[] = dirname(PHP_BINARY, 5) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'cacert.pem';
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function cacheDir(): string
    {
        $dir = self::adminRoot() . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** @return list<string> */
    public static function supportedSuppliersFromCatalog(): array
    {
        self::ensureImportSupplierLibLoaded();

        $slugs = [];
        foreach (import_furnizori_catalog() as $code => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $status = strtolower(trim((string) ($entry['status'] ?? 'active')));
            if ($status === 'deleted' || $status === 'blocked') {
                continue;
            }

            $slug = import_furnizori_search_slug((string) $code);
            if ($slug !== '' && in_array($slug, import_furnizori_search_supported_slugs(), true)) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    public static function normalizeQuery(string $query): string
    {
        return preg_replace('/[\s\-\/|\\\\]+/', '', trim($query)) ?? '';
    }
}
