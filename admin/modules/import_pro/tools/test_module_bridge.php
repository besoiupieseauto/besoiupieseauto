<?php
declare(strict_types=1);

$autoRoot = dirname(__DIR__, 3);
define('BESOIU_ROOT', $autoRoot);
define('BESOIU_APP', $autoRoot . '/app');

require_once BESOIU_APP . '/Import/bootstrap.php';
require_once $autoRoot . '/admin/bootstrap.php';
require_once $autoRoot . '/admin/vendor/autoload.php';

\Besoiu\Core\Module\ModuleRegistry::instance(
    \Besoiu\Core\Bootstrap\ApiBootstrap::adminRoot()
)->discover();

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Besoiu\\Modules\\ImportPro\\')) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen('Besoiu\\Modules\\ImportPro\\')));
    $path = dirname(__DIR__) . '/src/' . $rel . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use Besoiu\Import\Support\ImportPathResolver;
use Besoiu\Modules\ImportPro\Support\ImportProBridge;

ImportPathResolver::applyEnv();

$checks = [
    'matching_index' => ImportPathResolver::matchingProRoot() . '/index.php',
    'api_bootstrap' => ImportPathResolver::matchingProRoot() . '/api/bootstrap.php',
    'prelucrare_matcher' => ImportPathResolver::prelucrareRoot() . '/api/lib/ProductMatcher.php',
    'fetch_product_api' => ImportPathResolver::fetchProductRoot() . '/api/index-status.php',
    'public_wrapper' => $autoRoot . '/admin/public/import-pro/index.php',
];

echo "Import Pro bridge test\n";
$fail = 0;
foreach ($checks as $label => $path) {
    $exists = is_file($path);
    echo sprintf("[%s] %s\n", $exists ? 'OK' : 'FAIL', $label);
    if (!$exists) { $fail++; }
}
try {
    ImportProBridge::boot();
    echo "[OK] bridge root: " . ImportProBridge::root() . "\n";
    echo "[OK] scraper proxy: " . ImportProBridge::scraperProxyUrl() . "\n";
    if (\Besoiu\Core\Module\OptionalModuleBridge::isOperational('scraper_web')) {
        echo "[OK] scraper_web module operational\n";
    } else {
        echo "[WARN] scraper_web module not operational — scraping images may fail\n";
        $fail++;
    }
} catch (Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    $fail++;
}
exit($fail > 0 ? 1 : 0);
