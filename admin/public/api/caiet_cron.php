<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';
require_once __DIR__ . '/caiet_parity.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Config\Database;

$config = ApiBootstrap::bootJsonApi();
Database::ensureLegacy($config);

$secret = trim((string) ($_ENV['LKQ_CRON_SECRET'] ?? getenv('LKQ_CRON_SECRET') ?: ''));
$given = trim((string) ($_GET['key'] ?? $_POST['key'] ?? ''));
if ($secret === '' || !hash_equals($secret, $given)) {
    ApiBootstrap::json(['success' => false, 'message' => 'Cheie cron invalidă.'], 403);
}

$job = trim((string) ($_GET['job'] ?? 'fancourier'));
try {
    if ($job === 'lkq') {
        $data = caiet_lkq_import(['key' => $given, 'zip' => (string) ($_GET['zip'] ?? '')]);
    } else {
        $data = caiet_awb_track(80);
    }
    ApiBootstrap::json(['success' => true, 'data' => $data]);
} catch (Throwable $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 500);
}
