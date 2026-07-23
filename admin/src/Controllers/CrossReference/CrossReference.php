<?php

declare(strict_types=1);

namespace Besoiu\Controllers\CrossReference;

use Besoiu\Core\Hub\HubScaffoldController;

final class CrossReference extends HubScaffoldController
{
    protected const TABLE = 'cross_reference';
    protected const LABEL = 'Cross-reference';
    protected const SESSION_KEY = 'cross_reference';
}
