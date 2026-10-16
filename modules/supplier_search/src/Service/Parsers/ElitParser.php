<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Parsers;

use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;
use PDO;

final class ElitParser
{
    public function __construct(private readonly ?PDO $pdo)
    {
    }

    public static function normalizeCode(string $code): string
    {
        return preg_replace('/[.\s\-\/|\\\\]+/', '', trim($code)) ?? '';
    }

    /**
     * Parsează răspunsul live de la ElitClient (sau folosește fallback din lkq_prices dacă liveResponse este gol).
     *
     * @param array{products?:array<int,array<string,mixed>>,prices?:array<string,array<string,mixed>>,disponibility?:array<string,array<string,mixed>>}|null $liveData
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $query, ?array $liveData = null): array
    {
        if (is_array($liveData) && !empty($liveData['products'])) {
            $liveEntries = $this->parseLive($query, $liveData);
            if ($liveEntries !== []) {
                return $liveEntries;
            }
        }

        return $this->parseLegacyFallback($query);
    }

    /**
     * @param array{products?:array<int,array<string,mixed>>,prices?:array<string,array<string,mixed>>,disponibility?:array<string,array<string,mixed>>} $liveData
     * @return array<int, array<string, mixed>>
     */
    private function parseLive(string $query, array $liveData): array
    {
        $products = is_array($liveData['products'] ?? null) ? $liveData['products'] : [];
        $prices = is_array($liveData['prices'] ?? null) ? $liveData['prices'] : [];
        $disponibility = is_array($liveData['disponibility'] ?? null) ? $liveData['disponibility'] : [];

        $lkqMetaByItemNr = $this->lookupLkqMetaForProducts($products);
        $entriesByCode = [];

        foreach ($products as $prod) {
            if (!is_array($prod)) {
                continue;
            }

            $id = trim((string) ($prod['Id'] ?? ''));
            $orderCode = trim((string) ($prod['Code'] ?? $id));
            if ($id === '' && $orderCode === '') {
                continue;
            }

            $refCode = trim((string) ($prod['ReferenceCode'] ?? ''));
            $rawMfrpn = $refCode !== '' ? $refCode : $orderCode;
            $baseCode = self::normalizeCode($rawMfrpn);
            if ($baseCode === '') {
                $baseCode = self::normalizeCode($orderCode);
            }
            if ($baseCode === '') {
                continue;
            }

            $lkqRow = $lkqMetaByItemNr[$orderCode] ?? null;

            // Prețul net (din UserPrice/Data sau fallback din lkq_prices)
            $priceNet = 0.0;
            if ($id !== '' && isset($prices[$id])) {
                $priceNet = $this->parseElitPriceString((string) ($prices[$id]['Price'] ?? ''));
            }
            if ($priceNet <= 0 && $lkqRow !== null) {
                $priceNet = (float) ($lkqRow->net_price ?? 0);
            }
            if ($priceNet <= 0) {
                continue;
            }

            // Disponibilitatea live (din ProductDisponibility/data)
            $disp = ($id !== '' && isset($disponibility[$id]) && is_array($disponibility[$id]))
                ? $disponibility[$id]
                : null;

            $stock = 0;
            $deliveryText = '';
            if ($disp !== null) {
                $isCanceled = !empty($disp['IsCanceled']);
                $amount = (int) ($disp['Amount'] ?? 0);
                $text = trim((string) ($disp['Text'] ?? ''));
                if (!$isCanceled && $amount > 0) {
                    $stock = $amount;
                    $deliveryText = $text !== '' ? $text : 'Azi';
                }
            }

            // Dacă avem date live de disponibilitate pentru sesiune și acest produs are stoc 0, îl sărim
            if ($disponibility !== [] && $stock <= 0) {
                continue;
            }
            if ($stock <= 0) {
                $stock = 1;
                $deliveryText = 'Verifica stoc';
            }

            [$parsedName, $parsedBrand] = $this->splitNameAndBrand((string) ($prod['Name'] ?? ''), $lkqRow);

            if (!isset($entriesByCode[$baseCode])) {
                $entriesByCode[$baseCode] = [
                    'code' => $baseCode,
                    'mfrpn' => $baseCode,
                    'manufacturer' => $parsedBrand,
                    'db_name' => $lkqRow->description_ro ?? null,
                    'name' => $parsedName,
                    'ean' => null,
                    'material' => $orderCode,
                    'supplier_name' => 'elit',
                    'variants' => [],
                ];
            } else {
                if (empty($entriesByCode[$baseCode]['manufacturer']) && $parsedBrand !== null) {
                    $entriesByCode[$baseCode]['manufacturer'] = $parsedBrand;
                }
                if (empty($entriesByCode[$baseCode]['name']) && $parsedName !== null) {
                    $entriesByCode[$baseCode]['name'] = $parsedName;
                }
            }

            $plantName = $this->inferPlantFromDeliveryText($deliveryText);

            $entriesByCode[$baseCode]['variants'][] = [
                'supplier_stock' => $stock,
                'price' => $priceNet,
                'currency' => 'RON',
                'order_code' => $orderCode,
                'variant_code' => $orderCode,
                'api_lookup_code' => $rawMfrpn,
                'depot' => 'ELIT',
                'is_blocked' => false,
                'delivery' => [
                    'info_text' => $deliveryText,
                    'plant_name' => $plantName,
                ],
                'multiple_qty' => 1,
            ];
        }

        return array_values(array_filter(
            $entriesByCode,
            static fn (array $entry): bool => !empty($entry['variants'])
        ));
    }

