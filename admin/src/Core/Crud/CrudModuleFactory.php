<?php

declare(strict_types=1);

namespace Besoiu\Core\Crud;

use Besoiu\Controllers\AdaosComercial\AdaosComercial;
use Besoiu\Services\AdaosComercial\AdaosComercialService;
use Besoiu\Controllers\Alerts\Alerts;
use Besoiu\Controllers\Blog\Blog;
use Besoiu\Controllers\Bots\Bots;
use Besoiu\Services\BotsService;
use Besoiu\Controllers\Categorii\Categorii;
use Besoiu\Services\CategoriiService;
use Besoiu\Services\PieseAutoCategoryCatalog;
use Besoiu\Core\Client\ClientHooks;
use Besoiu\Controllers\Comenzi\Comenzi;
use Besoiu\Services\Orders\ComenziService;
use Besoiu\Controllers\Cron\Cron;
use Besoiu\Controllers\CrossReference\CrossReference;
use Besoiu\Controllers\Facturi\Facturi;
use Besoiu\Services\Fulfillment\FacturiService;
use Besoiu\Controllers\Livrare\Livrare;
use Besoiu\Services\Fulfillment\LivrareService;
use Besoiu\Controllers\Marketplace\Marketplace;
use Besoiu\Services\Marketplace\MarketplaceService;
use Besoiu\Controllers\Messages\Messages;
use Besoiu\Services\MessagesService;
use Besoiu\Controllers\Report\Report;
use Besoiu\Controllers\Scan\Scan;
use Besoiu\Controllers\SearchLogs\SearchLogsCrud;
use Besoiu\Controllers\Settings\Settings;
use Besoiu\Controllers\Website\Website;
use Besoiu\Core\Bots\BotsModel;
use Besoiu\Core\Comenzi\ComenziModel;
use Besoiu\Core\Facturi\FacturiModel;
use Besoiu\Core\Livrare\LivrareModel;
use Besoiu\Core\Marketplace\MarketplaceModel;
use Besoiu\Core\Messages\MessagesModel;
use Besoiu\Core\Module\ModuleGate;
use InvalidArgumentException;

/**
 * Instanțiere Controller + Service + Model pentru endpoint-urile crudu.
 * Modulele „extended” (ex-Legacy) au hartă de acțiuni custom.
 */
final class CrudModuleFactory
{
    /** @return array{label: string, session_key: string, mode?: string, actions?: array<string, string>} */
    public static function modernModuleMeta(string $moduleKey): array
    {
        if (!ModuleGate::crudAllowed($moduleKey)) {
            throw new InvalidArgumentException('Modul opțional dezactivat: ' . $moduleKey);
        }

        $meta = self::allModules()[$moduleKey] ?? null;
        if ($meta === null) {
            throw new InvalidArgumentException('Modul CRUD necunoscut: ' . $moduleKey);
        }

        return $meta;
    }

    public static function createModernController(string $moduleKey): object
    {
        if (!ModuleGate::crudAllowed($moduleKey)) {
            throw new InvalidArgumentException('Modul opțional dezactivat: ' . $moduleKey);
        }

        switch ($moduleKey) {
            case 'Comenzi':
                return new Comenzi(new ComenziService(new ComenziModel()));
            case 'Clienti':
                return ClientHooks::controller();
            case 'Bots':
                return new Bots(new BotsService(new BotsModel()));
            case 'Livrare':
                return new Livrare(new LivrareService(new LivrareModel()));
            case 'Facturi':
                return new Facturi(new FacturiService(new FacturiModel()));
            case 'Messages':
                return new Messages(new MessagesService(new MessagesModel()));
            case 'Marketplace':
                return new Marketplace(new MarketplaceService(new MarketplaceModel()));
            case 'Alerts':
                return new Alerts();
            case 'Scan':
                return new Scan();
            case 'Cron':
                return new Cron();
            case 'Report':
                return new Report();
            case 'Settings':
                return new Settings();
            case 'CrossReference':
                return new CrossReference();
            case 'SearchLogsCrud':
                return new SearchLogsCrud();
            case 'Blog':
                return new Blog();
            case 'Categorii':
                return new Categorii();
            case 'Website':
                return new Website();
            case 'AdaosComercial':
                return new AdaosComercial();
            default:
                throw new InvalidArgumentException('Modul CRUD necunoscut: ' . $moduleKey);
        }
    }

