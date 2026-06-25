<?php

namespace App\Services\SupplierSearch;

class DeliveryFormatter
{
    /**
     * Format delivery info (livrare, depozit) for all variants in productsMap.
     */
    public function formatProducts(array &$productsMap): void
    {
        $cache = [];
        foreach ($productsMap as &$product) {
            foreach ($product['suppliers'] as $supplierName => &$supplierData) {
                if (!isset($supplierData['variants'])) {
                    continue;
                }
                foreach ($supplierData['variants'] as &$variant) {
                    $cacheKey = strtolower((string) $supplierName) . '|' .
                        (string) ($variant['delivery']['info_text'] ?? '') . '|' .
                        (string) ($variant['delivery']['plant_name'] ?? '') . '|' .
                        (string) ($variant['depot'] ?? '') . '|' .
                        (string) ($variant['departamentCode'] ?? '');

                    if (!isset($cache[$cacheKey])) {
                        $cache[$cacheKey] = $this->formatDeliveryInfo($supplierName, $variant);
                    }
                    $formatted = $cache[$cacheKey];
                    $variant['livrare'] = $formatted['livrare'];
                    $variant['depozit'] = $formatted['depozit'];
                }
            }
        }
    }

    /**
     * Format livrare and depozit for a single variant.
     */
    public function formatDeliveryInfo(string $supplier, array $variant): array
    {
        $supplier = strtolower($supplier);
        $deliveryInfo = $variant['delivery']['info_text'] ?? '';
        $plantName = $variant['delivery']['plant_name'] ?? null;
        $depot = $variant['depot'] ?? null;
        $departamentCode = $variant['departamentCode'] ?? null;

        $result = ['livrare' => '', 'depozit' => ''];

        if ($supplier === 'materom') {
            $time = '';
            $text = strtolower(trim($deliveryInfo ?? ''));
            if (preg_match('/(\d{1,2}:\d{2})/', $text, $matches)) {
                $time = ltrim($matches[1], '0');
            }
            if ($plantName === 'Timișoara') {
                $result['depozit'] = 'TM';
                $result['livrare'] = $time ? "Azi {$time}" : 'azi';
            } elseif ($plantName === 'Centru Logistic') {
                $result['depozit'] = 'Mures';
                $result['livrare'] = $time ? "Maine {$time}" : 'maine';
            } else {
                $result['depozit'] = $plantName ?: '-';
                $result['livrare'] = $deliveryInfo ?: 'Verifica stoc';
            }
        } elseif ($supplier === 'elit') {
            $result['livrare'] = 'Verifica stoc';
            $result['depozit'] = '';
        } elseif ($supplier === 'autopartner') {
            $processedDeptCode = $departamentCode ?? '';
            if ($processedDeptCode === 'CN') {
                $processedDeptCode = 'Maine 8:00';
            } elseif ($processedDeptCode === '120' || $processedDeptCode === '72') {
                $processedDeptCode = 'Poimaine 8:00';
            }
            $result['livrare'] = $processedDeptCode ?: 'Verifica stoc';
            $result['depozit'] = '';
        } elseif ($supplier === 'autototal') {
            $warehouse = $plantName ?? '';
            if (preg_match('/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}):\d{2}/i', $deliveryInfo, $matches)) {
                $datePart = $matches[1];
                $timePart = ltrim($matches[2], '0');
                $deliveryDate = new \DateTime($datePart);
                $today = new \DateTime();
                $today->setTime(0, 0, 0);
                $deliveryDate->setTime(0, 0, 0);
                $diff = $today->diff($deliveryDate);
                $daysDiff = (int) $diff->format('%r%a');
                if ($daysDiff === 0) {
                    $result['livrare'] = $timePart ? "Azi {$timePart}" : 'Azi';
                } elseif ($daysDiff === 1) {
                    $result['livrare'] = $timePart ? "Mâine {$timePart}" : 'Mâine';
                } elseif ($daysDiff === 2) {
                    $result['livrare'] = $timePart ? "Poimâine {$timePart}" : 'Poimâine';
                } else {
                    $result['livrare'] = $timePart ? "{$daysDiff} zile {$timePart}" : "{$daysDiff} zile";
                }
            } else {
                $result['livrare'] = $deliveryInfo ?: 'Verifica stoc';
            }
            $result['depozit'] = $warehouse ?: '-';
        } elseif ($supplier === 'autonet') {
            if (preg_match('/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}):\d{2}/i', $deliveryInfo, $matches)) {
                $timePart = ltrim($matches[2], '0');
                $deliveryDate = new \DateTime($matches[1]);
                $today = new \DateTime();
                $today->setTime(0, 0, 0);
                $deliveryDate->setTime(0, 0, 0);
                $diff = $today->diff($deliveryDate);
                $daysDiff = (int) $diff->format('%r%a');
                if ($daysDiff === 0) {
                    $result['livrare'] = $timePart ? "Azi {$timePart}" : 'Azi';
                } elseif ($daysDiff === 1) {
                    $result['livrare'] = $timePart ? "Mâine {$timePart}" : 'Mâine';
                } elseif ($daysDiff === 2) {
                    $result['livrare'] = $timePart ? "Poimâine {$timePart}" : 'Poimâine';
                } else {
                    $result['livrare'] = $timePart ? "{$daysDiff} zile {$timePart}" : "{$daysDiff} zile";
                }
            } else {
                $result['livrare'] = $deliveryInfo ?: 'Verifica stoc';
            }
            $result['depozit'] = $depot ?: '-';
        }

        return $result;
    }
}
