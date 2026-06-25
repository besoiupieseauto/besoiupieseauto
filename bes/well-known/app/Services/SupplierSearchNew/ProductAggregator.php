<?php

namespace App\Services\SupplierSearchNew;

class ProductAggregator
{
    private const SUPPLIER_KEYS = ['autopartner', 'materom', 'autonet', 'autototal', 'elit'];

    /**
     * Values like "-", empty string or literal "null" should not block real data.
     */
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

    /**
     * Merge parser entries into a single productsMap (code => product).
     * Each entry has: code, mfrpn, manufacturer, db_name, name, ean, material, supplier_name, variants.
     */
    public function merge(array $entries): array
    {
        $productsMap = [];
        $skeleton = array_fill_keys(self::SUPPLIER_KEYS, ['variants' => []]);

        foreach ($entries as $entry) {
            $code = $entry['code'] ?? '';
            if ($code === '') {
                continue;
            }
            $supplierName = $entry['supplier_name'] ?? '';
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
                if ($this->isMissingValue($productsMap[$code]['manufacturer']) && !$this->isMissingValue($entry['manufacturer'] ?? null)) {
                    $productsMap[$code]['manufacturer'] = $entry['manufacturer'];
                }
                if ($this->isMissingValue($productsMap[$code]['db_name']) && !$this->isMissingValue($entry['db_name'] ?? null)) {
                    $productsMap[$code]['db_name'] = $entry['db_name'];
                }
                if ($this->isMissingValue($productsMap[$code]['name']) && !$this->isMissingValue($entry['name'] ?? null)) {
                    $productsMap[$code]['name'] = $entry['name'];
                }
                if ($this->isMissingValue($productsMap[$code]['ean']) && !$this->isMissingValue($entry['ean'] ?? null)) {
                    $productsMap[$code]['ean'] = $entry['ean'];
                }
                if ($this->isMissingValue($productsMap[$code]['material']) && !$this->isMissingValue($entry['material'] ?? null)) {
                    $productsMap[$code]['material'] = $entry['material'];
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
