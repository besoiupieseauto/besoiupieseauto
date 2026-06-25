<?php

declare(strict_types=1);

/**
 * Suite 50 verificări pipeline imagini — toate trebuie HTTP 200.
 *
 * Rulare: php admin/tools/test_image_pipeline_50.php
 * Exit code 0 = toate OK, 1 = cel puțin un eșec.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$root = dirname(__DIR__, 2);
require_once $root . '/admin/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($root . '/admin');
$dotenv->safeLoad();

require_once $root . '/admin/src/Services/ImagePipelineHealthCheck.php';

$checker = new Evasystem\Services\ImagePipelineHealthCheck($root);
$result = $checker->runAll();

$failures = array_filter($result['tests'], static fn (array $t): bool => ($t['http'] ?? 0) !== 200);

foreach ($result['tests'] as $t) {
    $icon = ($t['http'] ?? 0) === 200 ? 'OK' : 'FAIL';
    echo sprintf("[%s] #%02d HTTP %d — %s — %s\n", $icon, $t['id'], $t['http'], $t['name'], $t['message']);
}

echo "\n---\n";
echo 'Total: ' . $result['summary']['total'] . ' | OK: ' . $result['summary']['ok'] . ' | FAIL: ' . $result['summary']['fail'] . "\n";

if ($failures !== []) {
    exit(1);
}

echo "ALL 50 TESTS HTTP 200 OK\n";
exit(0);
