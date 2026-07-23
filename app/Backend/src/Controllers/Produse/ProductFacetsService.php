<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Produse;

/**
 * @deprecated Mutat în Besoiu\Services\Products\ProductFacetsService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Produse\ProductFacetsService', false)) {
    class_alias(\Besoiu\Services\Products\ProductFacetsService::class, 'Besoiu\Controllers\Produse\ProductFacetsService');
}
