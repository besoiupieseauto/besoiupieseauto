<?php

declare(strict_types=1);

namespace Besoiu\Core\Furnizori;

/** @deprecated Folosiți Besoiu\Core\Supplier\SupplierCatalogRepositoryFallback */
class_alias(
    \Besoiu\Core\Supplier\SupplierCatalogRepositoryFallback::class,
    'Besoiu\Core\Furnizori\FurnizoriModelFallback'
);
