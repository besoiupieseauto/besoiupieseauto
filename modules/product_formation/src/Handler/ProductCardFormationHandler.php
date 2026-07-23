<?php
declare(strict_types=1);

namespace Besoiu\Modules\ProductFormation\Handler;

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Services\ProductCardFormationService;
use Besoiu\Services\ProductDescriptionTabsService;
use Besoiu\Services\ProductTabChartService;
use Besoiu\Services\Products\ProductFacetsService;
use Throwable;

final class ProductCardFormationHandler
{
    public static function handle(): void
    {
        ApiBootstrap::bootJsonApi(true);
        ApiBootstrap::registerJsonFatalGuard('product_card_formation');
        ApiBootstrap::requireAuthenticatedSession();
        ApiBootstrap::beginBoundedJsonWork(60);

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $service = new ProductCardFormationService();
        $tabsService = new ProductDescriptionTabsService($service);
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $action = trim((string) ($_GET['action'] ?? ''));
        $scope = trim((string) ($_GET['scope'] ?? 'default'));

        try {
            if ($method === 'GET') {
                self::handleGet($service, $tabsService, $action, $scope);
            }

            if ($method !== 'POST') {
                ApiBootstrap::json(['success' => false, 'message' => 'Metodă neacceptată.'], 405);
            }

            self::handlePost($service, $tabsService, $action, $scope);
        } catch (Throwable $e) {
            ApiBootstrap::respondInternalError('product_card_formation', $e);
        }
    }

