<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Comenzi;

/**
 * @deprecated Mutat în Besoiu\Services\Fulfillment\OrderFulfillmentService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Comenzi\OrderFulfillmentService', false)) {
    class_alias(\Besoiu\Services\Fulfillment\OrderFulfillmentService::class, 'Besoiu\Controllers\Comenzi\OrderFulfillmentService');
}
