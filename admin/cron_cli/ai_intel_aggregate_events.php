<?php

declare(strict_types=1);

/**
 * Job orar — recalculează ai_intel_aggregates din evenimente.
 *
 * Usage:
 *   php admin/cron_cli/ai_intel_aggregate_events.php
 *   php admin/cron_cli/ai_intel_aggregate_events.php --days=2
 */
define('BESOIU_ROOT', dirname(__DIR__, 2));
require BESOIU_ROOT . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Services\AiIntelligence\EventAggregateService;
use Besoiu\Services\AiIntelligence\EventTrackingStore;

$days = 1;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = max(1, min(7, (int) substr($arg, 7)));
    }
}

$service = new EventAggregateService(new EventTrackingStore(null, BESOIU_ROOT), null, BESOIU_ROOT);
$result = $service->runRolling($days);

fwrite(
    STDOUT,
    sprintf(
        "ai-intel-aggregate: days=%d rows=%d\n",
        (int) ($result['days'] ?? 0),
        (int) ($result['total_rows'] ?? 0)
    )
);
exit(0);
