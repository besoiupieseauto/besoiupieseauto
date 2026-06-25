<?php

namespace App\Services\SupplierSearchNew\Parsers;

use App\Services\SupplierSearchNew\Contracts\SupplierParserInterface;

class AutototalParser implements SupplierParserInterface
{
    private function normalizeBrand(?string $brand): string
    {
        $brand = strtoupper(trim((string) $brand));
        if ($brand === '') {
            return '';
        }

        return preg_replace('/[^A-Z0-9]/', '', $brand) ?? '';
    }

    private function brandMatchesTarget(?string $actualBrand, ?string $targetBrand): bool
    {
        $target = $this->normalizeBrand($targetBrand);
        if ($target === '') {
            return true;
        }

        $actual = $this->normalizeBrand($actualBrand);
        return $actual !== '' && $actual === $target;
    }

    public function parse(string $query, $rawResponse, string $rawBody): array
    {
        $data = is_array($rawResponse) ? $rawResponse : (json_decode($rawBody, true) ?: []);
        if (isset($data[0]['response']) && isset($data[0]['meta'])) {
            return $this->parseAggregatedResponses($query, $data);
        }

        return $this->parseSingleResponse($query, $data, [
            'base_code' => preg_replace('/[.\s\-\/|\\\\]+/', '', $query) ?: $query,
            'manufacturer' => null,
            'db_name' => null,
            'sup_brand' => null,
        ]);
    }

    private function parseAggregatedResponses(string $query, array $items): array
    {
        $entries = [];
        $seenVariants = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $response = $item['response'] ?? [];
            $meta = $item['meta'] ?? [];
            if (!is_array($response) || !is_array($meta)) {
                continue;
            }

            $targets = $meta['targets'] ?? null;
            if (is_array($targets) && !empty($targets)) {
                foreach ($targets as $target) {
                    if (!is_array($target)) {
                        continue;
                    }
                    $parsed = $this->parseSingleResponse($query, $response, $target);
                    foreach ($parsed as $entry) {
                        $code = (string) ($entry['code'] ?? '');
                        if ($code === '') {
                            continue;
                        }
                        if (!isset($entries[$code])) {
                            $entries[$code] = $entry;
                            $entries[$code]['variants'] = [];
                        } else {
                            if (empty($entries[$code]['manufacturer']) && !empty($entry['manufacturer'])) {
                                $entries[$code]['manufacturer'] = $entry['manufacturer'];
                            }
                            if (empty($entries[$code]['db_name']) && !empty($entry['db_name'])) {
                                $entries[$code]['db_name'] = $entry['db_name'];
                            }
                            if (empty($entries[$code]['name']) && !empty($entry['name'])) {
                                $entries[$code]['name'] = $entry['name'];
                            }
                        }

                        foreach (($entry['variants'] ?? []) as $variant) {
                            $orderCode = (string) ($variant['order_code'] ?? '');
                            $price = (string) ($variant['price'] ?? '');
                            $vKey = $code . '|' . $orderCode . '|' . $price;
                            if (isset($seenVariants[$vKey])) {
                                continue;
                            }
                            $seenVariants[$vKey] = true;
                            $entries[$code]['variants'][] = $variant;
                        }
                    }
                }
                continue;
            }

            // Backward-compatible path for single-target metadata.
            $parsed = $this->parseSingleResponse($query, $response, $meta);
            foreach ($parsed as $entry) {
                $code = (string) ($entry['code'] ?? '');
                if ($code === '') {
                    continue;
                }
                if (!isset($entries[$code])) {
                    $entries[$code] = $entry;
                    $entries[$code]['variants'] = [];
                } else {
                    if (empty($entries[$code]['manufacturer']) && !empty($entry['manufacturer'])) {
                        $entries[$code]['manufacturer'] = $entry['manufacturer'];
                    }
                    if (empty($entries[$code]['db_name']) && !empty($entry['db_name'])) {
                        $entries[$code]['db_name'] = $entry['db_name'];
                    }
                    if (empty($entries[$code]['name']) && !empty($entry['name'])) {
                        $entries[$code]['name'] = $entry['name'];
                    }
                }

                foreach (($entry['variants'] ?? []) as $variant) {
                    $orderCode = (string) ($variant['order_code'] ?? '');
                    $price = (string) ($variant['price'] ?? '');
                    $vKey = $code . '|' . $orderCode . '|' . $price;
                    if (isset($seenVariants[$vKey])) {
                        continue;
                    }
                    $seenVariants[$vKey] = true;
                    $entries[$code]['variants'][] = $variant;
                }
            }
        }

