<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Livrare;

/**
 * @deprecated Mutat în Besoiu\Services\Fulfillment\LivrareService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Livrare\LivrareService', false)) {
    class_alias(\Besoiu\Services\Fulfillment\LivrareService::class, 'Besoiu\Controllers\Livrare\LivrareService');
}
