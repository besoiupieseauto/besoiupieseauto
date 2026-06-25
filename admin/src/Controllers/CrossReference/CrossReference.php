<?php

declare(strict_types=1);

namespace Evasystem\Controllers\CrossReference;

use Evasystem\Core\Hub\HubScaffoldController;

final class CrossReference extends HubScaffoldController
{
    protected const TABLE = 'cross_reference';
    protected const LABEL = 'Cross-reference';
    protected const SESSION_KEY = 'cross_reference';
}