    private static function handleGet(
        ProductCardFormationService $service,
        ProductDescriptionTabsService $tabsService,
        string $action,
        string $scope
    ): void {
        if ($action === 'preview_tabs') {
            $sampleKey = trim((string) ($_GET['sample'] ?? 'default'));
            $ctx = self::sampleContext($sampleKey, $scope);
            ApiBootstrap::json([
                'success' => true,
                'data' => $tabsService->preview($ctx),
                'sample' => $sampleKey,
                'scope' => $scope,
            ]);
        }

        if ($action === 'preview') {
            $channel = (string) ($_GET['channel'] ?? ProductCardFormationService::CHANNEL_WEBSITE);
            $sampleKey = trim((string) ($_GET['sample'] ?? 'default'));
            $ctx = self::sampleContext($sampleKey, $scope);
            ApiBootstrap::json([
                'success' => true,
                'data' => $service->preview($channel, $ctx),
                'sample' => $sampleKey,
                'scope' => $scope,
            ]);
        }

        if ($action === 'editor') {
            ApiBootstrap::json([
                'success' => true,
                'data' => $service->loadEditorConfig($scope),
            ]);
        }

        $categories = [];
        try {
            $facets = new ProductFacetsService();
            foreach ($facets->getCategories() as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $categories[] = [
                    'label' => $label,
                    'slug' => (string) ($row['slug'] ?? ''),
                    'count' => (int) ($row['count'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            error_log('[product_card_formation] categories: ' . $e->getMessage());
        }

        ApiBootstrap::json([
            'success' => true,
            'data' => [
                'config' => $service->load(),
                'editor' => $service->loadEditorConfig($scope),
                'scope' => $scope,
                'saved_scopes' => $service->listSavedScopes(),
                'categories' => $categories,
                'segments' => ProductCardFormationService::segmentCatalog(),
                'channels' => ProductCardFormationService::channelCatalog(),
                'samples' => self::samples(),
                'tab_fields' => ProductDescriptionTabsService::fieldCatalog(),
                'tab_columns' => ProductDescriptionTabsService::columnCatalog(),
                'tab_formats' => ProductDescriptionTabsService::formatCatalog(),
                'tab_chart_sources' => ProductTabChartService::sourceCatalog(),
                'tab_chart_types' => ProductTabChartService::typeCatalog(),
                'tab_chart_periods' => ProductTabChartService::periodCatalog(),
                'tab_chart_metrics' => ProductTabChartService::metricCatalog(),
            ],
        ]);
    }

    private static function handlePost(
        ProductCardFormationService $service,
        ProductDescriptionTabsService $tabsService,
        string $action,
        string $scope
    ): void {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $action = trim((string) ($payload['action'] ?? $action));

        if ($action === 'save') {
            $config = $payload['config'] ?? null;
            if (!is_array($config)) {
                ApiBootstrap::json(['success' => false, 'message' => 'Config invalid.'], 400);
            }
            $scope = trim((string) ($payload['scope'] ?? 'default'));
            $saved = $service->saveEditorConfig($scope, $config);
            $editor = $service->loadEditorConfig($scope);
            ApiBootstrap::json([
                'success' => true,
                'message' => self::saveMessage($scope, (string) ($editor['scope_label'] ?? '')),
                'data' => [
                    'config' => $saved,
                    'editor' => $editor,
                    'scope' => $scope,
                    'saved_scopes' => $service->listSavedScopes(),
                ],
            ]);
        }

        if ($action === 'preview_tabs') {
            $sampleKey = trim((string) ($payload['sample'] ?? 'default'));
            $scope = trim((string) ($payload['scope'] ?? 'default'));
            $ctx = self::sampleContext($sampleKey, $scope);
            $config = $payload['config'] ?? null;
            if (is_array($config)) {
                $merged = self::buildPreviewConfig($service, $scope, $config);
                $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bpa_pcf_tabs_' . bin2hex(random_bytes(8)) . '.json';
                file_put_contents($tmp, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                try {
                    $draftTabs = new ProductDescriptionTabsService(new ProductCardFormationService($tmp));
                    $result = $draftTabs->preview($ctx);
                } finally {
                    if (is_file($tmp)) {
                        @unlink($tmp);
                    }
                }
            } else {
                $result = $tabsService->preview($ctx);
            }
            ApiBootstrap::json(['success' => true, 'data' => $result, 'scope' => $scope]);
        }

        if ($action === 'preview') {
            $channel = (string) ($payload['channel'] ?? ProductCardFormationService::CHANNEL_WEBSITE);
            $sampleKey = trim((string) ($payload['sample'] ?? 'default'));
            $scope = trim((string) ($payload['scope'] ?? 'default'));
            $ctx = is_array($payload['context'] ?? null)
                ? $payload['context']
                : self::sampleContext($sampleKey, $scope);
            $config = $payload['config'] ?? null;
            if (is_array($config)) {
                $merged = self::buildPreviewConfig($service, $scope, $config);
                $result = (new ProductCardFormationService(self::writeTempConfig($merged)))->preview($channel, $ctx);
            } else {
                $result = $service->preview($channel, $ctx);
            }
            ApiBootstrap::json(['success' => true, 'data' => $result, 'scope' => $scope]);
        }

        if ($action === 'reset') {
            $channel = trim((string) ($payload['channel'] ?? ''));
            $scope = trim((string) ($payload['scope'] ?? 'default'));
            $scopePart = trim((string) ($payload['scope_part'] ?? 'channel'));
            if ($scope === 'tabs') {
                $scopePart = 'tabs';
                $scope = 'default';
            }
            $defaults = ProductCardFormationService::defaultConfig();
            $current = $service->load();
            $parsed = ProductCardFormationService::parseScope($scope);

            if ($parsed['type'] !== 'default') {
                if (!isset($current['scopes']) || !is_array($current['scopes'])) {
                    $current['scopes'] = [];
                }
                if ($scopePart === 'tabs') {
                    unset($current['scopes'][$parsed['scope_id']]['product_tabs']);
                } elseif ($channel !== '' && $parsed['type'] === 'project') {
                    unset($current['scopes'][$parsed['scope_id']]['channels'][$channel]);
                } elseif ($channel !== '') {
                    unset($current['scopes'][$parsed['scope_id']]['channels'][$channel]);
                } else {
                    unset($current['scopes'][$parsed['scope_id']]);
                }
                if (isset($current['scopes'][$parsed['scope_id']]) && $current['scopes'][$parsed['scope_id']] === []) {
                    unset($current['scopes'][$parsed['scope_id']]);
                }
            } elseif ($scopePart === 'tabs') {
                $current['product_tabs'] = $defaults['product_tabs'];
            } elseif ($channel !== '' && isset($defaults['channels'][$channel])) {
                $current['channels'][$channel] = $defaults['channels'][$channel];
            } else {
                $current = $defaults;
            }

            $saved = $service->save($current);
            $editor = $service->loadEditorConfig($scope);
            ApiBootstrap::json([
                'success' => true,
                'message' => match (true) {
                    $scopePart === 'tabs' => 'Tab-uri resetate la implicit.',
                    $channel !== '' => 'Canal resetat la implicit.',
                    $parsed['type'] !== 'default' => 'Profil resetat.',
                    default => 'Toate regulile resetate.',
                },
                'data' => [
                    'config' => $saved,
                    'editor' => $editor,
                    'scope' => $scope,
                    'saved_scopes' => $service->listSavedScopes(),
                ],
            ]);
        }

        ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 400);
    }

    /** @return array<string, string> */
    private static function samples(): array
    {
        return [
            'default' => 'Cu vehicul manual (Audi A3)',
            'fara_vehicul' => 'Fără marcă/model (doar compat TecDoc)',
            'oem_only' => 'Consumabil — filtru ulei',
        ];
    }

    /** @return array<string, mixed> */
    private static function sampleContext(string $sampleKey, string $scope = 'default'): array
    {
        $samples = [
            'default' => ProductCardFormationService::sampleContext(),
            'fara_vehicul' => array_merge(ProductCardFormationService::sampleContext(), [
                'pMarca' => '',
                'pModel' => '',
                'pMotorizare' => '',
            ]),
            'oem_only' => [
                'piece_name' => 'Filtru ulei',
                'brand' => 'MANN',
                'code' => 'HU 718 X',
                'pMarca' => '',
                'pModel' => '',
                'pMotorizare' => '',
                'specs_text' => '',
                'compat_brands' => ['VW', 'AUDI'],
                'compat_series' => ['Golf', 'A3'],
                'entries' => [],
            ],
        ];

        $ctx = $samples[$sampleKey] ?? $samples['default'];
        $parsed = ProductCardFormationService::parseScope($scope);
        if ($parsed['type'] === 'category' && $parsed['key'] !== '') {
            $ctx['pCategory'] = $parsed['key'];
        }

        return $ctx;
    }

    /** @param array<string, mixed> $editorConfig */
    private static function buildPreviewConfig(
        ProductCardFormationService $service,
        string $scope,
        array $editorConfig
    ): array {
        $full = $service->load();
        $parsed = ProductCardFormationService::parseScope($scope);

        if ($parsed['type'] === 'default') {
            return $service->normalizeIncomingConfig($editorConfig);
        }

        if (!isset($full['scopes']) || !is_array($full['scopes'])) {
            $full['scopes'] = [];
        }

        $slice = [
            'channels' => is_array($editorConfig['channels'] ?? null) ? $editorConfig['channels'] : [],
            'product_tabs' => is_array($editorConfig['product_tabs'] ?? null) ? $editorConfig['product_tabs'] : [],
        ];
        $full['scopes'][$parsed['scope_id']] = $slice;

        return $service->normalizeIncomingConfig($full);
    }

    /** @param array<string, mixed> $config */
    private static function writeTempConfig(array $config): string
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bpa_pcf_' . bin2hex(random_bytes(8)) . '.json';
        file_put_contents($tmp, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $tmp;
    }

    private static function saveMessage(string $scope, string $label): string
    {
        $parsed = ProductCardFormationService::parseScope($scope);
        if ($parsed['type'] === 'category') {
            return 'Structură salvată pentru categoria „' . ($label !== '' ? $label : $parsed['key']) . '”.';
        }
        if ($parsed['type'] === 'project') {
            return 'Structură salvată pentru proiectul „' . ($label !== '' ? $label : $parsed['key']) . '”.';
        }

        return 'Reguli implicite salvate.';
    }
}
