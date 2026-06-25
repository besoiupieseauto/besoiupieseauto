<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

final class ProductNameResolver
{
    public function resolveProducts(array &$productsMap): void
    {
        foreach ($productsMap as &$product) {
            if (empty($product['name'])) {
                if (!empty($product['db_name'])) {
                    $product['name'] = $product['db_name'];
                } elseif (!empty($product['manufacturer'])) {
                    $product['name'] = $product['manufacturer'];
                }
            }
            unset($product['db_name']);
        }
    }
}
