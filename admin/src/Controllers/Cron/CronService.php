<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Cron;

/**
 * @deprecated Mutat în Besoiu\Services\CronService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Cron\CronService', false)) {
    class_alias(\Besoiu\Services\CronService::class, 'Besoiu\Controllers\Cron\CronService');
}
