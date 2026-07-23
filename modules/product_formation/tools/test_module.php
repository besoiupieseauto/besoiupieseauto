<?php
declare(strict_types=1);

$adminRoot = dirname(__DIR__, 3) . '/admin';
require_once $adminRoot . '/bootstrap.php';
require_once $adminRoot . '/vendor/autoload.php';

use Besoiu\Core\Module\ModuleGate;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Services\ProductCardFormationService;
use Besoiu\Services\ProductDescriptionTabsService;

ModuleRegistry::instance($adminRoot)->discover();

echo 'product_formation enabled: ' . (ModuleGate::enabled('product_formation') ? 'yes' : 'no') . PHP_EOL;

$map = ModuleGate::optionalMap();
echo 'slugs: ' . implode(',', $map['product_formation']['slugs'] ?? []) . PHP_EOL;

$service = new ProductCardFormationService();
$config = $service->load();
echo 'config version: ' . ($config['version'] ?? '?') . PHP_EOL;
echo 'channels: ' . count($config['channels'] ?? []) . PHP_EOL;

$editor = $service->loadEditorConfig('default');
echo 'editor scope: ' . ($editor['scope'] ?? '?') . PHP_EOL;

$tabs = new ProductDescriptionTabsService($service);
$preview = $tabs->preview(ProductCardFormationService::sampleContext());
echo 'tabs preview count: ' . count($preview['tabs'] ?? []) . PHP_EOL;

$titlePreview = $service->preview(ProductCardFormationService::CHANNEL_WEBSITE, ProductCardFormationService::sampleContext());
echo 'title preview: ' . ($titlePreview['title'] ?? 'empty') . PHP_EOL;

$pageFile = __DIR__ . '/../pages/product-formation.php';
echo 'page exists: ' . (is_file($pageFile) ? 'yes' : 'no') . PHP_EOL;

$handlerFile = __DIR__ . '/../src/Handler/ProductCardFormationHandler.php';
echo 'handler exists: ' . (is_file($handlerFile) ? 'yes' : 'no') . PHP_EOL;
