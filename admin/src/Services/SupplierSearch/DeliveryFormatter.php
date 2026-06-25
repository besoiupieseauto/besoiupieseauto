<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

final class DeliveryFormatter
{
    public function formatProducts(array &$productsMap): void
    {
        foreach ($productsMap as &$product) {
            foreach ($product['suppliers'] as $supplierName => &$supplierData) {
                if (!isset($supplierData['variants'])) {
                    continue;
                }
                foreach ($supplierData['variants'] as &$variant) {
                    $formatted = $this->formatDeliveryInfo((string) $supplierName, $variant);
                    $variant['livrare'] = $formatted['livrare'];
                    $variant['depozit'] = $formatted['depozit'];
                }
            }
        }
    }

    /** @param array<string, mixed> $variant @return array{livrare:string,depozit:string} */
    public function formatDeliveryInfo(string $supplier, array $variant): array
    {
        $supplier = strtolower($supplier);
        $deliveryInfo = (string) ($variant['delivery']['info_text'] ?? '');
        $plantName = $variant['delivery']['plant_name'] ?? null;
        $departamentCode = $variant['departamentCode'] ?? null;
        $result = ['livrare' => '', 'depozit' => ''];

        if ($supplier === 'materom') {
            $time = '';
            if (preg_match('/(\d{1,2}:\d{2})/', strtolower($deliveryInfo), $matches)) {
                $time = ltrim($matches[1], '0');
            }
            if ($plantName === 'Timișoara') {
                $result['depozit'] = 'TM';
                $result['livrare'] = $time !== '' ? "Azi {$time}" : 'azi';
            } elseif ($plantName === 'Centru Logistic') {
                $result['depozit'] = 'Mures';
                $result['livrare'] = $time !== '' ? "Maine {$time}" : 'maine';
            } else {
                $result['depozit'] = (string) ($plantName ?: '-');
                $result['livrare'] = $deliveryInfo !== '' ? $deliveryInfo : 'Verifica stoc';
            }
        } elseif ($supplier === 'elit') {
            $result['livrare'] = 'Verifica stoc';
        } elseif ($supplier === 'autopartner') {
            $processed = (string) $departamentCode;
            if ($processed === 'CN') {
                $processed = 'Maine 8:00';
            } elseif ($processed === '120' || $processed === '72') {
                $processed = 'Poimaine 8:00';
            }
            $result['livrare'] = $processed !== '' ? $processed : 'Verifica stoc';
        }

        return $result;
    }
}
