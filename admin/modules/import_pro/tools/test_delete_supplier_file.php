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

$supplier = 'autototal';
$file = 'test_consumable.csv';
$path = $root . '/admin/storage/supplier_feeds/autototal/' . $file;

echo "Before disk: " . (is_file($path) ? 'yes' : 'no') . PHP_EOL;

$result = import_delete_stored_file($supplier, $file);
echo 'Delete: ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "After disk: " . (is_file($path) ? 'yes' : 'no') . PHP_EOL;

$row = null;
foreach (import_motor_load_furnizori_rows() as $candidate) {
    if (!is_array($candidate)) continue;
    $code = strtoupper(trim((string) ($candidate['code'] ?? '')));
    if (str_contains(strtolower($code), 'autototal')) {
        $row = $candidate;
        break;
    }
}

if (is_array($row)) {
    $browser = new SupplierImportFilesBrowser();
    $browse = $browser->browse([
        'code' => (string) ($row['code'] ?? ''),
        'randomn_id' => (int) ($row['randomn_id'] ?? 0),
    ], '', ['auto_mirror' => false, 'include_remote' => false]);
    $names = array_map(static fn ($e) => ($e['name'] ?? '') . ' [' . ($e['source'] ?? '') . ']', $browse['entries'] ?? []);
    echo 'Furnizori browse: ' . implode(', ', $names) . PHP_EOL;
}

$files = import_list_supplier_files();
$found = array_filter($files, static fn ($f) => ($f['supplier'] ?? '') === $supplier && ($f['filename'] ?? '') === $file);
echo 'Import Pro list: ' . (count($found) ? 'STILL THERE' : 'gone') . PHP_EOL;
