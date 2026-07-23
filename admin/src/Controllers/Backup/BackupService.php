<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Backup;

/**
 * @deprecated Mutat în Besoiu\Services\BackupService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Backup\BackupService', false)) {
    class_alias(\Besoiu\Services\BackupService::class, 'Besoiu\Controllers\Backup\BackupService');
}
