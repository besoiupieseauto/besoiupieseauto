<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Parsers;

final class IntercarsParser
{
    public static function normalizeCode(string $code): string
    {
        return preg_replace('/[.\s\-\/|\\\\]+/', '', trim($code)) ?? '';
    }

    /**
     * @param array{products?:array<int,array<string,mixed>>,prices?:array<string,array<string,mixed>>,availability?:array<string,array<string,mixed>>}|mixed $rawResponse
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $query, mixed $rawResponse): array
    {
        if (!is_array($rawResponse)) {
            return [];
        }

        $products = is_array($rawResponse['products'] ?? null) ? $rawResponse['products'] : [];
        $prices = is_array($rawResponse['prices'] ?? null) ? $rawResponse['prices'] : [];
        $availability = is_array($rawResponse['availability'] ?? null) ? $rawResponse['availability'] : [];

        $entries = [];

        foreach ($products as $prod) {
            if (!is_array($prod)) {
                continue;
            }

            $towKod = trim((string) ($prod['towKod'] ?? ''));
            if ($towKod === '') {
                continue;
            }

            $rawMfrpn = trim((string) ($prod['manufacturerNumber'] ?? $prod['index'] ?? ''));
            if ($rawMfrpn === '') {
                $rawMfrpn = $towKod;
            }

            $baseCode = self::normalizeCode($rawMfrpn);
            if ($baseCode === '') {
                $baseCode = self::normalizeCode($towKod);
            }
            if ($baseCode === '') {
                continue;
            }

            $manufacturer = trim((string) ($prod['brandName'] ?? $prod['manufacturerName'] ?? ''));
            $upperBrand = strtoupper($manufacturer);
            if ($upperBrand === 'DEPO' || $upperBrand === 'LORO') {
                $manufacturer = 'ABAKUS';
            }

            $name = trim((string) ($prod['nameB2B'] ?? ''));
            if ($name === '' && is_array($prod['categoryLeaves'] ?? null) && isset($prod['categoryLeaves'][0]['name'])) {
                $name = trim((string) $prod['categoryLeaves'][0]['name']);
            }
            if ($name === '') {
                $name = trim((string) ($prod['technicalDescription'] ?? ''));
            }

            $priceRow = is_array($prices[$towKod] ?? null) ? $prices[$towKod] : [];
            $wholesaleNet = (float) ($priceRow['wholesalePrice']['net'] ?? 0);
            if ($wholesaleNet <= 0) {
                continue;
            }

            $currency = trim((string) ($priceRow['currencyCode'] ?? 'RON')) ?: 'RON';
            $coreNet = (float) ($priceRow['corePrice']['net'] ?? 0);

            $availRow = is_array($availability[$towKod] ?? null) ? $availability[$towKod] : [];
            $variants = $this->buildVariantsForProduct($towKod, $rawMfrpn, $wholesaleNet, $currency, $coreNet, $availRow);

            if ($variants === []) {
                continue;
            }

            $entries[] = [
                'code' => $baseCode,
                'mfrpn' => $baseCode,
                'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
                'db_name' => null,
                'name' => $name !== '' ? $name : null,
                'ean' => null,
                'material' => $towKod,
                'supplier_name' => 'intercars',
                'variants' => $variants,
            ];
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $availRow
     * @return array<int, array<string, mixed>>
     */
    private function buildVariantsForProduct(
        string $towKod,
        string $rawMfrpn,
        float $priceNet,
        string $currency,
        float $coreNet,
        array $availRow
    ): array {
        $lines = $availRow['deliveryTime']['deliveryTimeLines'] ?? [];
        $branchName = trim((string) ($availRow['selectedStocks']['branchName'] ?? 'R86')) ?: 'R86';
        $branchStock = (int) ($availRow['selectedStocks']['stockLevelInCurrentBranch'] ?? 0);

        $variants = [];

        if (is_array($lines) && $lines !== []) {
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $qty = (int) ($line['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $term = strtoupper(trim((string) ($line['deliveryTerm'] ?? '')));
                $displayTime = trim((string) ($line['displayDeliveryTime'] ?? ''));

                [$infoText, $plantName, $depot] = $this->formatDeliveryTerm($term, $displayTime, $branchName);

                $variants[] = [
                    'supplier_stock' => $qty,
                    'price' => $priceNet,
                    'currency' => $currency,
                    'order_code' => $towKod,
                    'variant_code' => $towKod . ($term !== '' ? ':' . $term : ''),
                    'api_lookup_code' => $rawMfrpn,
                    'depot' => $depot,
                    'is_blocked' => false,
                    'delivery' => [
                        'info_text' => $infoText,
                        'plant_name' => $plantName,
                    ],
                    'deposit_included' => false,
                    'deposit_price' => $coreNet,
                    'multiple_qty' => 1,
                ];
            }
        }

        if ($variants === [] && $branchStock > 0) {
            $variants[] = [
                'supplier_stock' => $branchStock,
                'price' => $priceNet,
                'currency' => $currency,
                'order_code' => $towKod,
                'variant_code' => $towKod,
                'api_lookup_code' => $rawMfrpn,
                'depot' => $branchName,
                'is_blocked' => false,
                'delivery' => [
                    'info_text' => 'Azi',
                    'plant_name' => 'Timișoara (' . $branchName . ')',
                ],
                'deposit_included' => false,
                'deposit_price' => $coreNet,
                'multiple_qty' => 1,
            ];
        }

        return $variants;
    }

    /**
     * @return array{0:string,1:string,2:string} [infoText, plantName, depot]
     */
    private function formatDeliveryTerm(string $term, string $displayTime, string $branchName): array
    {
        $timeSuffix = $displayTime !== '' ? ' ' . $displayTime : '';

        return match ($term) {
            'TODAY_IN_BRANCH' => [
                'Azi' . $timeSuffix,
                'Timișoara (' . $branchName . ')',
                $branchName . ' TM',
            ],
            'TODAY' => [
                'Azi' . $timeSuffix,
                'Depozit IC',
                'IC RO',
            ],
            'TOMORROW', 'BUSINESS_DAYS_1' => [
                'Maine' . $timeSuffix,
                'Depozit IC',
                'IC RO',
            ],
            'BUSINESS_DAYS_2' => [
                '2 zile' . $timeSuffix,
                'Depozit IC',
                'IC EXT',
            ],
            'BUSINESS_DAYS_3' => [
                '3 zile' . $timeSuffix,
                'Depozit extern IC',
                'IC EXT',
            ],
            default => [
                preg_match('/BUSINESS_DAYS_(\d+)/', $term, $m)
                    ? $m[1] . ' zile' . $timeSuffix
                    : ($displayTime !== '' ? 'Azi ' . $displayTime : 'Verifica stoc'),
                'Depozit IC',
                'IC',
            ],
        };
    }
}
