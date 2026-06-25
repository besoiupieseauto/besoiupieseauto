<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

use Config\Database;
use Evasystem\Controllers\Furnizori\AutoPartnerApiClient;
use Evasystem\Core\Furnizori\FurnizoriModel;
use PDO;

/**
 * Config credențiale furnizori M2 (env + tabel furnizori).
 */
final class SupplierSearchConfig
{
    /** @return array<string, mixed> */
    public static function all(): array
    {
        return require dirname(__DIR__, 3) . '/config/supplier_search.php';
    }

    public static function materomToken(): string
    {
        return trim((string) (
            $_ENV['MATEROM_TOKEN_TIMISOARA']
            ?? $_ENV['MATEROM_API_TOKEN']
            ?? $_ENV['MATEROM_TOKEN']
            ?? ''
        ));
    }

    public static function materomBaseUrl(): string
    {
        $config = self::all();

        return rtrim(trim((string) ($_ENV['MATEROM_API_BASE_URL'] ?? $config['materom_base_url'] ?? '')), '/');
    }

    /** @return array<string, mixed>|null */
    public static function autopartnerFurnizor(): ?array
    {
        $model = new FurnizoriModel();
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
        return trim((string) ($_ENV['AUTONET_TAX_CODE'] ?? ''));
    }

    public static function autonetSecurityToken(): string
    {
        return trim((string) ($_ENV['AUTONET_SECURITY_TOKEN'] ?? ''));
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
        return [
            'username' => trim((string) ($_ENV['AUTOTOTAL_USERNAME'] ?? '')),
            'password' => trim((string) ($_ENV['AUTOTOTAL_PASSWORD'] ?? '')),
        ];
    }

    public static function autototalAvailabilityBaseUrl(): string
    {
        return rtrim(trim((string) ($_ENV['AUTOTOTAL_AVAILABILITY_URL'] ?? 'https://atx.autototal.ro:15063')), '/');
    }

    public static function cacheDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** @return list<string> */
    public static function supportedSuppliersFromCatalog(): array
    {
        if (!function_exists('import_furnizori_catalog')) {
            require_once dirname(__DIR__, 2) . '/Controllers/Produse/import_supplier_lib.php';
        }

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