        return array_values(array_filter($entries, static function (array $entry): bool {
            return !empty($entry['variants']);
        }));
    }

    private function parseSingleResponse(string $query, array $data, array $meta): array
    {
        $webApi = $data['webApiResponse'] ?? [];
        if (($webApi['status'] ?? null) != 1) {
            return [];
        }

        $searchCode = $data['searchCode'] ?? [];
        $availabilityList = $searchCode['availability'] ?? [];
        if (!is_array($availabilityList)) {
            $availabilityList = [];
        }
        $availability = $availabilityList[0] ?? null;
        $availabilityInfoText = $availability['arrivesAt'] ?? 'Verifica stoc';
        $warehouse = $availability['warehouse'] ?? null;
        $warehouseNames = [];
        $aggregatedStock = 0;
        foreach ($availabilityList as $availabilityRow) {
            if (!is_array($availabilityRow)) {
                continue;
            }
            $w = trim((string) ($availabilityRow['warehouse'] ?? ''));
            if ($w !== '') {
                $warehouseNames[$w] = true;
            }
            $aggregatedStock += (int) ($availabilityRow['stock'] ?? 0);
        }
        if (!empty($warehouseNames)) {
            $warehouse = implode(' + ', array_keys($warehouseNames));
        }
        if ($aggregatedStock <= 0) {
            $aggregatedStock = 1;
        }

        $price = (float) ($searchCode['price'] ?? 0);
        $orderCode = $searchCode['supplierCode'] ?? $query;
        $targetSupBrand = isset($meta['sup_brand']) ? trim((string) $meta['sup_brand']) : '';
        $isBrandTarget = $targetSupBrand !== '';
        $mainManufacturer = $searchCode['manufacturer'] ?? ($isBrandTarget ? $targetSupBrand : ($meta['manufacturer'] ?? null));
        $baseCode = (string) ($meta['base_code'] ?? '');
        if ($baseCode === '') {
            $baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', $query) ?: $query;
        }

        $entryCode = $baseCode;
        if ($isBrandTarget) {
            $entryCode = $baseCode . '|' . $targetSupBrand;
        }

        $entryMfrpn = $entryCode;
        if ($isBrandTarget && !empty($searchCode['supplierCode'])) {
            // Legacy parity: for own-brand targets show supplier code in parent row.
            $entryMfrpn = trim((string) $searchCode['supplierCode']);
        }

        $variants = [];
        if (
            !empty($searchCode['supplierCode']) &&
            (($searchCode['status'] ?? 'In stoc') === 'In stoc') &&
            $availabilityInfoText !== 'Verifica stoc'
        ) {
            if (!$this->brandMatchesTarget($searchCode['manufacturer'] ?? $mainManufacturer, $targetSupBrand)) {
                // For own-brand targets keep only variants that match requested brand.
            } else {
            $variants[] = [
                'supplier_stock' => $aggregatedStock,
                'price' => $price,
                'currency' => 'RON',
                'order_code' => $orderCode,
                'variant_code' => $orderCode,
                'api_lookup_code' => (string) ($searchCode['attCode'] ?? $orderCode),
                'depot' => null,
                'is_blocked' => ($searchCode['blockedOnReturn'] ?? 'NU') === 'DA',
                'delivery' => [
                    'info_text' => $availabilityInfoText,
                    'plant_name' => $warehouse,
                ],
                'multiple_qty' => (int) ($searchCode['minQtyOrd'] ?? 1),
                'blockedOnReturn' => $searchCode['blockedOnReturn'] ?? '',
                'name' => $searchCode['name'] ?? null,
                'manufacturer' => $mainManufacturer,
                'exchangePart' => $searchCode['exchangePart'] ?? '',
                'priceEP' => (float) ($searchCode['priceEP'] ?? 0),
                'is_main_result' => true,
            ];
            }
        }

        $crossRef = $searchCode['crossReference'] ?? [];
        if ($availabilityInfoText !== 'Verifica stoc' && is_array($crossRef)) {
            foreach ($crossRef as $cross) {
                if (($cross['status'] ?? '') !== 'In stoc') {
                    continue;
                }
                $orderCodeCross = $cross['supplierCode'] ?? '';
                if ($orderCodeCross === '') {
                    continue;
                }
                $crossManufacturer = trim($cross['manufacturer'] ?? '') ?: $mainManufacturer;
                if (!$this->brandMatchesTarget($crossManufacturer, $targetSupBrand)) {
                    continue;
                }
                $variants[] = [
                    'supplier_stock' => $aggregatedStock,
                    'price' => (float) ($cross['price'] ?? 0),
                    'currency' => 'RON',
                    'order_code' => $orderCodeCross,
                    'variant_code' => $orderCodeCross,
                    'api_lookup_code' => (string) ($cross['attCode'] ?? $orderCodeCross),
                    'depot' => null,
                    'is_blocked' => ($cross['blockedOnReturn'] ?? 'NU') === 'DA',
                    'delivery' => ['info_text' => $availabilityInfoText, 'plant_name' => $warehouse],
                    'multiple_qty' => 1,
                    'blockedOnReturn' => $cross['blockedOnReturn'] ?? '',
                    'name' => $cross['name'] ?? null,
                    'manufacturer' => $crossManufacturer,
                    'exchangePart' => $searchCode['exchangePart'] ?? '',
                    'priceEP' => (float) ($searchCode['priceEP'] ?? 0),
                    'is_main_result' => false,
                ];
            }
        }

        if (empty($variants)) {
            return [];
        }

        return [[
            'code' => $entryCode,
            'mfrpn' => $entryMfrpn,
            'manufacturer' => $mainManufacturer,
            'db_name' => $meta['db_name'] ?? null,
            'name' => $searchCode['name'] ?? null,
            'ean' => null,
            'material' => null,
            'supplier_name' => 'autototal',
            'variants' => $variants,
        ]];
    }
}
