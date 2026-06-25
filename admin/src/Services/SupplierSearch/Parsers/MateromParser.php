<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Parsers;

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
                'manufacturer' => $product['manufacturer'] ?? null,
                'db_name' => null,
                'name' => $product['name'] ?? null,
                'ean' => $product['ean'] ?? null,
                'material' => $product['material'] ?? null,
                'supplier_name' => 'materom',
                'variants' => $variants,
            ];
        }

        return $entries;
    }
}
