<?php
declare(strict_types=1);

/**
 * Cron backup zilnic — rulează din Task Scheduler sau manual CLI.
 * Exemplu: php admin/cron_cli/daily_backup.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Controllers\Backup\BackupService;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

try {
    $service = new BackupService();
    $result = $service->runBackup($config);
    echo '[backup] OK: ' . ($result['filename'] ?? '') . ' (' . ($result['size_human'] ?? '') . ')' . PHP_EOL;
    if (($result['removed_old'] ?? 0) > 0) {
        echo '[backup] Removed old: ' . (int) $result['removed_old'] . PHP_EOL;
    }
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[backup] ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
