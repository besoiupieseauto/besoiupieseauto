<?php

namespace App\Services\SupplierSearchNew\Parsers;

use App\Services\SupplierSearchNew\Contracts\SupplierParserInterface;
use Illuminate\Support\Facades\DB;

class ElitParser implements SupplierParserInterface
{
    public function parse(string $query, $rawResponse, string $rawBody): array
    {
        $entriesByCode = [];
        $normalizedQuery = preg_replace('/[.\s\-\/|\\\\]+/', '', $query);
        if ($normalizedQuery === '') {
            return [];
        }

        $ensureEntry = function (string $code, $manufacturer = null, $dbName = null) use (&$entriesByCode): void {
            if (!isset($entriesByCode[$code])) {
                $entriesByCode[$code] = [
                    'code' => $code,
                    'mfrpn' => $code,
                    'manufacturer' => $manufacturer,
                    'db_name' => $dbName,
                    'name' => null,
                    'ean' => null,
                    'material' => null,
                    'supplier_name' => 'elit',
                    'variants' => [],
                ];
                return;
            }

            if (empty($entriesByCode[$code]['manufacturer']) && !empty($manufacturer)) {
                $entriesByCode[$code]['manufacturer'] = $manufacturer;
            }
            if (empty($entriesByCode[$code]['db_name']) && !empty($dbName)) {
                $entriesByCode[$code]['db_name'] = $dbName;
            }
        };

        // 1) Direct LKQ lookup by searched code (legacy behavior)
        $directElitRows = DB::table('lkq_prices')
            ->where('supplier_catalog_nr', $query)
            ->get();

        if (!$directElitRows->isEmpty()) {
            $first = $directElitRows->first();
            $ensureEntry($normalizedQuery, $first->brand_name ?? null, $first->description_ro ?? null);
            foreach ($directElitRows as $elitRow) {
                $entriesByCode[$normalizedQuery]['variants'][] = $this->buildVariant($elitRow->item_nr ?? '', (float) ($elitRow->net_price ?? 0));
            }
        }

        // 2) Expand via parts_catalog and map to LKQ rows by supplier_catalog_nr + brand_name (legacy behavior)
        $partsRows = DB::table('parts_catalog')
            ->where('code_parts', $normalizedQuery)
            ->get();

        $partsMeta = [];
        $supplierCatalogNrs = [];
        $brands = [];
        foreach ($partsRows as $row) {
            $mainart = (string) ($row->mainart_code_parts ?? '');
            $baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainart);
            if ($mainart === '' || $baseCode === '') {
                continue;
            }

            $brand = $row->mainart_brands ?? null;
            if ($brand === 'ABAKUS') {
                $brand = 'DEPO';
            }
            $brand = is_string($brand) ? trim($brand) : $brand;
            if ($brand === '' || $brand === null) {
                continue;
            }

            $pairKey = $mainart . '|' . $brand;
            if (!isset($partsMeta[$pairKey])) {
                $partsMeta[$pairKey] = [
                    'base_code' => $baseCode,
                    'brand' => $brand,
                    'fallback_name' => $row->brands ?? null,
                ];
                $supplierCatalogNrs[$mainart] = true;
                $brands[$brand] = true;
            }
        }

        if (!empty($partsMeta)) {
            $lkqRows = DB::table('lkq_prices')
                ->whereIn('supplier_catalog_nr', array_keys($supplierCatalogNrs))
                ->whereIn('brand_name', array_keys($brands))
                ->get();

            $lkqByPair = [];
            foreach ($lkqRows as $lkqRow) {
                $pairKey = (string) ($lkqRow->supplier_catalog_nr ?? '') . '|' . (string) ($lkqRow->brand_name ?? '');
                $lkqByPair[$pairKey][] = $lkqRow;
            }

            foreach ($partsMeta as $pairKey => $meta) {
                $baseCode = $meta['base_code'];
                $brand = $meta['brand'];
                $elitRows = $lkqByPair[$pairKey] ?? [];
                $lkqDescription = !empty($elitRows) ? ($elitRows[0]->description_ro ?? null) : null;

                $ensureEntry($baseCode, $brand, $lkqDescription ?? ($meta['fallback_name'] ?? null));

                foreach ($elitRows as $elitRow) {
                    $entriesByCode[$baseCode]['variants'][] = $this->buildVariant(
                        $elitRow->item_nr ?? '',
                        (float) ($elitRow->net_price ?? 0)
                    );
                }
            }
        }

        // Keep only entries that actually have ELIT variants to avoid null/0 placeholders.
        $entries = array_values(array_filter($entriesByCode, static function (array $entry) {
            return !empty($entry['variants']);
        }));

        return $entries;
    }

    private function buildVariant(string $orderCode, float $price): array
    {
        return [
            'supplier_stock' => 1,
            'price' => $price,
            'currency' => 'RON',
            'order_code' => $orderCode,
            'depot' => null,
            'is_blocked' => false,
            'delivery' => [
                'info_text' => 'Verifica stoc',
                'plant_name' => null,
            ],
            'multiple_qty' => 1,
        ];
    }
}
