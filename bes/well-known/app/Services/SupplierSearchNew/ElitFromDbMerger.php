<?php

namespace App\Services\SupplierSearchNew;

use Illuminate\Support\Facades\DB;

/**
 * Merges Elit data from lkq_prices (DB) into productsMap, same as the old supplier search.
 */
class ElitFromDbMerger
{
    public function merge(string $query, string $rawQuery, array &$productsMap): void
    {
        $elitRows = DB::table('lkq_prices')
            ->where('supplier_catalog_nr', $rawQuery)
            ->get();

        if ($elitRows->isNotEmpty()) {
            $baseCode = $query;
            $first = $elitRows->first();
            $this->ensureProduct($productsMap, $baseCode, [
                'manufacturer' => $first->brand_name ?? null,
                'db_name' => $first->description_ro ?? null,
            ]);
            foreach ($elitRows as $row) {
                $productsMap[$baseCode]['suppliers']['elit']['variants'][] = [
                    'supplier_stock' => 1,
                    'price' => (float) ($row->net_price ?? 0),
                    'currency' => 'RON',
                    'order_code' => $row->item_nr ?? '',
                    'depot' => null,
                    'is_blocked' => false,
                    'delivery' => ['info_text' => 'Verifica stoc', 'plant_name' => null],
                    'multiple_qty' => 1,
                ];
            }
        }

        $rows = DB::table('parts_catalog')->where('code_parts', $query)->get();
        if ($rows->isEmpty()) {
            return;
        }

        $catalogNumbers = $rows->pluck('mainart_code_parts')->filter()->unique()->values()->all();
        $elitBrands = $rows->map(function ($row) {
            return ($row->mainart_brands ?? '') === 'ABAKUS' ? 'DEPO' : ($row->mainart_brands ?? '');
        })->filter()->unique()->values()->all();

        if (empty($catalogNumbers) || empty($elitBrands)) {
            return;
        }

        $allElitRows = DB::table('lkq_prices')
            ->whereIn('supplier_catalog_nr', $catalogNumbers)
            ->whereIn('brand_name', $elitBrands)
            ->get();

        $elitRowsByKey = [];
        foreach ($allElitRows as $row) {
            $key = ($row->supplier_catalog_nr ?? '') . '|' . ($row->brand_name ?? '');
            if (!isset($elitRowsByKey[$key])) {
                $elitRowsByKey[$key] = [];
            }
            $elitRowsByKey[$key][] = $row;
        }

        foreach ($rows as $row) {
            $baseCode = str_replace([' ', '-', '/', '|', '\\'], '', $row->mainart_code_parts ?? '');
            $originalCode = $row->mainart_code_parts;
            $brand = ($row->mainart_brands ?? '') === 'ABAKUS' ? 'DEPO' : ($row->mainart_brands ?? '');
            $elitKey = ($row->mainart_code_parts ?? '') . '|' . ($row->mainart_brands ?? '');
            $elitRowsForRow = $elitRowsByKey[$elitKey] ?? [];

            $lkqDescription = null;
            if (!empty($elitRowsForRow)) {
                $lkqDescription = $elitRowsForRow[0]->description_ro ?? null;
            }

            $this->ensureProduct($productsMap, $baseCode, [
                'manufacturer' => $brand,
                'db_name' => $lkqDescription ?? ($row->brands ?? null),
            ]);

            foreach ($elitRowsForRow as $elitRow) {
                $productsMap[$baseCode]['suppliers']['elit']['variants'][] = [
                    'supplier_stock' => 1,
                    'price' => (float) ($elitRow->net_price ?? 0),
                    'currency' => 'RON',
                    'order_code' => $elitRow->item_nr ?? '',
                    'depot' => null,
                    'is_blocked' => false,
                    'delivery' => ['info_text' => 'Verifica stoc', 'plant_name' => null],
                    'multiple_qty' => 1,
                ];
            }
        }
    }

    private function ensureProduct(array &$productsMap, string $code, array $defaults): void
    {
        $skeleton = [
            'autopartner' => ['variants' => []],
            'materom' => ['variants' => []],
            'autonet' => ['variants' => []],
            'autototal' => ['variants' => []],
            'elit' => ['variants' => []],
        ];
        if (!isset($productsMap[$code])) {
            $productsMap[$code] = [
                'mfrpn' => $code,
                'manufacturer' => $defaults['manufacturer'] ?? null,
                'db_name' => $defaults['db_name'] ?? null,
                'name' => null,
                'ean' => null,
                'material' => null,
                'suppliers' => $skeleton,
            ];
        } else {
            if (empty($productsMap[$code]['manufacturer']) && !empty($defaults['manufacturer'])) {
                $productsMap[$code]['manufacturer'] = $defaults['manufacturer'];
            }
            if (empty($productsMap[$code]['db_name']) && !empty($defaults['db_name'])) {
                $productsMap[$code]['db_name'] = $defaults['db_name'];
            }
        }
    }
}
