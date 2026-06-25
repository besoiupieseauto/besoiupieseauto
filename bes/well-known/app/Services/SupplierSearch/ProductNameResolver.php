<?php

namespace App\Services\SupplierSearch;

class ProductNameResolver
{
    /**
     * Resolve product name from name, db_name, or manufacturer.
     */
    public function resolveProduct(array &$product): void
    {
        if (!empty($product['name'])) {
            return;
        }
        if (!empty($product['db_name'])) {
            $product['name'] = $product['db_name'];
            return;
        }
        if (!empty($product['manufacturer'])) {
            $product['name'] = $product['manufacturer'];
        }
    }

    /**
     * Resolve names for all products and remove db_name.
     */
    public function resolveProducts(array &$productsMap): void
    {
        foreach ($productsMap as &$product) {
            $this->resolveProduct($product);
            unset($product['db_name']);
        }
    }
}
