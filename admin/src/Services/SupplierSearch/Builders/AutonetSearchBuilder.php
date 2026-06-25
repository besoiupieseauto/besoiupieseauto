<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Builders;

use Evasystem\Services\SupplierSearch\Clients\AutonetClient;
use Evasystem\Services\SupplierSearch\PartsCatalogLookup;
use PDO;

final class AutonetSearchBuilder
{
    /** @param array<int, object> $rows @return array<int, array<string, mixed>> */
    public function buildItems(string $query, string $rawQuery, array $rows, ?PDO $pdo): array
    {
        $items = [];
        $seen = [];
        $refNrs = [];
        $refBrands = [];
        $validPairs = [];
        $fallbackBrandIds = $this->loadFallbackBrandIds($rows, $pdo);

        foreach ($rows as $row) {
            if (!empty($row->brand_id)) {
                $key = 'td:' . (string) $row->brand_id . '|' . (string) ($row->mainart_code_parts ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $items[] = [
                    'TDBrandId' => $row->brand_id,
                    'TDArticleNo' => $row->mainart_code_parts,
                    'Quantity' => 2,
                ];
            } else {
                $brandName = (string) ($row->mainart_brands ?? '');
                $fallbackBrandId = $brandName !== '' ? ($fallbackBrandIds[$brandName] ?? null) : null;
                if ($fallbackBrandId !== null && $fallbackBrandId !== '') {
                    $tdKey = 'td:' . (string) $fallbackBrandId . '|' . (string) ($row->mainart_code_parts ?? '');
                    if (!isset($seen[$tdKey])) {
                        $seen[$tdKey] = true;
                        $items[] = [
                            'TDBrandId' => $fallbackBrandId,
                            'TDArticleNo' => $row->mainart_code_parts,
                            'Quantity' => 2,
                        ];
                    }
                }

                $partNo = (string) ($row->mainart_code_parts ?? '');
                if ($partNo !== '') {
                    $key = 'part:' . $partNo;
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $items[] = ['PartNo' => $partNo, 'Quantity' => 2];
                    }
                }

                $codeForRule = preg_replace('/[.\s\-\/|\\\\]+/', '', $partNo) ?? '';
                if ($codeForRule !== '') {
                    $mappedPartNo = AutonetClient::applyPrefix($brandName, $codeForRule);
                    if ($mappedPartNo !== '' && $mappedPartNo !== $partNo) {
                        $mappedKey = 'part:' . $mappedPartNo;
                        if (!isset($seen[$mappedKey])) {
                            $seen[$mappedKey] = true;
                            $items[] = ['PartNo' => $mappedPartNo, 'Quantity' => 2];
                        }
                    }
                }
            }

            if (!empty($row->mainart_code_parts) && !empty($row->mainart_brands)) {
                $refNrs[] = (string) $row->mainart_code_parts;
                $refBrands[] = (string) $row->mainart_brands;
                $validPairs[(string) $row->mainart_code_parts . '|' . (string) $row->mainart_brands] = true;
            }
        }

        $this->appendQwpItems($items, $seen, $refNrs, $refBrands, $validPairs, $query, $pdo, true);
        $this->appendQwpItems($items, $seen, $refNrs, [], [], $query, $pdo, false);

        $directSearchCode = trim($rawQuery !== '' ? $rawQuery : $query);
        if ($directSearchCode === '') {
            $directSearchCode = $query;
        }

        if ($rows === [] && $pdo !== null) {
            $directCandidates = $this->buildQwpRefCandidates([], $query);
            $qwpDirect = $this->findQwpRow($pdo, $directCandidates);
            if ($qwpDirect !== null) {
                $key = 'part:' . $directSearchCode;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $items[] = ['PartNo' => $directSearchCode, 'Quantity' => 2];
                }
            }
        }

        $queryKey = 'part:' . $directSearchCode;
        if (!isset($seen[$queryKey])) {
            $seen[$queryKey] = true;
            $items[] = ['PartNo' => $directSearchCode, 'Quantity' => 2];
        }

