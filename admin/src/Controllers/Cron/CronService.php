<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Cron;

use Evasystem\Core\Hub\HubScaffoldModel;
use Evasystem\Core\Hub\HubScaffoldService;

final class CronService
{
    private HubScaffoldService $hubService;

    public function __construct(?HubScaffoldService $hubService = null)
    {
        $this->hubService = $hubService ?? new HubScaffoldService(new HubScaffoldModel('cron'));
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllCrons(): array
    {
        return $this->hubService->listAll();
    }
}
