<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Hub\HubScaffoldModel;
use Besoiu\Core\Hub\HubScaffoldService;

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
