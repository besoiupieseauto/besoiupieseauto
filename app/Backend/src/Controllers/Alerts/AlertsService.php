<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Alerts;

/**
 * @deprecated Mutat în Besoiu\Services\AlertsService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Alerts\AlertsService', false)) {
    class_alias(\Besoiu\Services\AlertsService::class, 'Besoiu\Controllers\Alerts\AlertsService');
}
