<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Report;

use Evasystem\Core\Hub\HubScaffoldModel;
use Evasystem\Core\Hub\HubScaffoldService;

final class ReportService
{
    private HubScaffoldService $hubService;

    public function __construct(?HubScaffoldService $hubService = null)
    {
        $this->hubService = $hubService ?? new HubScaffoldService(new HubScaffoldModel('report'));
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllReports(): array
    {
        return $this->hubService->listAll();
    }
}
