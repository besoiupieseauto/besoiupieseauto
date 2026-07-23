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
        $model = new SupplierCatalogRepository();
        $row = $model->findByCode('AUTOPARTNER');
        if ($row === null) {
            return null;
        }

        return $row;
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
            'username' => trim((string) (
                $_ENV['AUTOTOTAL_USERNAME']
                ?? $_ENV['AUTOTOTAL_USER_TIMISOARA']
                ?? $secrets['search_username']
                ?? $secrets['conn_username']
                ?? ''
            )),
            'password' => trim((string) (
                $_ENV['AUTOTOTAL_PASSWORD']
                ?? $_ENV['AUTOTOTAL_PASS_TIMISOARA']
                ?? $secrets['search_password']
                ?? $secrets['conn_password']
                ?? ''
            )),
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
