<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

final class ProductAggregator
{
    private const SUPPLIER_KEYS = ['autopartner', 'materom', 'autonet', 'autototal', 'elit'];

    private function isMissingValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }

        $normalized = strtolower(trim($value));

        return $normalized === '' || $normalized === '-' || $normalized === 'null';
    }

    private function normalizeValue(mixed $value): mixed
    {
        return $this->isMissingValue($value) ? null : $value;
    }

    /** @param array<int, array<string, mixed>> $entries @return array<string, array<string, mixed>> */
    public function merge(array $entries): array
    {
        $productsMap = [];
        $skeleton = array_fill_keys(self::SUPPLIER_KEYS, ['variants' => []]);

        foreach ($entries as $entry) {
            $code = (string) ($entry['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $supplierName = (string) ($entry['supplier_name'] ?? '');
            $variants = $entry['variants'] ?? [];

            if (!isset($productsMap[$code])) {
                $productsMap[$code] = [
                    'mfrpn' => $entry['mfrpn'] ?? $code,
                    'manufacturer' => $this->normalizeValue($entry['manufacturer'] ?? null),
                    'db_name' => $this->normalizeValue($entry['db_name'] ?? null),
                    'name' => $this->normalizeValue($entry['name'] ?? null),
                    'ean' => $this->normalizeValue($entry['ean'] ?? null),
                    'material' => $this->normalizeValue($entry['material'] ?? null),
                    'suppliers' => $skeleton,
                ];
            } else {
                foreach (['manufacturer', 'db_name', 'name', 'ean', 'material'] as $field) {
                    if ($this->isMissingValue($productsMap[$code][$field]) && !$this->isMissingValue($entry[$field] ?? null)) {
                        $productsMap[$code][$field] = $entry[$field];
                    }
                }
            }

            if ($supplierName !== '' && isset($productsMap[$code]['suppliers'][$supplierName])) {
                $existing = $productsMap[$code]['suppliers'][$supplierName]['variants'];
                $productsMap[$code]['suppliers'][$supplierName]['variants'] = array_merge($existing, $variants);
            }
        }

        return $productsMap;
    }
}
