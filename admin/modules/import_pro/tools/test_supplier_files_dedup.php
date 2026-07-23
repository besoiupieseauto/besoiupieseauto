<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
define('BESOIU_ROOT', $root);
define('BESOIU_APP', $root . '/app');
define('BESOIU_ADMIN', $root . '/admin');

require_once $root . '/app/Import/bootstrap.php';
require_once $root . '/admin/bootstrap.php';
require_once $root . '/admin/vendor/autoload.php';
require_once $root . '/app/Import/MatchingPro/api/bootstrap.php';

\Besoiu\Core\Module\ModuleRegistry::instance(
    \Besoiu\Core\Bootstrap\ApiBootstrap::adminRoot()
)->discover();

use Besoiu\Modules\Furnizori\Service\SupplierImportFilesBrowser;

$row = null;
foreach (import_motor_load_furnizori_rows() as $candidate) {
    if (!is_array($candidate)) {
        continue;
    }
    $code = strtoupper(trim((string) ($candidate['code'] ?? '')));
    if (str_contains(strtolower($code), 'autototal') || str_contains(strtolower((string) ($candidate['name'] ?? '')), 'autototal')) {
        $row = $candidate;
        break;
    }
}
if (!is_array($row)) {
    $row = import_motor_load_furnizori_rows()[0] ?? null;
}
if (!is_array($row)) {
    echo "No autototal supplier\n";
    exit(1);
}

$browser = new SupplierImportFilesBrowser();
$result = $browser->browse([
    'code' => (string) ($row['code'] ?? ''),
    'randomn_id' => (int) ($row['randomn_id'] ?? 0),
    'connection_type' => (string) ($row['connection_type'] ?? ''),
], '', ['auto_mirror' => false, 'include_remote' => false]);

$entries = is_array($result['entries'] ?? null) ? $result['entries'] : [];
$names = [];
$dupes = [];
foreach ($entries as $entry) {
    $name = strtolower(trim((string) ($entry['name'] ?? '')));
    if ($name === '') {
        continue;
    }
    if (isset($names[$name])) {
        $dupes[] = $name;
    }
    $names[$name] = ($entry['source'] ?? '?');
}

echo 'supplier=' . ($row['code'] ?? '') . ' entries=' . count($entries) . ' dupes=' . count($dupes) . PHP_EOL;
foreach ($entries as $entry) {
    echo '  - ' . ($entry['name'] ?? '') . ' [' . ($entry['source'] ?? '') . ']' . PHP_EOL;
}
exit($dupes === [] ? 0 : 2);
