<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Facturi;

/**
 * @deprecated Mutat în Besoiu\Services\Fulfillment\FacturiService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Facturi\FacturiService', false)) {
    class_alias(\Besoiu\Services\Fulfillment\FacturiService::class, 'Besoiu\Controllers\Facturi\FacturiService');
}
