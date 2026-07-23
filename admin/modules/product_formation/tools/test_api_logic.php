<?php
declare(strict_types=1);

/**
 * Test API handler actions without HTTP (simulated POST/GET).
 */
$adminRoot = dirname(__DIR__, 3) . '/admin';
require_once $adminRoot . '/bootstrap.php';
require_once $adminRoot . '/vendor/autoload.php';

use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Modules\ProductFormation\Handler\ProductCardFormationHandler;
use Besoiu\Services\ProductCardFormationService;

// Cannot easily call Handler::handle() without session; test service operations directly.
$service = new ProductCardFormationService();
$defaults = ProductCardFormationService::defaultConfig();

// Test save/load roundtrip on temp config
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bpa_pcf_test_' . bin2hex(random_bytes(4)) . '.json';
$tmpService = new ProductCardFormationService($tmp);
$saved = $tmpService->save($defaults);
$loaded = $tmpService->load();
@unlink($tmp);

echo 'save/load roundtrip: ' . (($loaded['version'] ?? 0) === 4 ? 'OK' : 'FAIL') . PHP_EOL;

$preview = $service->preview('website', ProductCardFormationService::sampleContext());
echo 'preview title non-empty: ' . (trim((string)($preview['title'] ?? '')) !== '' ? 'OK' : 'FAIL') . PHP_EOL;

$scopes = $service->listSavedScopes();
echo 'list scopes: OK (' . count($scopes) . ' scopes)' . PHP_EOL;

$editor = $service->loadEditorConfig('default');
echo 'editor config keys: ' . (isset($editor['channels'], $editor['product_tabs']) ? 'OK' : 'FAIL') . PHP_EOL;

$parsed = ProductCardFormationService::parseScope('category:Filtre');
echo 'parse category scope: ' . ($parsed['type'] === 'category' ? 'OK' : 'FAIL') . PHP_EOL;

ModuleRegistry::instance($adminRoot)->discover();

echo 'handler class exists: ' . (class_exists(ProductCardFormationHandler::class) ? 'OK' : 'FAIL') . PHP_EOL;
