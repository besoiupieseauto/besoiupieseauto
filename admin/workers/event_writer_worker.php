<?php

declare(strict_types=1);

/**
 * Worker CLI — consumă coada ai-events și scrie în MySQL.
 *
 * Usage:
 *   php admin/workers/event_writer_worker.php
 *   php admin/workers/event_writer_worker.php --max=50
 */
define('BESOIU_ROOT', dirname(__DIR__, 2));
require BESOIU_ROOT . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Services\AiIntelligence\EventWriterWorker;

$max = 0;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--max=')) {
        $max = max(0, (int) substr($arg, 6));
    }
}

$worker = new EventWriterWorker();
$processed = $worker->run($max);

fwrite(STDOUT, "event-writer: processed {$processed} batch(es)\n");
exit(0);
