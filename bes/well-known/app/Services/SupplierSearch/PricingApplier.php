<?php

namespace App\Services\SupplierSearch;

class PricingApplier
{
    /**
     * Apply pricing calculations to all variants in a product.
     */
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

                $rawPrice = (float) $variant['price'];
                $currency = $variant['currency'] ?? 'RON';
                $pricing = $this->calculateVariantPricing($supplierName, $rawPrice, $currency, $variant);

                $variant['raw_price'] = $pricing['raw_price'];
                $variant['calculated_price'] = $pricing['calculated_price'];
                $variant['acquisition_price'] = $pricing['acquisition_price'];
                $variant['price_breakdown'] = $pricing['price_breakdown'];
                if (isset($pricing['original_price'])) {
                    $variant['original_price'] = $pricing['original_price'];
                }
            }
        }
    }

    /**
     * Apply pricing to all products in the map.
     */
    public function applyToProducts(array &$productsMap): void
    {
        foreach ($productsMap as &$product) {
            $this->applyToProduct($product);
        }
    }

    /**
     * Calculate pricing for a variant based on supplier.
     */
    public function calculateVariantPricing(string $supplier, float $rawPrice, string $currency = 'RON', array $variant = []): array
    {
        $supplier = strtolower($supplier);
        $originalPrice = $rawPrice;
        $markupMultiplier = 1.35;

        if ($supplier === 'autopartner') {
            $markupMultiplier = 1.45;
            $depositIncluded = $variant['deposit_included'] ?? false;
            $depositPrice = (float) ($variant['deposit_price'] ?? 0);
            if ($depositIncluded && $depositPrice > 0) {
                $rawPrice = $rawPrice - $depositPrice;
            }
        }

        if ($supplier === 'elit') {
            $markupMultiplier = 1.40;
        }

        $acquisitionPrice = (int) ceil($rawPrice * 1.21);
        $calculatedPrice = (int) ceil($rawPrice * 1.21 * $markupMultiplier);
        $priceBreakdown = [
            'acquisition' => $acquisitionPrice,
            'plus_10' => (int) ceil($acquisitionPrice * 1.10),
            'plus_20' => (int) ceil($acquisitionPrice * 1.20),
            'plus_30' => (int) ceil($acquisitionPrice * 1.30),
            'final' => $calculatedPrice,
        ];

        return [
            'raw_price' => $rawPrice,
            'original_price' => $originalPrice,
            'calculated_price' => $calculatedPrice,
            'acquisition_price' => $acquisitionPrice,
            'price_breakdown' => $priceBreakdown,
            'currency' => $currency,
        ];
    }
}
