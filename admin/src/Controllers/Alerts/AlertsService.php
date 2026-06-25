<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Alerts;

use Evasystem\Core\Hub\HubScaffoldModel;
use Evasystem\Core\Hub\HubScaffoldService;

/** Serviciu listare hub — compatibil admin_hub_endpoint. */
final class AlertsService
{
    private HubScaffoldService $hubService;

    public function __construct(?HubScaffoldService $hubService = null)
    {
        $this->hubService = $hubService ?? new HubScaffoldService(new HubScaffoldModel('alerts'));
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllAlertss(): array
    {
        return $this->hubService->listAll();
    }
}
