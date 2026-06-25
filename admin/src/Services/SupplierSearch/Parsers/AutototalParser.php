<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Parsers;

final class AutototalParser
{
    /** @return array<int, array<string, mixed>> */
    public function parse(string $query, mixed $rawResponse, string $rawBody = ''): array
    {
        $data = is_array($rawResponse) ? $rawResponse : (json_decode($rawBody, true) ?: []);
        if (isset($data[0]['response']) && isset($data[0]['meta'])) {
            return $this->parseAggregatedResponses($query, $data);
        }

        return $this->parseSingleResponse($query, is_array($data) ? $data : [], [
            'base_code' => preg_replace('/[.\s\-\/|\\\\]+/', '', $query) ?: $query,
            'manufacturer' => null,
            'db_name' => null,
            'sup_brand' => null,
        ]);
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
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
            $parsedList = [];
            if (is_array($targets) && $targets !== []) {
                foreach ($targets as $target) {
                    if (!is_array($target)) {
                        continue;
                    }
                    $parsedList = array_merge($parsedList, $this->parseSingleResponse($query, $response, $target));
                }
            } else {
                $parsedList = $this->parseSingleResponse($query, $response, $meta);
            }

            foreach ($parsedList as $entry) {
                $code = (string) ($entry['code'] ?? '');
                if ($code === '') {
                    continue;
                }
                if (!isset($entries[$code])) {
                    $entries[$code] = $entry;
                    $entries[$code]['variants'] = [];
                } else {
                    foreach (['manufacturer', 'db_name', 'name'] as $field) {
                        if (empty($entries[$code][$field]) && !empty($entry[$field])) {
                            $entries[$code][$field] = $entry[$field];
                        }
                    }
                }

                foreach (($entry['variants'] ?? []) as $variant) {
                    $vKey = $code . '|' . (string) ($variant['order_code'] ?? '') . '|' . (string) ($variant['price'] ?? '');
                    if (isset($seenVariants[$vKey])) {
                        continue;
                    }
                    $seenVariants[$vKey] = true;
                    $entries[$code]['variants'][] = $variant;
                }
            }
        }

        return array_values(array_filter($entries, static fn (array $entry) => !empty($entry['variants'])));
    }

    /** @param array<string, mixed> $meta @return array<int, array<string, mixed>> */
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
        $availabilityInfoText = $availabilityList[0]['arrivesAt'] ?? 'Verifica stoc';
        $warehouse = null;
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
        if ($warehouseNames !== []) {
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

        $entryCode = $isBrandTarget ? $baseCode . '|' . $targetSupBrand : $baseCode;
        $entryMfrpn = ($isBrandTarget && !empty($searchCode['supplierCode']))
            ? trim((string) $searchCode['supplierCode'])
            : $entryCode;

        $variants = [];
        if (
            !empty($searchCode['supplierCode']) &&
            (($searchCode['status'] ?? 'In stoc') === 'In stoc') &&
            $availabilityInfoText !== 'Verifica stoc' &&
            $this->brandMatchesTarget($searchCode['manufacturer'] ?? $mainManufacturer, $targetSupBrand)
        ) {
            $variants[] = $this->buildVariant($searchCode, $orderCode, $aggregatedStock, $price, $availabilityInfoText, $warehouse, $mainManufacturer, true);
        }

        if ($availabilityInfoText !== 'Verifica stoc' && is_array($searchCode['crossReference'] ?? null)) {
            foreach ($searchCode['crossReference'] as $cross) {
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
                $variants[] = $this->buildVariant($cross, $orderCodeCross, $aggregatedStock, (float) ($cross['price'] ?? 0), $availabilityInfoText, $warehouse, $crossManufacturer, false, $searchCode);
            }
        }

        if ($variants === []) {
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

    /** @param array<string, mixed> $row @param array<string, mixed>|null $mainSearchCode */
    private function buildVariant(
        array $row,
        string $orderCode,
        int $stock,
        float $price,
        string $availabilityInfoText,
        ?string $warehouse,
        ?string $manufacturer,
        bool $isMain,
        ?array $mainSearchCode = null
    ): array {
        return [
            'supplier_stock' => $stock,
            'price' => $price,
            'currency' => 'RON',
            'order_code' => $orderCode,
            'variant_code' => $orderCode,
            'api_lookup_code' => (string) ($row['attCode'] ?? $orderCode),
            'depot' => null,
            'is_blocked' => ($row['blockedOnReturn'] ?? 'NU') === 'DA',
            'delivery' => ['info_text' => $availabilityInfoText, 'plant_name' => $warehouse],
            'multiple_qty' => (int) ($row['minQtyOrd'] ?? 1),
            'blockedOnReturn' => $row['blockedOnReturn'] ?? '',
            'name' => $row['name'] ?? null,
            'manufacturer' => $manufacturer,
            'exchangePart' => $mainSearchCode['exchangePart'] ?? ($row['exchangePart'] ?? ''),
            'priceEP' => (float) ($mainSearchCode['priceEP'] ?? ($row['priceEP'] ?? 0)),
            'is_main_result' => $isMain,
        ];
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

    private function normalizeBrand(?string $brand): string
    {
        $brand = strtoupper(trim((string) $brand));
        if ($brand === '') {
            return '';
        }

        return preg_replace('/[^A-Z0-9]/', '', $brand) ?? '';
    }
}
