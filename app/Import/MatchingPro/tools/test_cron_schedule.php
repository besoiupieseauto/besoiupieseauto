<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';

$schedule = import_cron_suppliers_schedule();
echo 'suppliers=' . count($schedule['suppliers']) . PHP_EOL;
echo 'due=' . $schedule['due_count'] . ' active=' . $schedule['active_count'] . PHP_EOL;
foreach ($schedule['suppliers'] as $row) {
    echo sprintf(
        "%s | %s | %s | timer=%s | %s\n",
        $row['slug'],
        $row['schedule_label'],
        $row['next_run_label'],
        $row['next_run_seconds'] ?? '—',
        $row['status']
    );
}
