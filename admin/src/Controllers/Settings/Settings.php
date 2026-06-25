<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Settings;

use Evasystem\Core\Hub\HubScaffoldController;

final class Settings extends HubScaffoldController
{
    protected const TABLE = 'settings';
    protected const LABEL = 'Setare';
    protected const SESSION_KEY = 'settings';
}
