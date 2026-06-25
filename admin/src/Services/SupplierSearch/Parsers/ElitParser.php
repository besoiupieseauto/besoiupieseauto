<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Parsers;

use Evasystem\Services\SupplierSearch\SupplierSearchConfig;
use PDO;

final class ElitParser
{
    public function __construct(private readonly ?PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function parse(string $query): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $entriesByCode = [];
        $normalizedQuery = SupplierSearchConfig::normalizeQuery($query);
        if ($normalizedQuery === '') {
            return [];
        }

        $ensureEntry = function (string $code, ?string $manufacturer = null, ?string $dbName = null) use (&$entriesByCode): void {
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

            if (empty($entriesByCode[$code]['manufacturer']) && $manufacturer) {
                $entriesByCode[$code]['manufacturer'] = $manufacturer;
            }
            if (empty($entriesByCode[$code]['db_name']) && $dbName) {
                $entriesByCode[$code]['db_name'] = $dbName;
            }
        };

        $directStmt = $this->pdo->prepare('SELECT * FROM lkq_prices WHERE supplier_catalog_nr = ?');
        $directStmt->execute([$query]);
        $directRows = $directStmt->fetchAll(PDO::FETCH_OBJ) ?: [];

        if ($directRows !== []) {
            $first = $directRows[0];
            $ensureEntry($normalizedQuery, $first->brand_name ?? null, $first->description_ro ?? null);
            foreach ($directRows as $row) {
                $entriesByCode[$normalizedQuery]['variants'][] = $this->buildVariant(
                    (string) ($row->item_nr ?? ''),
                    (float) ($row->net_price ?? 0)
                );
            }
        }

        $partsStmt = $this->pdo->prepare(
            'SELECT code_parts, mainart_code_parts, mainart_brands, brands
             FROM parts_catalog
             WHERE REPLACE(REPLACE(REPLACE(code_parts, " ", ""), "-", ""), ".", "") = ?'
        );
        $partsStmt->execute([$normalizedQuery]);
        $partsRows = $partsStmt->fetchAll(PDO::FETCH_OBJ) ?: [];

        $partsMeta = [];
        $supplierCatalogNrs = [];
        $brands = [];

        foreach ($partsRows as $row) {
            $mainart = (string) ($row->mainart_code_parts ?? '');
            $baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainart) ?? '';
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

        if ($partsMeta !== []) {
            $catalogNrs = array_keys($supplierCatalogNrs);
            $brandList = array_keys($brands);
            $placeholdersCat = implode(',', array_fill(0, count($catalogNrs), '?'));
            $placeholdersBrand = implode(',', array_fill(0, count($brandList), '?'));

            $lkqStmt = $this->pdo->prepare(
                "SELECT * FROM lkq_prices
                 WHERE supplier_catalog_nr IN ({$placeholdersCat})
                   AND brand_name IN ({$placeholdersBrand})"
            );
            $lkqStmt->execute(array_merge($catalogNrs, $brandList));
            $lkqRows = $lkqStmt->fetchAll(PDO::FETCH_OBJ) ?: [];

            $lkqByPair = [];
            foreach ($lkqRows as $lkqRow) {
                $pairKey = (string) ($lkqRow->supplier_catalog_nr ?? '') . '|' . (string) ($lkqRow->brand_name ?? '');
                $lkqByPair[$pairKey][] = $lkqRow;
            }

            foreach ($partsMeta as $pairKey => $meta) {
                $baseCode = $meta['base_code'];
                $elitRows = $lkqByPair[$pairKey] ?? [];
                $lkqDescription = !empty($elitRows) ? ($elitRows[0]->description_ro ?? null) : null;
                $ensureEntry($baseCode, $meta['brand'], $lkqDescription ?? ($meta['fallback_name'] ?? null));

                foreach ($elitRows as $elitRow) {
                    $entriesByCode[$baseCode]['variants'][] = $this->buildVariant(
                        (string) ($elitRow->item_nr ?? ''),
                        (float) ($elitRow->net_price ?? 0)
                    );
                }
            }
        }

        return array_values(array_filter($entriesByCode, static fn (array $entry): bool => !empty($entry['variants'])));
    }

    /** @return array<string, mixed> */
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
