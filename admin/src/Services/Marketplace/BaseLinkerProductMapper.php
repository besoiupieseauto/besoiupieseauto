<?php

declare(strict_types=1);

namespace Evasystem\Services\Marketplace;

/**
 * Mapare câmpuri produs Besoiu → payload BaseLinker addInventoryProduct.
 */
final class BaseLinkerProductMapper
{
    /** @return array<string, string> */
    public static function defaultMapping(): array
    {
        return [
            'name' => 'pName',
            'sku' => 'pCode',
            'price_brutto' => 'pPrice',
            'description' => 'pNoteMarketplace',
            'quantity' => 'pStock',
            'images' => 'pImages',
            'text_fields' => 'json:{"Brand":"pBrand","OEM":"pOem","Categorie":"pCategory"}',
        ];
    }

    /** @param array<string, mixed> $stored @return array<string, string> */
    public static function resolveMapping(array $stored): array
    {
        $defaults = self::defaultMapping();
        foreach ($defaults as $blField => $defaultSource) {
            if (!isset($stored[$blField]) || trim((string) $stored[$blField]) === '') {
                $stored[$blField] = $defaultSource;
            }
        }

        /** @var array<string, string> $normalized */
        $normalized = [];
        foreach ($stored as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalized[$key] = trim((string) $value);
        }

        return $normalized;
    }

    /** @param array<string, mixed>|null $raw */
    public static function decodeStored(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return self::defaultMapping();
        }

        return self::resolveMapping($raw);
    }

    /** @param array<string, mixed> $product @param array<string, string> $mapping @return array<string, mixed> */
    public static function toBaseLinkerPayload(array $product, array $mapping, string $siteBaseUrl): array
    {
        $payload = [];

        foreach (['name', 'sku', 'price_brutto', 'description', 'quantity'] as $field) {
            $source = $mapping[$field] ?? '';
            if ($source === '') {
                continue;
            }
            $value = self::readProductField($product, $source);
            if ($value === null || $value === '') {
                continue;
            }
            if ($field === 'price_brutto') {
                $payload[$field] = round((float) $value, 2);
                continue;
            }
            if ($field === 'quantity') {
                $payload[$field] = max(0, (int) $value);
                continue;
            }
            $payload[$field] = (string) $value;
        }

        $imagesSource = $mapping['images'] ?? 'pImages';
        $images = self::resolveImages($product, $imagesSource, $siteBaseUrl);
        if ($images !== []) {
            $payload['images'] = $images;
        }

        $extra = self::buildExtraDescription($product, $mapping);
        if ($extra !== '') {
            $baseDescription = (string) ($payload['description'] ?? '');
            $payload['description'] = trim($baseDescription . ($baseDescription !== '' ? "\n\n" : '') . $extra);
        }

        if (($payload['sku'] ?? '') === '' && !empty($product['randomn_id'])) {
            $payload['sku'] = (string) $product['randomn_id'];
        }

        if (($payload['name'] ?? '') === '') {
            $payload['name'] = 'Produs #' . (string) ($product['randomn_id'] ?? $product['id'] ?? '');
        }

        return $payload;
    }

    /** @return list<string> */
    public static function allowedSourceFields(): array
    {
        return [
            'pName', 'pCode', 'pOem', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pCar',
            'pCategory', 'pSubcategory', 'pPrice', 'pBasePrice', 'pStock', 'pNote',
            'pNoteWebsite', 'pNoteMarketplace', 'pImages', 'pSupplier', 'randomn_id',
        ];
    }

    /** @param array<string, mixed> $product */
    private static function readProductField(array $product, string $source): mixed
    {
        if (str_starts_with($source, 'json:')) {
            return null;
        }

        return $product[$source] ?? null;
    }

    /** @param array<string, mixed> $product @return list<string> */
    private static function resolveImages(array $product, string $source, string $siteBaseUrl): array
    {
        $raw = $product[$source] ?? '[]';
        if (!is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $urls = [];
        foreach ($decoded as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }
            $urls[] = self::absoluteUrl($item, $siteBaseUrl);
        }

        return array_values(array_unique($urls));
    }

    /** @param array<string, mixed> $product @param array<string, string> $mapping */
    private static function buildExtraDescription(array $product, array $mapping): string
    {
        $textFieldsRaw = $mapping['text_fields'] ?? '';
        if ($textFieldsRaw === '' || !str_starts_with($textFieldsRaw, 'json:')) {
            return '';
        }

        $decoded = json_decode(substr($textFieldsRaw, 5), true);
        if (!is_array($decoded)) {
            return '';
        }

        $lines = [];
        foreach ($decoded as $label => $source) {
            if (!is_string($label) || !is_string($source)) {
                continue;
            }
            $value = trim((string) ($product[$source] ?? ''));
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }

        return implode("\n", $lines);
    }

    private static function absoluteUrl(string $path, string $siteBaseUrl): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $base = rtrim($siteBaseUrl, '/');
        $relative = '/' . ltrim(str_replace('\\', '/', $path), '/');

        return $base . $relative;
    }
}