    /**
     * Handler special pentru acțiuni care nu sunt metode pe Controller
     * (ex. Categorii list/tree/import_tecdoc).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null null = folosește metoda din hartă
     */
    public static function handleExtendedAction(string $moduleKey, string $action, array $data): ?array
    {
        if ($moduleKey === 'Categorii') {
            return self::handleCategoriiExtended($action, $data);
        }
        if ($moduleKey === 'AdaosComercial' && $action === 'list') {
            return ['success' => true, 'data' => (new AdaosComercialService())->getAll()];
        }

        return null;
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    private static function handleCategoriiExtended(string $action, array $data): ?array
    {
        $service = new CategoriiService();

        if ($action === 'list') {
            $filterType = $data['filter_type'] ?? '';
            $items = $filterType ? $service->getByType((string) $filterType) : $service->getAll();

            return ['success' => true, 'data' => $items];
        }
        if ($action === 'tree') {
            return ['success' => true, 'data' => $service->getTree()];
        }
        if ($action === 'import_tecdoc') {
            if (!$service->isTecdocStructureImportEnabled()) {
                return [
                    'success' => false,
                    'reference_only' => true,
                    'message' => $service->tecdocStructureImportBlockedMessage(),
                ];
            }
            $items = $data['items'] ?? [];
            if (empty($items) || !is_array($items)) {
                return ['success' => false, 'message' => 'Nu s-au trimis categorii.'];
            }
            $imported = 0;
            foreach ($items as $item) {
                $label = trim((string) ($item['label'] ?? ''));
                $tecdocId = (int) ($item['tecdoc_id'] ?? 0);
                if ($label === '' || $tecdocId <= 0) {
                    continue;
                }
                $slug = mb_strtolower($label, 'UTF-8');
                $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? $slug;
                $slug = trim($slug, '-');
                $payload = [
                    'label' => $label,
                    'slug' => $slug,
                    'type' => 'categorie',
                    'tecdoc_id' => $tecdocId,
                    'is_active' => 1,
                    'sort_order' => $imported * 10,
                    'parent_id' => null,
                ];
                if (!empty($item['parent_tecdoc_id'])) {
                    $payload['meta'] = json_encode(['parent_tecdoc_id' => (int) $item['parent_tecdoc_id']]);
                }
                if ($service->create($payload)) {
                    $imported++;
                }
            }

            if ($imported > 0) {
                $service->syncTaxonomy(null);
            }

            return [
                'success' => true,
                'message' => $imported . ' categorii importate din TecDoc și sincronizate.',
                'count' => $imported,
            ];
        }
        if ($action === 'sync_taxonomy') {
            $controller = new Categorii();

            return $controller->syncTaxonomy();
        }
        if ($action === 'pieseauto_stats') {
            return [
                'success' => true,
                'stats' => $service->getPieseAutoCatalogStats(),
            ];
        }
        if ($action === 'import_pieseauto') {
            $onlyMissing = empty($data['force_update']);
            $result = $service->importPieseAutoCatalog($onlyMissing);

            return [
                'success' => true,
                'message' => sprintf(
                    'Import PieseAuto: %d categorii, %d subcategorii noi; %d actualizate; %d sărite.',
                    $result['imported_categories'],
                    $result['imported_subcategories'],
                    $result['updated'],
                    $result['skipped']
                ),
                'result' => $result,
            ];
        }
        if ($action === 'parse_pieseauto_html') {
            $html = trim((string) ($data['html'] ?? ''));
            if ($html === '') {
                foreach ([
                    PieseAutoCategoryCatalog::htmlSnapshotPath(),
                    PieseAutoCategoryCatalog::projectRoot() . '/tmp_pieseauto_user.html',
                ] as $candidate) {
                    if (is_file($candidate)) {
                        $html = (string) file_get_contents($candidate);
                        break;
                    }
                }
            }
            if ($html === '') {
                return ['success' => false, 'message' => 'Lipsește HTML-ul catalogului PieseAuto.'];
            }

            $parsed = $service->parsePieseAutoHtml($html);

            return [
                'success' => true,
                'message' => sprintf(
                    'Catalog parsat: %d categorii, %d subcategorii.',
                    $parsed['categories'],
                    $parsed['subcategories']
                ),
                'parsed' => $parsed,
                'stats' => $service->getPieseAutoCatalogStats(),
            ];
        }
        if ($action === 'match_category') {
            $controller = new Categorii();

            return $controller->matchCategory($data);
        }
        if ($action === 'ollama_category_status') {
            return [
                'success' => true,
                'status' => $service->categoryMatchOllamaStatus(),
            ];
        }
        if ($action === 'preview_besoiu_tree') {
            return (new Categorii())->previewBesoiuTree($data);
        }
        if ($action === 'import_besoiu_tree') {
            return (new Categorii())->importBesoiuTree($data);
        }
        if ($action === 'purge_alternate_catalog') {
            return (new Categorii())->purgeAlternateCatalog();
        }
        if ($action === 'upload_besoiu_excel') {
            return (new Categorii())->uploadBesoiuExcel($_FILES);
        }

        return null;
    }

    /** @return array<string, array{label: string, session_key: string, mode?: string, actions?: array<string, string>}> */
    private static function allModules(): array
    {
        return [
            'Comenzi' => ['label' => 'Comandă', 'session_key' => 'comenzi', 'mode' => 'standard'],
            'Clienti' => ['label' => 'Client', 'session_key' => 'clienti', 'mode' => 'standard'],
            'Bots' => ['label' => 'Bot', 'session_key' => 'bots', 'mode' => 'standard'],
            'Livrare' => ['label' => 'Livrare', 'session_key' => 'livrare', 'mode' => 'standard'],
            'Facturi' => ['label' => 'Factură', 'session_key' => 'facturi', 'mode' => 'standard'],
            'Messages' => ['label' => 'Mesaj', 'session_key' => 'messages', 'mode' => 'standard'],
            'Marketplace' => ['label' => 'Marketplace', 'session_key' => 'marketplace', 'mode' => 'standard'],
            'Alerts' => ['label' => 'Alert', 'session_key' => 'alerts', 'mode' => 'standard'],
            'Scan' => ['label' => 'Scan', 'session_key' => 'scan', 'mode' => 'standard'],
            'Cron' => ['label' => 'Cron', 'session_key' => 'cron', 'mode' => 'standard'],
            'Report' => ['label' => 'Raport', 'session_key' => 'report', 'mode' => 'standard'],
            'Settings' => ['label' => 'Setare', 'session_key' => 'settings', 'mode' => 'standard'],
            'CrossReference' => ['label' => 'Cross-reference', 'session_key' => 'cross_reference', 'mode' => 'standard'],
            'SearchLogsCrud' => ['label' => 'Search logs', 'session_key' => 'search_logs', 'mode' => 'standard'],
            'Blog' => [
                'label' => 'Articol',
                'session_key' => 'blog',
                'mode' => 'extended',
                'actions' => [
                    'add' => 'add',
                    'edit' => 'edit',
                    'delete' => 'delete',
                    'list' => 'list',
                ],
            ],
            'Categorii' => [
                'label' => 'Categorie',
                'session_key' => 'categorii',
                'mode' => 'extended',
                'actions' => [
                    'add' => 'add',
                    'edit' => 'edit',
                    'delete' => 'delete',
                    'toggle' => 'toggleActive',
                    'import_defaults' => 'importDefaults',
                    'backfill_icons' => 'backfillIcons',
                    'import_pieseauto' => '__extended__',
                    'parse_pieseauto_html' => '__extended__',
                    'pieseauto_stats' => '__extended__',
                    'match_category' => '__extended__',
                    'ollama_category_status' => '__extended__',
                    'list' => '__extended__',
                    'tree' => '__extended__',
                    'import_tecdoc' => '__extended__',
                    'sync_taxonomy' => '__extended__',
                ],
            ],
            'Website' => [
                'label' => 'Pagină site',
                'session_key' => 'website',
                'mode' => 'extended',
                'actions' => [
                    'save' => 'save',
                    'list' => 'list',
                    'create' => 'create',
                    'delete' => 'delete',
                    'toggle_active' => 'toggleActive',
                ],
            ],
            'AdaosComercial' => [
                'label' => 'Adaos comercial',
                'session_key' => 'adaos_comercial',
                'mode' => 'extended',
                'actions' => [
                    'list' => '__extended__',
                    'save' => 'save',
                    'delete' => 'delete',
                    'toggle' => 'toggle',
                    'preview' => 'preview',
                    'apply' => 'apply',
                    'simulate_product' => 'simulateProduct',
                    'save_vat' => 'saveVatSettings',
                    'save_price_round' => 'saveGlobalPriceRoundSettings',
                    'save_global_markup' => 'saveGlobalCommercialMarkupSettings',
                    'reapply_all' => 'reapplyAll',
                    'price_formation_trace' => 'priceFormationTrace',
                    'price_trace' => 'priceFormationTrace',
                ],
            ],
        ];
    }
}
