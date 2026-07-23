<?php

declare(strict_types=1);

/**
 * Încarcă funcțiile de imagine din import fără a declanșa handler-ul HTTP JSON.
 */
final class ImportImageBridge
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
            define('IMPORT_PRODUCE_SKIP_HTTP', true);
        }

        $path = dirname(__DIR__, 2) . '/admin/src/Controllers/Produse/importproduse.php';
        if (is_file($path)) {
            require_once $path;
        }

        self::$booted = true;
    }

    /** @return array<string, mixed> */
    public static function findFromOemCrossList(array $product, bool $fast = false, string $queryLabel = ''): array
    {
        self::boot();
        if (!function_exists('import_find_image_from_oem_cross_list')) {
            return [
                'url' => '',
                'source' => 'tecdoc_api',
                'query' => $queryLabel,
                'api_error' => 'TecDoc: funcție OEM cross lipsă',
            ];
        }

        return import_find_image_from_oem_cross_list($product, $fast, $queryLabel);
    }

    /** @param array<string, mixed> $hit @return array<string, mixed> */
    public static function normalizeHit(array $hit): array
    {
        self::boot();
        if (function_exists('import_normalize_image_hit')) {
            return import_normalize_image_hit($hit);
        }

        return $hit;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $hit
     * @return array<string, mixed>
     */
    public static function applyHit(array $product, array $hit): array
    {
        self::boot();
        if (function_exists('import_apply_image_lookup_result')) {
            return import_apply_image_lookup_result($product, $hit);
        }

        $url = trim((string) ($hit['url'] ?? ''));
        if ($url !== '') {
            $product['pImages'] = json_encode([$url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $product['pImageSource'] = (string) ($hit['source'] ?? '');
        }

        return $product;
    }
}
