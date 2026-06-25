<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Scraper\EpiesaScraperService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\ScraperHubService;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $service = new EpiesaScraperService();
    $hub = new ScraperHubService();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $view = (string) ($_GET['view'] ?? 'latest');

        if ($view === 'stats') {
            ApiBootstrap::json(['success' => true, 'data' => $service->stats()]);
        }

        if ($view === 'catalog') {
            $cat = trim((string) ($_GET['category'] ?? ''));
            ApiBootstrap::json(['success' => true, 'data' => $service->catalog($cat !== '' ? $cat : null)]);
        }

        if ($view === 'logs') {
            $lines = max(20, min(500, (int) ($_GET['lines'] ?? 120)));
            ApiBootstrap::json(['success' => true, 'data' => ['log' => $service->logs($lines)]]);
        }

        if ($view === 'hub') {
            ApiBootstrap::json(['success' => true, 'data' => $hub->dashboard()]);
        }

        if ($view === 'sources') {
            ApiBootstrap::json(['success' => true, 'data' => ['cards' => $hub->listSourceCards()]]);
        }

        if ($view === 'source') {
            $sid = trim((string) ($_GET['source_id'] ?? ''));
            if ($sid === '') {
                throw new InvalidArgumentException('Lipsește source_id.');
            }
            ApiBootstrap::json(['success' => true, 'data' => $hub->getSourceConfig($sid)]);
        }

        if ($view === 'step_catalog') {
            ApiBootstrap::json(['success' => true, 'data' => $hub->getStepCatalog()]);
        }

        if ($view === 'integration') {
            ApiBootstrap::json(['success' => true, 'data' => $hub->getIntegrationConfig()]);
        }

        if ($view === 'image_proxy') {
            require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperImageProxy.php';
            \ScraperImageProxy::stream((string) ($_GET['url'] ?? ''));

            return;
        }

        if ($view === 'rules') {
            ApiBootstrap::json(['success' => true, 'data' => $hub->getRules()]);
        }

        $data = $service->latest();
        ApiBootstrap::json([
            'success' => true,
            'message' => $data['message'] ?? 'OK',
            'data'    => $data,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON invalid.');
    }

    $action = (string) ($payload['action'] ?? $payload['type_product'] ?? 'scan');

    if ($action === 'latest') {
        $data = $service->latest();
        ApiBootstrap::json(['success' => true, 'data' => $data]);
    }

    if ($action === 'stats') {
        ApiBootstrap::json(['success' => true, 'data' => $service->stats()]);
    }

    if ($action === 'catalog') {
        $cat = trim((string) ($payload['category'] ?? ''));
        ApiBootstrap::json(['success' => true, 'data' => $service->catalog($cat !== '' ? $cat : null)]);
    }

    if ($action === 'scan') {
        $data = $service->runScan($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => $data['message'] ?? 'Scan finalizat.',
            'data'    => $data,
        ]);
    }

    if ($action === 'cache_images') {
        $data = $service->cacheImages();
        ApiBootstrap::json([
            'success' => true,
            'message' => $data['message'] ?? 'Imagini actualizate.',
            'data'    => $data,
        ]);
    }

    if ($action === 'source_save') {
        $sid = trim((string) ($payload['source_id'] ?? ''));
        if ($sid === '') {
            throw new InvalidArgumentException('Lipsește source_id.');
        }
        $cfg = is_array($payload['config'] ?? null) ? $payload['config'] : $payload;
        unset($cfg['source_id'], $cfg['action']);
        $saved = $hub->saveSourceConfig($sid, $cfg);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Configurare salvată pentru ' . $sid,
            'data' => $saved,
        ]);
    }

    if ($action === 'source_test') {
        $sid = trim((string) ($payload['source_id'] ?? ''));
        if ($sid === '') {
            throw new InvalidArgumentException('Lipsește source_id.');
        }
        $data = $hub->testSource($sid, $payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => $data['items_count'] . ' rezultate · scor ' . ($data['analysis']['quality_score'] ?? 0),
            'data' => $data,
        ]);
    }

    if ($action === 'source_create') {
        $created = $hub->createSource($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Sursă creată: ' . ($created['id'] ?? ''),
            'data' => $created,
        ]);
    }

    if ($action === 'source_delete') {
        $sid = trim((string) ($payload['source_id'] ?? ''));
        if ($sid === '') {
            throw new InvalidArgumentException('Lipsește source_id.');
        }
        $hub->deleteSource($sid);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Sursa ștearsă peste tot: scraper, pipeline, cron și image-search-sources.php.',
            'data' => ['source_id' => $sid],
        ]);
    }

    if ($action === 'source_restore_presets') {
        $added = $hub->restoreBuiltinPresets();
        ApiBootstrap::json([
            'success' => true,
            'message' => $added > 0 ? "Au fost readăugate {$added} surse preset." : 'Toate preseturile există deja.',
            'data' => ['added' => $added, 'cards' => $hub->listSourceCards()],
        ]);
    }

    if ($action === 'sync_all_sources') {
        require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperImageSourcesSync.php';
        \ScraperImageSourcesSync::rebuild();
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Sincronizare completă: image-search-sources.php + pipeline + cron.',
            'data' => ['active' => \ScraperImageSourcesSync::activeSourceIds()],
        ]);
    }

    if ($action === 'hub_save') {
        $saved = $hub->saveConfig($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Configurare salvată.',
            'data'    => $saved,
        ]);
    }

    if ($action === 'test_fetch') {
        $data = $hub->testFetch($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Fetch OK (' . ($data['html_length'] ?? 0) . ' bytes).',
            'data'    => $data,
        ]);
    }

    if ($action === 'agent_analyze_html') {
        $data = $hub->agentAnalyzeHtml($payload);
        ApiBootstrap::json([
            'success' => !empty($data['ok']) || !empty($data['selectors']['block']),
            'message' => !empty($data['items_count'])
                ? 'Agent AI: ' . $data['items_count'] . ' produs(e) — ' . ($data['mode'] ?? 'analiză')
                : ((string) ($data['explanation_ro'] ?? $data['error'] ?? 'Agent AI a analizat HTML.')),
            'data' => $data,
        ]);
    }

    if ($action === 'analyze_saved_html') {
        $data = $hub->analyzeSavedHtml($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Analiză HTML: ' . ($data['items_count'] ?? 0) . ' produs(e), '
                . (($data['diagnostics']['blocks_found'] ?? 0)) . ' bloc(uri) găsite.',
            'data' => $data,
        ]);
    }

    if ($action === 'test_parse') {
        $data = $hub->testParse($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Parsare: ' . ($data['parsed_count'] ?? 0) . ' item(i).',
            'data'    => $data,
        ]);
    }

    if ($action === 'test_pipeline') {
        $data = $hub->testPipeline($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => $data['winner'] !== null ? 'Pipeline: sursă câștigătoare găsită.' : 'Pipeline: fără rezultat.',
            'data'    => $data,
        ]);
    }

    if ($action === 'integration_save') {
        $saved = $hub->saveIntegrationConfig($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Pipeline imagini salvat — cron și import folosesc planurile 1→2→3.',
            'data' => $saved,
        ]);
    }

    if ($action === 'test_image_pipeline') {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        $data = $hub->testImagePipeline($payload);
        $hit = is_array($data['hit'] ?? null) ? $data['hit'] : null;
        ApiBootstrap::json([
            'success' => true,
            'message' => $hit && trim((string) ($hit['url'] ?? '')) !== ''
                ? 'Imagine găsită la plan ' . ($data['tried'][count($data['tried']) - 1]['tier'] ?? '?')
                : 'Nicio sursă nu a returnat imagine.',
            'data' => $data,
        ]);
    }

    if ($action === 'test_image_pipeline_step') {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        $data = $hub->testImagePipelineStep($payload);
        $hit = is_array($data['hit'] ?? null) ? $data['hit'] : null;
        $tried = is_array($data['tried'] ?? null) ? $data['tried'] : [];
        ApiBootstrap::json([
            'success' => true,
            'message' => $hit && trim((string) ($hit['url'] ?? '')) !== ''
                ? 'Plan ' . ($tried['tier'] ?? '?') . ': imagine găsită.'
                : 'Plan ' . ($tried['tier'] ?? '?') . ': ' . ($tried['message'] ?? 'fără rezultat'),
            'data' => $data,
        ]);
    }

    if ($action === 'analyze_agent') {
        $data = $hub->analyzeAgent($payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Analiză finalizată.',
            'data'    => $data,
        ]);
    }

    ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 422);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('scraper_endpoint', $exception);
}
