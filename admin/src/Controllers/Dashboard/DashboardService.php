<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Dashboard;

/**
 * @deprecated Mutat în Besoiu\Services\DashboardService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Dashboard\DashboardService', false)) {
    class_alias(\Besoiu\Services\DashboardService::class, 'Besoiu\Controllers\Dashboard\DashboardService');
}
