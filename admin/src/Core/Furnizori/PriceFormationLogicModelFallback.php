<?php

declare(strict_types=1);

namespace Besoiu\Core\Furnizori;

/** @deprecated Folosiți Besoiu\Core\Supplier\SupplierPriceLogicStoreFallback */
class_alias(
    \Besoiu\Core\Supplier\SupplierPriceLogicStoreFallback::class,
    'Besoiu\Core\Furnizori\PriceFormationLogicModelFallback'
);
