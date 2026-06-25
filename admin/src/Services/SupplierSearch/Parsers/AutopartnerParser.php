<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Parsers;

use Evasystem\Services\SupplierSearch\PartsCatalogLookup;

final class AutopartnerParser
{
    /** @var array<string, array<string, mixed>> */
    private array $seedMap = [];

    public function __construct(private readonly PartsCatalogLookup $catalogLookup)
    {
    }

    /** @param array<string, array<string, mixed>> $seedMap */
    public function setSeedMap(array $seedMap): void
    {
        $this->seedMap = $seedMap;
    }

    public static function normalizeCode(string $code): string
    {
        return preg_replace('/[.\s\-\/|\\\\]+/', '', trim($code)) ?? '';
    }

    /** @return array<int, array<string, mixed>> */
    public function parse(string $query, mixed $rawResponse, string $rawBody = ''): array
    {
        $data = is_array($rawResponse) ? $rawResponse : (json_decode($rawBody, true) ?: []);
        $availabilityList = $data['RestProductsAvailabilityV2Result']['Availability'] ?? [];
        if (!is_array($availabilityList)) {
            return [];
        }

        $entries = [];
        foreach ($availabilityList as $item) {
            if (!is_array($item)) {
                continue;
            }

            $code = self::normalizeCode((string) ($item['ProductCode'] ?? ''));
            if ($code === '') {
                continue;
            }

            $seed = $this->seedMap[$code] ?? null;
            $dbRow = $seed ? null : $this->catalogLookup->getByCode($code);
            $orderCode = trim((string) ($item['ProductCode'] ?? ''));
            if ($orderCode === '') {
                $orderCode = $code;
            }

            $states = $item['States'] ?? [];
            if (!is_array($states) || $states === []) {
                continue;
            }

            $state = $states[0];
            $departmentCode = (string) ($state['DepartmentCode'] ?? '');

            $entries[] = [
                'code' => $code,
                'mfrpn' => $code,
                'manufacturer' => $query === $code
                    ? '-'
                    : ($seed['manufacturer'] ?? ($dbRow->mainart_brands ?? null)),
                'db_name' => $seed['db_name'] ?? ($dbRow ? ($dbRow->brands ?? null) : null),
                'name' => $item['ProductName'] ?? null,
                'ean' => null,
                'material' => null,
                'supplier_name' => 'autopartner',
                'variants' => [[
                    'supplier_stock' => (int) ($state['InStock'] ?? 0),
                    'price' => (float) ($item['Price'] ?? 0),
                    'currency' => $item['CurrencyCode'] ?? 'RON',
                    'departamentCode' => $departmentCode,
                    'order_code' => $orderCode,
                    'depot' => $departmentCode,
                    'is_blocked' => (bool) ($item['IsBlocked'] ?? false),
                    'delivery' => ['info_text' => '', 'plant_name' => null],
                    'deposit_included' => $item['DepositIncluded'] ?? false,
                    'deposit_price' => $item['DepositPrice'] ?? 0,
                    'multiple_qty' => $item['MultipleQty'] ?? 1,
                ]],
            ];
        }

        return $entries;
    }
}