    private function parseElitPriceString(string $raw): float
    {
        $clean = preg_replace('/[^0-9,.\-]/', '', $raw) ?? '';
        if ($clean === '') {
            return 0.0;
        }
        // Format românesc: "1.234,56" sau "303,36"
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return (float) $clean;
    }

    /**
     * @return array{0:?string,1:?string} [name, brand]
     */
    private function splitNameAndBrand(string $fullName, ?object $lkqRow): array
    {
        $fullName = trim($fullName);
        $lkqBrand = $lkqRow !== null ? trim((string) ($lkqRow->brand_name ?? '')) : '';
        $lkqDesc = $lkqRow !== null ? trim((string) ($lkqRow->description_ro ?? '')) : '';

        if ($lkqBrand !== '') {
            $brand = strtoupper($lkqBrand);
            if ($brand === 'DEPO' || $brand === 'LORO') {
                $brand = 'ABAKUS';
            }
            $name = $lkqDesc !== '' ? $lkqDesc : $fullName;
            return [$name !== '' ? $name : null, $brand];
        }

        if ($fullName === '') {
            return [null, null];
        }

        // În Elit, Name este de forma "set placute frana ATE" sau "etrier frana BOSCH"
        $multiWordBrands = [
            'FEBI BILSTEIN', 'MANN-FILTER', 'MANN FILTER', 'BLUE PRINT', 'HERTH+BUSS JAKOPARTS',
            'HERTH+BUSS ELPARTS', 'VICTOR REINZ', 'CONTINENTAL CTAM', 'CONTITECH', 'MAGNETI MARELLI',
            'MEAT & DORIA', 'UFI FILTERS', 'PURFLUX', 'KOLBENSCHMIDT', 'PIERBURG', 'LEMFÖRDER', 'LEMFORDER',
        ];
        $upperFull = function_exists('mb_strtoupper') ? mb_strtoupper($fullName, 'UTF-8') : strtoupper($fullName);
        foreach ($multiWordBrands as $mwBrand) {
            if (str_ends_with($upperFull, ' ' . $mwBrand)) {
                $cutLen = strlen($fullName) - strlen($mwBrand);
                $namePart = trim(substr($fullName, 0, $cutLen));
                return [$namePart !== '' ? $namePart : $fullName, $mwBrand];
            }
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        if (count($parts) >= 2) {
            $lastToken = (string) array_pop($parts);
            $upperLast = strtoupper($lastToken);
            if ($upperLast === 'DEPO' || $upperLast === 'LORO') {
                $upperLast = 'ABAKUS';
            }
            $namePart = trim(implode(' ', $parts));
            return [$namePart !== '' ? $namePart : $fullName, $upperLast];
        }

        return [$fullName, null];
    }

    private function inferPlantFromDeliveryText(string $deliveryText): string
    {
        $lower = function_exists('mb_strtolower') ? mb_strtolower($deliveryText, 'UTF-8') : strtolower($deliveryText);
        if (str_contains($lower, 'astăzi') || str_contains($lower, 'astazi') || str_contains($lower, 'azi')) {
            return 'Timișoara';
        }
        if (str_contains($lower, 'mâine') || str_contains($lower, 'maine')) {
            return 'Depozit Central';
        }

        return 'ELIT';
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<string, object>
     */
    private function lookupLkqMetaForProducts(array $products): array
    {
        if ($this->pdo === null || $products === []) {
            return [];
        }

        $codes = [];
        foreach ($products as $prod) {
            if (!is_array($prod)) {
                continue;
            }
            $code = trim((string) ($prod['Code'] ?? ''));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        if ($codes === []) {
            return [];
        }

        try {
            $codeList = array_keys($codes);
            $placeholders = implode(',', array_fill(0, count($codeList), '?'));
            $stmt = $this->pdo->prepare("SELECT item_nr, supplier_catalog_nr, brand_name, description_ro, net_price FROM lkq_prices WHERE item_nr IN ({$placeholders})");
            $stmt->execute($codeList);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
            $map = [];
            foreach ($rows as $row) {
                $itemNr = trim((string) ($row->item_nr ?? ''));
                if ($itemNr !== '') {
                    $map[$itemNr] = $row;
                }
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function parseLegacyFallback(string $query): array
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
             FROM parts_catalog WHERE code_parts = ?
             UNION
             SELECT code_parts, mainart_code_parts, mainart_brands, brands
             FROM parts_catalog WHERE mainart_code_parts = ?
             LIMIT 80'
        );
        $partsStmt->execute([$normalizedQuery, $normalizedQuery]);
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
            'delivery' => ['info_text' => 'Verifica stoc', 'plant_name' => null],
            'multiple_qty' => 1,
        ];
    }
}
