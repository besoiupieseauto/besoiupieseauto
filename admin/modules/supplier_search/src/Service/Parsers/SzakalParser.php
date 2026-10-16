<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Parsers;

/**
 * Parser pentru răspunsurile Szakál Metál Webservice (/product/* + /stock_info).
 */
final class SzakalParser
{
    public static function normalizeCode(string $code): string
    {
        return preg_replace('/[.\s\-\/|\\\\]+/', '', trim($code)) ?? '';
    }

    public static function stripSupplierSuffix(string $szakalCode): string
    {
        $trimmed = trim($szakalCode);
        if ($trimmed === '') {
            return '';
        }

        $stripped = preg_replace('/[_\/][A-Za-z0-9]+(?:\/[A-Za-z0-9]+)?$/', '', $trimmed);

        return is_string($stripped) && $stripped !== '' ? $stripped : $trimmed;
    }

    /**
     * @param array{products?:array<int,array<string,mixed>>,stocks?:array<string,array<int,array<string,mixed>>>}|mixed $rawResponse
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $query, mixed $rawResponse): array
    {
        if (!is_array($rawResponse)) {
            return [];
        }

        $products = is_array($rawResponse['products'] ?? null) ? $rawResponse['products'] : [];
        $stocksMap = is_array($rawResponse['stocks'] ?? null) ? $rawResponse['stocks'] : [];

        if ($products === [] || $stocksMap === []) {
            return [];
        }

        $entries = [];

        foreach ($products as $prod) {
            if (!is_array($prod)) {
                continue;
            }

            $glid = trim((string) ($prod['glid'] ?? ''));
            if ($glid === '' || empty($stocksMap[$glid]) || !is_array($stocksMap[$glid])) {
                continue;
            }

            $szakalCode = trim((string) ($prod['code'] ?? ''));
            $artnr = trim((string) ($prod['artnr'] ?? ''));
            $targetBaseCode = trim((string) ($prod['_target_base_code'] ?? ''));

            $rawMfrpn = $artnr !== ''
                ? $artnr
                : ($targetBaseCode !== '' ? $targetBaseCode : self::stripSupplierSuffix($szakalCode));

            $baseCode = self::normalizeCode($rawMfrpn);
            if ($baseCode === '') {
                $baseCode = self::normalizeCode($szakalCode);
            }
            if ($baseCode === '') {
                continue;
            }

            $manufacturer = trim((string) ($prod['maker'] ?? $prod['_target_brand'] ?? ''));
            $manufacturer = $this->normalizeBrandDisplay($manufacturer);

            $name = trim((string) ($prod['name'] ?? ''));
            $dbName = trim((string) ($prod['_db_name'] ?? ''));

            $variants = $this->buildVariantsForStocks(
                $glid,
                $szakalCode !== '' ? $szakalCode : $baseCode,
                $stocksMap[$glid]
            );

            if ($variants === []) {
                continue;
            }

            $entries[] = [
                'code' => $baseCode,
                'mfrpn' => $baseCode,
                'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
                'db_name' => $dbName !== '' ? $dbName : null,
                'name' => $name !== '' ? $name : null,
                'ean' => null,
                'material' => $szakalCode !== '' ? $szakalCode : $glid,
                'supplier_name' => 'szakal',
                'variants' => $variants,
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, array<string, mixed>> $stocks
     * @return array<int, array<string, mixed>>
     */
    private function buildVariantsForStocks(string $glid, string $szakalCode, array $stocks): array
    {
        $variants = [];

        foreach (array_values($stocks) as $index => $stock) {
            if (!is_array($stock)) {
                continue;
            }

            $qty = (int) ($stock['quantity'] ?? 0);
            $unitPrice = (float) ($stock['unit_price'] ?? 0);
            if ($qty <= 0 || $unitPrice <= 0) {
                continue;
            }

            $token = trim((string) ($stock['stock_order_token'] ?? ''));
            $expectedDelivery = trim((string) ($stock['expected_delivery'] ?? ''));
            $noReturnable = !empty($stock['no_returnable']);
            $coreCharge = (float) ($stock['core_charge'] ?? 0);
            $quantum = max(1, (int) ($stock['order_quantum'] ?? $stock['moq'] ?? 1));

            [$infoText, $plantName, $depot] = $this->formatExpectedDelivery($expectedDelivery, $noReturnable);

            $variantCode = $szakalCode . ($index > 0 ? '#' . ($index + 1) : '');

            $variants[] = [
                'supplier_stock' => $qty,
                'stock' => $qty,
                'price' => $unitPrice,
                'currency' => 'RON',
                'order_code' => $variantCode,
                'variant_code' => $variantCode,
                'api_lookup_code' => $glid,
                'szakal_glid' => $glid,
                'szakal_token' => $token,
                'depot' => $depot,
                'is_blocked' => $noReturnable,
                'delivery' => [
                    'info_text' => $infoText,
                    'plant_name' => $plantName,
                    'expected_delivery' => $expectedDelivery,
                ],
                'deposit_included' => false,
                'deposit_price' => $coreCharge,
                'multiple_qty' => $quantum,
            ];
        }

        return $variants;
    }

    /**
     * @return array{0:string,1:string,2:string} [infoText, plantName, depot]
     */
    private function formatExpectedDelivery(string $expectedDelivery, bool $noReturnable): array
    {
        $depot = $noReturnable ? 'Szakal (Nerambursabil)' : 'Szakal';
        $plantName = $depot;

        if ($expectedDelivery === '') {
            return ['Verifica stoc', $plantName, $depot];
        }

        try {
            $tz = new \DateTimeZone('Europe/Bucharest');
            $dt = new \DateTime($expectedDelivery);
            $dt->setTimezone($tz);

            $timeStr = $dt->format('H:i');
            $dateStr = $dt->format('d.m');

            $today = new \DateTime('today', $tz);
            $deliveryDay = (clone $dt)->setTime(0, 0, 0);
            $daysDiff = (int) $today->diff($deliveryDay)->format('%r%a');

            if ($daysDiff <= 0) {
                return ['Azi ' . $timeStr, $plantName, $depot];
            }
            if ($daysDiff === 1) {
                return ['Maine ' . $timeStr, $plantName, $depot];
            }

            return [$daysDiff . ' zile (' . $dateStr . ' ' . $timeStr . ')', $plantName, $depot];
        } catch (\Throwable) {
            return [$expectedDelivery, $plantName, $depot];
        }
    }

    private function normalizeBrandDisplay(string $brand): string
    {
        $upper = strtoupper(trim($brand));
        if (in_array($upper, ['DEPO', 'LORO', 'LORO TUNING', 'ABAKUS-DEPO', 'DEP', 'LOR', 'ABA'], true)) {
            return 'ABAKUS';
        }

        return trim($brand);
    }
}
