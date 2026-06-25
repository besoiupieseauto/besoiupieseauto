<?php

namespace App\Services\SupplierSearchNew\Parsers;

use App\Services\SupplierSearchNew\Contracts\SupplierParserInterface;

class MateromParser implements SupplierParserInterface
{
    public function parse(string $query, $rawResponse, string $rawBody): array
    {
        $entries = [];
        $body = is_array($rawResponse) ? $rawResponse : (json_decode($rawBody, true) ?: []);

        if (!is_array($body)) {
            return [];
        }

        foreach ($body as $product) {
            $baseCode = trim($product['mfrpn'] ?? '');
            $baseCode = str_replace([' ', '-', '/', '|', '\\'], '', $baseCode);
            if ($baseCode === '') {
                continue;
            }

            $variants = $product['pricingVariants'] ?? [];
            $variants = array_filter($variants, fn($v) => empty($v['is_resealed']) || $v['is_resealed'] != 1);

            $entries[] = [
                'code' => $baseCode,
                'mfrpn' => $baseCode,
                'manufacturer' => $product['manufacturer'] ?? null,
                'db_name' => null,
                'name' => $product['name'] ?? null,
                'ean' => $product['ean'] ?? null,
                'material' => $product['material'] ?? null,
                'supplier_name' => 'materom',
                'variants' => array_values($variants),
            ];
        }

        return $entries;
    }
}
