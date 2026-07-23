<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

/**
 * @deprecated Mutat în Besoiu\Services\Furnizori\FurnizoriRemoteSyncService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Furnizori\FurnizoriRemoteSyncService', false)) {
    class_alias(\Besoiu\Services\Furnizori\FurnizoriRemoteSyncService::class, 'Besoiu\Controllers\Furnizori\FurnizoriRemoteSyncService');
}
