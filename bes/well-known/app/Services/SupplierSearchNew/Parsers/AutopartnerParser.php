<?php

namespace App\Services\SupplierSearchNew\Parsers;

use App\Services\SupplierSearchNew\Contracts\SupplierParserInterface;
use App\Services\SupplierSearchNew\PartsCatalogLookup;
use App\Services\AutoPartner\AutoPartnerService;

class AutopartnerParser implements SupplierParserInterface
{
    /** @var array<string, array{manufacturer: mixed, db_name: mixed, order_code: mixed}> */
    protected array $seedMap = [];

    public function __construct(
        protected AutoPartnerService $autoPartnerService,
        protected PartsCatalogLookup $partsCatalogLookup
    ) {}

    public function setSeedMap(array $seedMap): void
    {
        $this->seedMap = $seedMap;
    }

    public function parse(string $query, $rawResponse, string $rawBody): array
    {
        $data = is_array($rawResponse) ? $rawResponse : (json_decode($rawBody, true) ?: []);
        $availabilityList = $data['RestProductsAvailabilityV2Result']['Availability'] ?? [];
        if (!is_array($availabilityList)) {
            return [];
        }

        $entries = [];
        foreach ($availabilityList as $item) {
            $apiCode = $item['ProductCode'] ?? '';
            $normalizedFromApi = $this->autoPartnerService->normalizeCode($apiCode);
            $code = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $normalizedFromApi);
            if ($code === '') {
                continue;
            }

            $seed = $this->seedMap[$code] ?? null;
            $dbRow = $seed ? null : $this->partsCatalogLookup->getByCode($code);
            $catalogOrderCode = $code;
            if ($seed && !empty($seed['order_code'])) {
                $catalogOrderCode = (string) $seed['order_code'];
            } elseif ($dbRow && !empty($dbRow->mainart_code_parts)) {
                $catalogOrderCode = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $dbRow->mainart_code_parts);
                if ($catalogOrderCode === '') {
                    $catalogOrderCode = $code;
                }
            }

            // Use API ProductCode for cart / wishlist / availability (keeps hyphens e.g. 82-1205).
            $apiProductCode = trim((string) ($item['ProductCode'] ?? ''));
            $orderCode = $apiProductCode !== '' ? $apiProductCode : $catalogOrderCode;

            $states = $item['States'] ?? [];
            if (empty($states)) {
                continue;
            }
            $state = $states[0];
            $departmentCode = $state['DepartmentCode'] ?? '';
            if ($departmentCode === 'CN') {
                $departmentCode = 'maine 8:00';
            } elseif ($departmentCode === '120' || $departmentCode === '72') {
                $departmentCode = 'poimaine 8:00';
            }

            $variant = [
                'supplier_stock' => (int) ($state['InStock'] ?? 0),
                'price' => (float) ($item['Price'] ?? 0),
                'currency' => $item['CurrencyCode'] ?? 'RON',
                'departamentCode' => $state['DepartmentCode'] ?? '',
                'order_code' => $orderCode,
                'depot' => $departmentCode,
                'is_blocked' => (bool) ($item['IsBlocked'] ?? false),
                'delivery' => ['info_text' => '', 'plant_name' => null],
                'deposit_included' => $item['DepositIncluded'] ?? false,
                'deposit_price' => $item['DepositPrice'] ?? 0,
                'possible_return' => $item['PossibleReturn'] ?? true,
                'multiple_qty' => $item['MultipleQty'] ?? 1,
                'PossibleReturn' => (bool) ($item['PossibleReturn'] ?? false),
            ];

            $name = $item['ProductName'] ?? null;
            if ($query === $code) {
                $name = 'null';
            }

            $entries[] = [
                'code' => $code,
                'mfrpn' => $code,
                'manufacturer' => $query === $code
                    ? '-'
                    : ($seed['manufacturer'] ?? ($dbRow->mainart_brands ?? null)),
                'db_name' => $seed['db_name'] ?? ($dbRow ? ($dbRow->mainart_name ?? null) : null),
                'name' => $name,
                'ean' => null,
                'material' => null,
                'supplier_name' => 'autopartner',
                'variants' => [$variant],
            ];
        }

        return $entries;
    }
}
