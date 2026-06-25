<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

final class PricingApplier
{
    public function applyToProducts(array &$productsMap): void
    {
        foreach ($productsMap as &$product) {
            $this->applyToProduct($product);
        }
    }

    /** @param array<string, mixed> $product */
    public function applyToProduct(array &$product): void
    {
        foreach ($product['suppliers'] as $supplierName => &$supplierData) {
            if (!isset($supplierData['variants']) || !is_array($supplierData['variants'])) {
                continue;
            }

            foreach ($supplierData['variants'] as &$variant) {
                if (!isset($variant['price'])) {
                    continue;
                }

                $pricing = $this->calculateVariantPricing(
                    (string) $supplierName,
                    (float) $variant['price'],
                    (string) ($variant['currency'] ?? 'RON'),
                    $variant
                );

                $variant['raw_price'] = $pricing['raw_price'];
                $variant['calculated_price'] = $pricing['calculated_price'];
                $variant['acquisition_price'] = $pricing['acquisition_price'];
                $variant['price_breakdown'] = $pricing['price_breakdown'];
            }
        }
    }

    /** @param array<string, mixed> $variant @return array<string, mixed> */
    public function calculateVariantPricing(string $supplier, float $rawPrice, string $currency = 'RON', array $variant = []): array
    {
        $supplier = strtolower($supplier);
        $markupMultiplier = 1.35;

        if ($supplier === 'autopartner') {
            $markupMultiplier = 1.45;
            $depositIncluded = $variant['deposit_included'] ?? false;
            $depositPrice = (float) ($variant['deposit_price'] ?? 0);
            if ($depositIncluded && $depositPrice > 0) {
                $rawPrice -= $depositPrice;
            }
        }

        if ($supplier === 'elit') {
            $markupMultiplier = 1.40;
        }

        $acquisitionPrice = (int) ceil($rawPrice * 1.21);
        $calculatedPrice = (int) ceil($rawPrice * 1.21 * $markupMultiplier);

        return [
            'raw_price' => $rawPrice,
            'calculated_price' => $calculatedPrice,
            'acquisition_price' => $acquisitionPrice,
            'price_breakdown' => [
                'acquisition' => $acquisitionPrice,
                'plus_10' => (int) ceil($acquisitionPrice * 1.10),
                'plus_20' => (int) ceil($acquisitionPrice * 1.20),
                'plus_30' => (int) ceil($acquisitionPrice * 1.30),
                'final' => $calculatedPrice,
            ],
            'currency' => $currency,
        ];
    }
}
