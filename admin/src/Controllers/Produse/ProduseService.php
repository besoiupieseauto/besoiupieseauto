<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Produse;

/**
 * @deprecated Mutat în Besoiu\Services\Products\ProduseService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Produse\ProduseService', false)) {
    class_alias(\Besoiu\Services\Products\ProduseService::class, 'Besoiu\Controllers\Produse\ProduseService');
}
