<?php

namespace App\Services\SupplierSearchNew;

use Illuminate\Support\Facades\DB;

/**
 * Enriches products that have Autonet variants with manufacturer/db_name from
 * parts_catalog row map (no extra DB) and QWP fallback (single batch query).
 */
class AutonetProductEnricher
{
    /**
     * Enrich productsMap: set manufacturer and db_name for products with autonet
     * variants using pre-built rowMap; for codes not in rowMap, try autonet_qwp_data (one query).
     *
     * @param array<string, array> $productsMap
     * @param array<string, object> $rowMap normalizedCode => parts_catalog row (mainart_brands, mainart_name)
     */
    public function enrich(array &$productsMap, array $rowMap): void
    {
        foreach ($productsMap as $code => &$product) {
            $variants = $product['suppliers']['autonet']['variants'] ?? [];
            if (empty($variants)) {
                continue;
            }
            if (!empty($product['manufacturer']) && !empty($product['db_name'])) {
                continue;
            }

            $row = $rowMap[$code] ?? null;
            if ($row !== null) {
                if (empty($product['manufacturer']) && !empty($row->mainart_brands)) {
                    $product['manufacturer'] = $row->mainart_brands;
                }
                if (empty($product['db_name']) && !empty($row->mainart_name)) {
                    $product['db_name'] = $row->mainart_name;
                }
            }
        }
        unset($product);

        $codesNeedingQwp = [];
        foreach ($productsMap as $code => $product) {
            $variants = $product['suppliers']['autonet']['variants'] ?? [];
            if (empty($variants)) {
                continue;
            }
            if (empty($product['manufacturer']) && empty($product['db_name'])) {
                $codesNeedingQwp[] = $code;
            }
        }

        if (empty($codesNeedingQwp)) {
            return;
        }

        $qwpRows = DB::table('autonet_qwp_data')
            ->whereIn('ArtNr', $codesNeedingQwp)
            ->orWhereIn('RefNr', $codesNeedingQwp)
            ->get();

        $normalize = static function (string $v): string {
            return preg_replace('/[.\s\-\/|\\\\]+/', '', $v);
        };
        foreach ($qwpRows as $row) {
            $artNr = $normalize(trim((string) ($row->ArtNr ?? '')));
            $refNr = $normalize(trim((string) ($row->RefNr ?? '')));
            if ($artNr !== '' && isset($productsMap[$artNr]) && empty($productsMap[$artNr]['manufacturer']) && empty($productsMap[$artNr]['db_name'])) {
                $productsMap[$artNr]['manufacturer'] = 'QWP';
                $productsMap[$artNr]['db_name'] = 'QWP';
            }
            if ($refNr !== '' && $refNr !== $artNr && isset($productsMap[$refNr]) && empty($productsMap[$refNr]['manufacturer']) && empty($productsMap[$refNr]['db_name'])) {
                $productsMap[$refNr]['manufacturer'] = 'QWP';
                $productsMap[$refNr]['db_name'] = 'QWP';
            }
        }
    }
}