        return $items;
    }

    /** @param array<int, object> $rows @return array<string, array<string, mixed>> */
    public function buildRowMap(array $rows, string $query, ?PDO $pdo): array
    {
        $map = [];
        $refNrs = [];
        $refBrands = [];
        $validPairs = [];

        foreach ($rows as $row) {
            $mainCode = (string) ($row->mainart_code_parts ?? '');
            if ($mainCode === '') {
                continue;
            }
            $normalized = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainCode) ?? '';
            if ($normalized === '') {
                continue;
            }
            if (!isset($map[$normalized])) {
                $meta = [
                    'manufacturer' => $row->mainart_brands ?? null,
                    'db_name' => $row->brands ?? null,
                    'order_code' => $mainCode,
                ];
                $map[$normalized] = $meta;
                $brandName = (string) ($row->mainart_brands ?? '');
                if ($this->isLemforderCatalogBrand($brandName) && preg_match('/^(\d{4,})01$/', $normalized, $m)) {
                    $shortKey = $m[1];
                    if (!isset($map[$shortKey])) {
                        $map[$shortKey] = $meta;
                    }
                }
            }

            if (!empty($row->mainart_code_parts) && !empty($row->mainart_brands)) {
                $refNrs[] = (string) $row->mainart_code_parts;
                $refBrands[] = (string) $row->mainart_brands;
                $validPairs[(string) $row->mainart_code_parts . '|' . (string) $row->mainart_brands] = true;
            }
        }

        $this->seedQwpMap($map, $refNrs, $refBrands, $validPairs, $pdo, true);
        $this->seedQwpMap($map, $refNrs, [], [], $pdo, false);

        if ($map === [] && $query !== '' && $pdo !== null) {
            $directCandidates = $this->buildQwpRefCandidates([], $query);
            $qwpDirect = $this->findQwpRow($pdo, $directCandidates);
            if ($qwpDirect !== null) {
                $q = preg_replace('/[.\s\-\/|\\\\]+/', '', $query) ?? '';
                if ($q !== '') {
                    $map[$q] = [
                        'manufacturer' => 'QWP',
                        'db_name' => 'QWP',
                        'order_code' => $query,
                    ];
                }
            }
        }

        return $map;
    }

    /** @param array<int, object> $rows @return array<string, mixed> */
    private function loadFallbackBrandIds(array $rows, ?PDO $pdo): array
    {
        if ($pdo === null) {
            return [];
        }

        $brandsNeedingId = [];
        foreach ($rows as $row) {
            if (empty($row->brand_id) && !empty($row->mainart_brands)) {
                $brandsNeedingId[] = (string) $row->mainart_brands;
            }
        }
        $brandsNeedingId = array_values(array_unique(array_filter($brandsNeedingId)));
        if ($brandsNeedingId === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($brandsNeedingId), '?'));
        $stmt = $pdo->prepare(
            "SELECT mainart_brands, brand_id FROM parts_catalog
             WHERE mainart_brands IN ($placeholders) AND brand_id IS NOT NULL"
        );
        $stmt->execute($brandsNeedingId);

        $fallback = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $brandIdRow) {
            $brandName = (string) ($brandIdRow->mainart_brands ?? '');
            if ($brandName !== '' && !isset($fallback[$brandName])) {
                $fallback[$brandName] = $brandIdRow->brand_id;
            }
        }

        return $fallback;
    }

    /** @param array<int, array<string, mixed>> $items @param array<string, bool> $seen @param array<string, bool> $validPairs */
    private function appendQwpItems(
        array &$items,
        array &$seen,
        array $refNrs,
        array $refBrands,
        array $validPairs,
        string $query,
        ?PDO $pdo,
        bool $strictBrandMatch
    ): void {
        if ($pdo === null) {
            return;
        }

        if ($strictBrandMatch) {
            if ($refNrs === [] || $refBrands === []) {
                return;
            }
            $uniqueRefs = array_values(array_unique($refNrs));
            $uniqueBrands = array_values(array_unique($refBrands));
            $refPh = implode(',', array_fill(0, count($uniqueRefs), '?'));
            $brandPh = implode(',', array_fill(0, count($uniqueBrands), '?'));
            $stmt = $pdo->prepare(
                "SELECT RefNr, ReferenceBrand, ArtNr FROM autonet_qwp_data
                 WHERE RefNr IN ($refPh) AND ReferenceBrand IN ($brandPh)"
            );
            $stmt->execute(array_merge($uniqueRefs, $uniqueBrands));
        } else {
            $candidates = $this->buildQwpRefCandidates($refNrs, $query);
            if ($candidates === []) {
                return;
            }
            $ph = implode(',', array_fill(0, count($candidates), '?'));
            $stmt = $pdo->prepare("SELECT RefNr, ReferenceBrand, ArtNr FROM autonet_qwp_data WHERE RefNr IN ($ph)");
            $stmt->execute($candidates);
        }

        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $qRow) {
            if ($strictBrandMatch) {
                $pairKey = (string) ($qRow->RefNr ?? '') . '|' . (string) ($qRow->ReferenceBrand ?? '');
                if (!isset($validPairs[$pairKey])) {
                    continue;
                }
            }
            $artNr = (string) ($qRow->ArtNr ?? '');
            if ($artNr === '') {
                continue;
            }
            $key = 'part:' . $artNr;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = ['PartNo' => $artNr, 'Quantity' => 2];
        }
    }

    /** @param array<string, array<string, mixed>> $map @param array<string, bool> $validPairs */
    private function seedQwpMap(
        array &$map,
        array $refNrs,
        array $refBrands,
        array $validPairs,
        ?PDO $pdo,
        bool $strictBrandMatch
    ): void {
        if ($pdo === null) {
            return;
        }

        if ($strictBrandMatch) {
            if ($refNrs === [] || $refBrands === []) {
                return;
            }
            $uniqueRefs = array_values(array_unique($refNrs));
            $uniqueBrands = array_values(array_unique($refBrands));
            $refPh = implode(',', array_fill(0, count($uniqueRefs), '?'));
            $brandPh = implode(',', array_fill(0, count($uniqueBrands), '?'));
            $stmt = $pdo->prepare(
                "SELECT RefNr, ReferenceBrand, ArtNr FROM autonet_qwp_data
                 WHERE RefNr IN ($refPh) AND ReferenceBrand IN ($brandPh)"
            );
            $stmt->execute(array_merge($uniqueRefs, $uniqueBrands));
        } else {
            $candidates = $this->buildQwpRefCandidates($refNrs, '');
            if ($candidates === []) {
                return;
            }
            $ph = implode(',', array_fill(0, count($candidates), '?'));
            $stmt = $pdo->prepare("SELECT RefNr, ReferenceBrand, ArtNr FROM autonet_qwp_data WHERE RefNr IN ($ph)");
            $stmt->execute($candidates);
        }

        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $qRow) {
            if ($strictBrandMatch) {
                $pairKey = (string) ($qRow->RefNr ?? '') . '|' . (string) ($qRow->ReferenceBrand ?? '');
                if (!isset($validPairs[$pairKey])) {
                    continue;
                }
            }
            $artNr = (string) ($qRow->ArtNr ?? '');
            $normalizedArt = preg_replace('/[.\s\-\/|\\\\]+/', '', $artNr) ?? '';
            if ($normalizedArt === '' || isset($map[$normalizedArt])) {
                continue;
            }
            $map[$normalizedArt] = [
                'manufacturer' => 'QWP',
                'db_name' => 'QWP',
                'order_code' => $artNr,
            ];
        }
    }

    /** @param array<int, string> $refNrs @return array<int, string> */
    private function buildQwpRefCandidates(array $refNrs, string $query): array
    {
        $raw = array_values(array_filter(array_merge($refNrs, [$query]), static fn ($v) => is_string($v) && trim($v) !== ''));
        if ($raw === []) {
            return [];
        }

        $out = [];
        foreach ($raw as $value) {
            $v = trim($value);
            if ($v === '') {
                continue;
            }
            $out[$v] = true;
            $normalized = preg_replace('/[.\s\-\/|\\\\]+/', '', $v) ?? '';
            if ($normalized !== '') {
                $out[$normalized] = true;
                $upper = strtoupper($normalized);
                $out[$upper] = true;
                if (preg_match('/^(\d{5,})[A-Z]{1,3}$/', $upper, $m)) {
                    $out[$m[1]] = true;
                }
            }
        }

        return array_keys($out);
    }

    /** @param array<int, string> $candidates @return object|null */
    private function findQwpRow(PDO $pdo, array $candidates): ?object
    {
        if ($candidates === []) {
            return null;
        }

        $ph = implode(',', array_fill(0, count($candidates), '?'));
        $stmt = $pdo->prepare(
            "SELECT RefNr, ReferenceBrand, ArtNr FROM autonet_qwp_data
             WHERE ArtNr IN ($ph) OR RefNr IN ($ph) LIMIT 1"
        );
        $stmt->execute(array_merge($candidates, $candidates));
        $row = $stmt->fetch(PDO::FETCH_OBJ);

        return $row ?: null;
    }

    private function isLemforderCatalogBrand(string $brand): bool
    {
        $u = strtoupper(str_replace(['Ö', 'ö'], 'O', $brand));

        return str_contains($u, 'LEMFORDER');
    }
}
