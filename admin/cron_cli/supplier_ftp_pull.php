<?php

declare(strict_types=1);

/**
 * Descarca automat listele furnizorilor de pe FTP/SFTP in admin/storage/supplier_feeds/{cod}/.
 *
 * Usage:
 *   php admin/cron_cli/supplier_ftp_pull.php
 *   php admin/cron_cli/supplier_ftp_pull.php --force
 *   php admin/cron_cli/supplier_ftp_pull.php --code=ELIT
 */
define('BESOIU_ROOT', dirname(__DIR__, 2));
require BESOIU_ROOT . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Modules\Furnizori\Service\SupplierFtpPullService;
use Config\Database;

$envDir = is_file(BESOIU_ROOT . '/.env') ? BESOIU_ROOT : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}
if (class_exists(\Dotenv\Dotenv::class) && is_file(BESOIU_ADMIN . '/.env')) {
    \Dotenv\Dotenv::createImmutable(BESOIU_ADMIN)->safeLoad();
}

ModuleRegistry::instance(BESOIU_ADMIN)->boot();

$config = require BESOIU_BACKEND . '/config/config.php';
Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

$force = false;
$onlyCode = '';
foreach ($argv ?? [] as $arg) {
    if ($arg === '--force') {
        $force = true;
    } elseif (str_starts_with((string) $arg, '--code=')) {
        $onlyCode = substr((string) $arg, 7);
    }
}

set_time_limit(0);
ini_set('memory_limit', '512M');

$result = (new SupplierFtpPullService())->run($force, $onlyCode);

fwrite(STDOUT, (string) ($result['message'] ?? 'FTP pull') . PHP_EOL);
foreach ($result['suppliers'] ?? [] as $row) {
    if (!is_array($row)) {
        continue;
    }
    fwrite(
        STDOUT,
        sprintf(
            "  [%s] %s — %s\n",
            strtoupper((string) ($row['status'] ?? '')),
            (string) ($row['code'] ?? ''),
            (string) ($row['message'] ?? '')
        )
    );
}

exit(!empty($result['success']) ? 0 : 1);
