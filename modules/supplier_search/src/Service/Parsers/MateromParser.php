<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Parsers;

final class MateromParser
{
    /** @return array<int, array<string, mixed>> */
    public function parse(string $query, mixed $rawResponse, string $rawBody = ''): array
    {
        $body = is_array($rawResponse) ? $rawResponse : (json_decode($rawBody, true) ?: []);
        if (!is_array($body)) {
            return [];
        }

        $entries = [];
        foreach ($body as $product) {
            if (!is_array($product)) {
                continue;
            }

            $baseCode = str_replace([' ', '-', '/', '|', '\\'], '', trim((string) ($product['mfrpn'] ?? '')));
            if ($baseCode === '') {
                continue;
            }

            $variants = $product['pricingVariants'] ?? [];
            $variants = array_values(array_filter(
                is_array($variants) ? $variants : [],
                static fn ($v): bool => empty($v['is_resealed']) || (int) $v['is_resealed'] !== 1
            ));

            if ($variants === []) {
                continue;
            }

            $entries[] = [
                'code' => $baseCode,
                'mfrpn' => $baseCode,
                'manufacturer' => self::text($product['manufacturer'] ?? null),
                'db_name' => null,
                'name' => self::text($product['name'] ?? null),
                'ean' => $product['ean'] ?? null,
                'material' => $product['material'] ?? null,
                'supplier_name' => 'materom',
                'variants' => $variants,
            ];
        }

        return $entries;
    }

    private static function text(mixed $value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $text = trim((string) $value);
            return $text !== '' ? $text : null;
        }
        if (!is_array($value)) {
            return null;
        }
        foreach (['name', 'label', 'title'] as $key) {
            if (!isset($value[$key]) || !is_scalar($value[$key])) {
                continue;
            }
            $text = trim((string) $value[$key]);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }
}
