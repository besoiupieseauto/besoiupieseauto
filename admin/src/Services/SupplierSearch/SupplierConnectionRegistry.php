<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

require_once dirname(__DIR__, 2) . '/Controllers/Produse/import_supplier_lib.php';

/**
 * Stare conexiune per furnizor B2B — derivata din catalogul central (cartela furnizor).
 */
final class SupplierConnectionRegistry
{
    /** @var array<string, array{key:string, label:string, short:string}> */
    private const SEARCH_META = [
        'AUTOTOTAL' => ['key' => 'autototal', 'label' => 'Autototal', 'short' => 'AT'],
        'AUTONET' => ['key' => 'autonet', 'label' => 'Autonet', 'short' => 'AN'],
        'MATEROM' => ['key' => 'materom', 'label' => 'Materom', 'short' => 'MA'],
        'ELIT' => ['key' => 'elit', 'label' => 'Elit', 'short' => 'EL'],
        'AUTOPARTNER' => ['key' => 'autopartner', 'label' => 'Auto Partner', 'short' => 'AP'],
    ];

    /** @return array<string, array{key:string, label:string, short:string, connected:bool, reason:string}> */
    public static function all(): array
    {
        $result = [];
        $catalog = import_furnizori_catalog();
        $supportedSlugs = import_furnizori_search_supported_slugs();

        foreach ($catalog as $code => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $status = strtolower(trim((string) ($entry['status'] ?? 'active')));
            if ($status === 'deleted' || $status === 'blocked') {
                continue;
            }

            $meta = self::SEARCH_META[$code] ?? self::buildMetaFromEntry($code, $entry);
            $slug = (string) ($meta['key'] ?? '');
            if ($slug === '' || !in_array($slug, $supportedSlugs, true)) {
                continue;
            }

            $statusInfo = self::resolveStatus($slug, $entry);
            $result[$slug] = [
                'key' => $slug,
                'label' => (string) ($meta['label'] ?? $code),
                'short' => (string) ($meta['short'] ?? strtoupper(substr($slug, 0, 2))),
                'connected' => $statusInfo['connected'],
                'reason' => $statusInfo['reason'],
            ];
        }

        return $result;
    }

    /** @return list<string> */
    public static function connectedKeys(): array
    {
        $keys = [];
        foreach (self::all() as $key => $row) {
            if (!empty($row['connected'])) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @param array<string, mixed> $entry @return array{key:string, label:string, short:string} */
    private static function buildMetaFromEntry(string $code, array $entry): array
    {
        $slug = import_furnizori_search_slug($code);
        $label = trim((string) ($entry['name'] ?? $code));

        return [
            'key' => $slug,
            'label' => $label !== '' ? $label : $code,
            'short' => strtoupper(substr($slug, 0, 2)),
        ];
    }

    /** @param array<string, mixed> $entry @return array{connected:bool, reason:string} */
    private static function resolveStatus(string $key, array $entry): array
    {
        $resolved = import_furnizori_resolve_credentials($entry);

        return match ($key) {
            'materom' => SupplierSearchConfig::materomToken() !== ''
                ? ['connected' => true, 'reason' => '']
                : ['connected' => false, 'reason' => 'Token Materom lipsă (MATEROM_TOKEN_TIMISOARA).'],
            'elit' => SupplierSearchConfig::legacyPdo() !== null
                ? ['connected' => true, 'reason' => '']
                : ['connected' => false, 'reason' => 'BD legacy indisponibilă (LEGACY_DB_*).'],
            'autopartner' => self::autopartnerConnected($resolved)
                ? ['connected' => true, 'reason' => '']
                : ['connected' => false, 'reason' => 'Furnizor AUTOPARTNER neconfigurat în cartela furnizor (API/token).'],
            'autonet' => (SupplierSearchConfig::autonetTaxCode() !== '' && SupplierSearchConfig::autonetSecurityToken() !== '')
                ? ['connected' => true, 'reason' => '']
                : ['connected' => false, 'reason' => 'Credențiale Autonet lipsă (AUTONET_TAX_CODE / AUTONET_SECURITY_TOKEN).'],
            'autototal' => SupplierSearchConfig::autototalCredentials()['username'] !== ''
                ? ['connected' => true, 'reason' => '']
                : ['connected' => false, 'reason' => 'Credențiale Autototal lipsă (AUTOTOTAL_USERNAME/PASSWORD).'],
            default => ['connected' => false, 'reason' => 'Furnizor necunoscut.'],
        };
    }

    /** @param array<string, mixed> $entry */
    private static function autopartnerConnected(array $entry): bool
    {
        if (SupplierSearchConfig::autopartnerFurnizor() !== null) {
            return true;
        }

        $apiToken = trim((string) ($entry['api_token'] ?? ''));
        $apiBaseUrl = trim((string) ($entry['api_base_url'] ?? ''));

        return $apiToken !== '' && $apiBaseUrl !== '';
    }
}
