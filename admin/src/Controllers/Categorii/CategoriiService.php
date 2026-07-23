<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Categorii;

/**
 * @deprecated Mutat în Besoiu\Services\CategoriiService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Categorii\CategoriiService', false)) {
    class_alias(\Besoiu\Services\CategoriiService::class, 'Besoiu\Controllers\Categorii\CategoriiService');
}
