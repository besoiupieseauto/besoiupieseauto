<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Hub\HubScaffoldModel;
use Besoiu\Core\Hub\HubScaffoldService;

final class SettingsService
{
    private HubScaffoldService $hubService;

    public function __construct(?HubScaffoldService $hubService = null)
    {
        $this->hubService = $hubService ?? new HubScaffoldService(new HubScaffoldModel('settings'));
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllSettingss(): array
    {
        return $this->hubService->listAll();
    }
}
