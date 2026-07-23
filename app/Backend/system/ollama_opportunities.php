<?php

declare(strict_types=1);

require_once __DIR__ . '/ollama_health.php';
require_once __DIR__ . '/ollama_error_log.php';

/**
 * Cele 20 probleme → 20 oportunități testabile (checkpoint-uri).
 *
 * @return list<array{id:string,problem:int,title:string,category:string,description:string}>
 */
function ollama_opportunities_registry(): array
{
    return [
        ['id' => 'opp_01_chat_widget', 'problem' => 1, 'title' => 'Chat widget public', 'category' => 'A', 'description' => 'Endpoint robot/chat_widget_api.php + catalog'],
        ['id' => 'opp_02_orchestrator', 'problem' => 2, 'title' => 'Orchestrator shop chat', 'category' => 'A', 'description' => 'PublicShopChatOrchestrator apelat din flux live'],
        ['id' => 'opp_03_comunicare', 'problem' => 3, 'title' => 'Modul Comunicare', 'category' => 'A', 'description' => 'API comunicare + RAG operator'],
        ['id' => 'opp_04_whatsapp', 'problem' => 4, 'title' => 'WhatsApp / webhook', 'category' => 'A', 'description' => 'Webhook multicanal → Ollama'],
        ['id' => 'opp_05_supervisor', 'problem' => 5, 'title' => 'Supervisor AI', 'category' => 'A', 'description' => 'Serviciu supervisor + ciclu autonom'],
        ['id' => 'opp_06_bots_ui', 'problem' => 6, 'title' => 'UI Roboți / agenți', 'category' => 'A', 'description' => 'Panou admin + API agenți'],
        ['id' => 'opp_07_module_tests', 'problem' => 7, 'title' => 'Teste per modul', 'category' => 'A', 'description' => 'ERP, Import, Scraper, Orchestr, Python'],
        ['id' => 'opp_08_shop_bridge', 'problem' => 8, 'title' => 'Bridge shop → agenți', 'category' => 'A', 'description' => 'ShopChatOllamaAgentBridge în flux live'],
        ['id' => 'opp_09_clients_unified', 'problem' => 9, 'title' => 'Clienți HTTP unificați', 'category' => 'B', 'description' => 'Registry integrări + telemetrie comună'],
        ['id' => 'opp_10_profiles', 'problem' => 10, 'title' => 'Profile LLM aliniate', 'category' => 'B', 'description' => 'Parametri comparabili între module'],
        ['id' => 'opp_11_env_sync', 'problem' => 11, 'title' => 'Env sincronizat', 'category' => 'B', 'description' => 'Fără conflicte .env / settings.json'],
        ['id' => 'opp_12_router_local', 'problem' => 12, 'title' => 'Router local vs hybrid', 'category' => 'B', 'description' => 'Transparență provider (Ollama vs cloud)'],
        ['id' => 'opp_13_python_bridge', 'problem' => 13, 'title' => 'Python LangGraph', 'category' => 'B', 'description' => 'Nod llm.py + env partajat'],
        ['id' => 'opp_14_catalog_rag', 'problem' => 14, 'title' => 'Catalog RAG SQL', 'category' => 'B', 'description' => 'Căutare catalog fără LLM'],
        ['id' => 'opp_15_fast_agents', 'problem' => 15, 'title' => 'Agenți — test rapid', 'category' => 'C', 'description' => 'Smoke test Ollama < 45s'],
        ['id' => 'opp_16_agent_imagini', 'problem' => 16, 'title' => 'Agent Imagini', 'category' => 'C', 'description' => 'Vision + randomn_id pentru audit real'],
        ['id' => 'opp_17_agent_produse', 'problem' => 17, 'title' => 'Agent Produse RAG', 'category' => 'C', 'description' => 'Răspuns din catalog SQL sau LLM'],
        ['id' => 'opp_18_agents_scope', 'problem' => 18, 'title' => '4 agenți instalați', 'category' => 'C', 'description' => 'Registry agenți + prompturi'],
        ['id' => 'opp_19_ollama_daemon', 'problem' => 19, 'title' => 'Ollama daemon activ', 'category' => 'D', 'description' => 'Ping /api/tags + modele instalate'],
        ['id' => 'opp_20_health_contract', 'problem' => 20, 'title' => 'Health + jurnal erori', 'category' => 'D', 'description' => 'Telemetrie, JSONL, verify all'],
    ];
}

/**
 * @param array{live?:bool,ids?:list<string>} $options
 * @return array{generated_at:string,summary:array{total:int,ok:int,warn:int,fail:int},items:list<array<string,mixed>>}
 */
function ollama_opportunities_run_all(?string $projectRoot = null, array $options = []): array
{
    $projectRoot = ollama_site_root($projectRoot);
    $live = !empty($options['live']);
    $onlyIds = $options['ids'] ?? null;
    $items = [];

    foreach (ollama_opportunities_registry() as $row) {
        if (is_array($onlyIds) && $onlyIds !== [] && !in_array($row['id'], $onlyIds, true)) {
            continue;
        }
        $items[] = ollama_opportunities_run_one($row['id'], $projectRoot, $live);
    }

    $ok = 0;
    $warn = 0;
    $fail = 0;
    foreach ($items as $item) {
        match ($item['status'] ?? 'fail') {
            'ok' => $ok++,
            'warn' => $warn++,
            default => $fail++,
        };
    }

    return [
        'generated_at' => date('c'),
        'summary' => [
            'total' => count($items),
            'ok' => $ok,
            'warn' => $warn,
            'fail' => $fail,
        ],
        'items' => $items,
    ];
}

/** @return array<string, mixed> */
function ollama_opportunities_run_one(string $id, ?string $projectRoot = null, bool $live = false): array
{
    $projectRoot = ollama_site_root($projectRoot);
    $meta = null;
    foreach (ollama_opportunities_registry() as $row) {
        if ($row['id'] === $id) {
            $meta = $row;
            break;
        }
    }
    if ($meta === null) {
        return ollama_opportunity_result($id, 'fail', 'Checkpoint necunoscut.', []);
    }

    $started = hrtime(true);
    try {
        $result = match ($id) {
            'opp_01_chat_widget' => ollama_opp_chat_widget($projectRoot),
            'opp_02_orchestrator' => ollama_opp_orchestrator($projectRoot),
            'opp_03_comunicare' => ollama_opp_comunicare($projectRoot),
            'opp_04_whatsapp' => ollama_opp_whatsapp($projectRoot),
            'opp_05_supervisor' => ollama_opp_supervisor($projectRoot),
            'opp_06_bots_ui' => ollama_opp_bots_ui($projectRoot),
            'opp_07_module_tests' => ollama_opp_module_tests($projectRoot, $live),
            'opp_08_shop_bridge' => ollama_opp_shop_bridge($projectRoot, $live),
            'opp_09_clients_unified' => ollama_opp_clients_unified($projectRoot),
            'opp_10_profiles' => ollama_opp_profiles($projectRoot),
            'opp_11_env_sync' => ollama_opp_env_sync($projectRoot),
            'opp_12_router_local' => ollama_opp_router_local($projectRoot),
            'opp_13_python_bridge' => ollama_opp_python_bridge($projectRoot),
            'opp_14_catalog_rag' => ollama_opp_catalog_rag($projectRoot),
            'opp_15_fast_agents' => ollama_opp_fast_agents($projectRoot, $live),
            'opp_16_agent_imagini' => ollama_opp_agent_imagini($projectRoot, $live),
            'opp_17_agent_produse' => ollama_opp_agent_produse($projectRoot),
            'opp_18_agents_scope' => ollama_opp_agents_scope($projectRoot),
            'opp_19_ollama_daemon' => ollama_opp_ollama_daemon($projectRoot),
            'opp_20_health_contract' => ollama_opp_health_contract($projectRoot),
            default => ['status' => 'fail', 'message' => 'Handler lipsă', 'details' => []],
        };
    } catch (Throwable $e) {
        $result = ['status' => 'fail', 'message' => $e->getMessage(), 'details' => []];
    }

    $latency = (int) round((hrtime(true) - $started) / 1_000_000);

    return ollama_opportunity_result(
        $id,
        (string) ($result['status'] ?? 'fail'),
        (string) ($result['message'] ?? ''),
        array_merge(
            ['problem' => $meta['problem'], 'title' => $meta['title'], 'category' => $meta['category'], 'latency_ms' => $latency],
            (array) ($result['details'] ?? [])
        )
    );
}

/** @param array<string, mixed> $details */
function ollama_opportunity_result(string $id, string $status, string $message, array $details = []): array
{
    return array_merge([
        'id' => $id,
        'status' => in_array($status, ['ok', 'warn', 'fail'], true) ? $status : 'fail',
        'ok' => $status === 'ok',
        'message' => $message,
    ], $details);
}

function ollama_opp_boot_admin(string $root): void
{
    static $booted = false;
    if ($booted) {
        return;
    }
    if (!defined('BESOIU_ROOT')) {
        define('BESOIU_ROOT', $root);
    }
    if (!defined('BESOIU_APP')) {
        define('BESOIU_APP', $root . '/app');
    }
    if (!defined('BESOIU_ADMIN')) {
        define('BESOIU_ADMIN', $root . '/admin');
    }
    if (!defined('BESOIU_BACKEND')) {
        define('BESOIU_BACKEND', $root . '/app/Backend');
    }
    $autoload = $root . '/admin/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    $booted = true;
}

function ollama_opp_pdo(string $root): ?\PDO
{
    ollama_opp_boot_admin($root);
    try {
        if (class_exists(\Config\Database::class) && \Config\Database::hasConnection()) {
            return \Config\Database::getDB();
        }
    } catch (Throwable) {
        // init below
    }
    $configPath = $root . '/admin/config/config.php';
    if (!is_file($configPath)) {
        return null;
    }
    try {
        $config = require $configPath;
        \Config\Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? ''),
            (string) ($config['db_pass'] ?? '')
        );

        return \Config\Database::getDB();
    } catch (Throwable) {
        return null;
    }
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_chat_widget(string $root): array
{
    $files = [
        'app/robot/chat_widget_api.php',
        'app/robot/products_catalog.php',
        'app/robot/bootstrap.php',
        'app/robot/public_shop_chat.php',
    ];
    $missing = [];
    foreach ($files as $rel) {
        if (!is_file($root . '/' . $rel)) {
            $missing[] = $rel;
        }
    }
    if ($missing !== []) {
        return ['status' => 'fail', 'message' => 'Lipsesc: ' . implode(', ', $missing), 'details' => ['missing' => $missing]];
    }

    $ht = @file_get_contents($root . '/.htaccess');
    $rewrite = is_string($ht) && str_contains($ht, 'robot/chat_widget_api');

    return [
        'status' => $rewrite ? 'ok' : 'warn',
        'message' => $rewrite
            ? 'Chat widget portat + rewrite activ.'
            : 'Fișiere OK, dar lipsește rewrite robot/chat_widget_api.php.',
        'details' => ['rewrite' => $rewrite],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_orchestrator(string $root): array
{
    $classFile = $root . '/app/Backend/src/Services/PublicShopChatOrchestrator.php';
    if (!is_file($classFile)) {
        return ['status' => 'fail', 'message' => 'PublicShopChatOrchestrator lipsă.', 'details' => []];
    }
    $widget = @file_get_contents($root . '/app/robot/chat_widget_api.php');
    $wired = is_string($widget) && str_contains($widget, '->handleMessage(');

    return [
        'status' => $wired ? 'ok' : 'warn',
        'message' => $wired ? 'Orchestrator legat în chat_widget_api.' : 'Clasă există, dar nu e apelată din widget.',
        'details' => ['wired_in_widget' => $wired],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_comunicare(string $root): array
{
    $path = $root . '/admin/public/api/comunicare_endpoint.php';
    if (!is_file($path)) {
        return ['status' => 'fail', 'message' => 'comunicare_endpoint.php lipsă.', 'details' => []];
    }
    $content = (string) file_get_contents($path);
    $stub = str_contains($content, 'modul neportat') || str_contains($content, 'indisponibil');

    return [
        'status' => $stub ? 'warn' : 'ok',
        'message' => $stub
            ? 'Stub Comunicare — RAG operator încă neportat (oportunitate viitoare).'
            : 'Modul Comunicare activ.',
        'details' => ['stub' => $stub],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_whatsapp(string $root): array
{
    $candidates = [
        'api/whatsapp_webhook.php',
        'robot/webhook.php',
        'app/robot/webhook.php',
    ];
    foreach ($candidates as $rel) {
        if (is_file($root . '/' . $rel)) {
            return ['status' => 'ok', 'message' => 'Webhook găsit: ' . $rel, 'details' => ['path' => $rel]];
        }
    }

    return [
        'status' => 'warn',
        'message' => 'WhatsApp/webhook neportat — marcat ca oportunitate (nu blocant panou).',
        'details' => ['ported' => false],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_supervisor(string $root): array
{
    $svc = $root . '/app/Backend/src/Services/AiSupervisor/AiSupervisorApiService.php';
    $endpoint = $root . '/admin/public/api/ai_agent_endpoint.php';
    if (!is_file($svc)) {
        return ['status' => 'warn', 'message' => 'Serviciu supervisor lipsă din backend.', 'details' => []];
    }
    $api = is_file($endpoint) ? (string) file_get_contents($endpoint) : '';
    $exposed = str_contains($api, 'supervisor_status');

    return [
        'status' => $exposed ? 'ok' : 'warn',
        'message' => $exposed
            ? 'Supervisor API disponibil (UI mission eliminat, backend păstrat).'
            : 'Backend supervisor există, API parțial.',
        'details' => ['api_exposed' => $exposed],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_bots_ui(string $root): array
{
    $ui = is_file($root . '/admin/Templates/admin/pages/ai-agent/ai-agent.php');
    $js = is_file($root . '/admin/public/assets/js/admin-ollama-control.js');
    $api = is_file($root . '/admin/public/api/ai_agent_endpoint.php');

    if ($ui && $js && $api) {
        return ['status' => 'ok', 'message' => 'Panou Control Ollama + API agenți active.', 'details' => []];
    }

    return ['status' => 'fail', 'message' => 'Lipsesc componente panou/API agenți.', 'details' => [
        'ui' => $ui, 'js' => $js, 'api' => $api,
    ]];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_module_tests(string $root, bool $live): array
{
    $modules = [];
    foreach (['erp', 'import', 'scraper', 'orchestr', 'python'] as $mod) {
        $modules[$mod] = ollama_health_module_status($mod, $root);
    }
    $green = 0;
    foreach ($modules as $m) {
        if (($m['status'] ?? '') === 'green') {
            $green++;
        }
    }

    if ($live && besoiu_ollama_enabled()) {
        $ping = ollama_health_test_chat(besoiu_ollama_base_url(), besoiu_ollama_model(), 'Spune: OK module.');
        if (empty($ping['ok'])) {
            return ['status' => 'fail', 'message' => 'Module: ' . $green . '/5 verzi, dar test live eșuat.', 'details' => ['modules' => $modules, 'live_error' => $ping['error'] ?? '']];
        }
    }

    $status = $green >= 4 ? 'ok' : ($green >= 2 ? 'warn' : 'fail');

    return [
        'status' => $status,
        'message' => sprintf('%d/5 module verzi (ERP, Import, Scraper, Orchestr, Python).', $green),
        'details' => ['modules' => $modules, 'green_count' => $green],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_shop_bridge(string $root, bool $live): array
{
    $bridgeFile = $root . '/app/Backend/src/Services/ShopChatOllamaAgentBridge.php';
    if (!is_file($bridgeFile)) {
        return ['status' => 'fail', 'message' => 'ShopChatOllamaAgentBridge lipsă.', 'details' => []];
    }

    require_once $bridgeFile;
    $enabled = \Besoiu\Services\ShopChatOllamaAgentBridge::isEnabled();
    $wired = ollama_health_class_has_consumer('ShopChatOllamaAgentBridge', $root);
    $widget = is_file($root . '/app/robot/chat_widget_api.php')
        && str_contains((string) file_get_contents($root . '/app/robot/chat_widget_api.php'), 'tryReply(');

    if (!$enabled) {
        return ['status' => 'warn', 'message' => 'Bridge dezactivat (SHOP_CHAT_OLLAMA_AGENTS=0).', 'details' => ['enabled' => false, 'wired' => $wired || $widget]];
    }

    if ($live && $wired) {
        ollama_opp_boot_admin($root);
        ollama_opp_pdo($root);
        try {
            if (class_exists(\Besoiu\Services\ShopChatOllamaAgentBridge::class)) {
                $bridge = new \Besoiu\Services\ShopChatOllamaAgentBridge($root . '/app');
                $reply = $bridge->tryReply('agent-produse', 'Spune doar: OK bridge.', [], '/', ['channel' => 'verify']);
                if (is_array($reply) && !empty($reply['ok'])) {
                    return ['status' => 'ok', 'message' => 'Bridge shop → agent răspunde live.', 'details' => ['provider' => $reply['provider'] ?? '']];
                }
            }
        } catch (Throwable $e) {
            return [
                'status' => 'warn',
                'message' => 'Bridge wired, live: ' . $e->getMessage(),
                'details' => ['orchestrator' => $wired, 'widget' => $widget],
            ];
        }
    }

    $status = ($wired || $widget) ? 'ok' : 'warn';

    return [
        'status' => $status,
        'message' => ($wired || $widget)
            ? 'Bridge activ în orchestrator + widget.'
            : 'Bridge există, wiring incomplet.',
        'details' => ['enabled' => true, 'orchestrator' => $wired, 'widget' => $widget],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_clients_unified(string $root): array
{
    $registry = ollama_health_integration_registry($root);
    $active = 0;
    foreach ($registry as $row) {
        if (($row['wiring'] ?? '') === 'active') {
            $active++;
        }
    }

    return [
        'status' => $active >= 8 ? 'ok' : 'warn',
        'message' => sprintf('%d/%d integrări active în registry (unificare parțială).', $active, count($registry)),
        'details' => ['active' => $active, 'total' => count($registry)],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_profiles(string $root): array
{
    $settings = $root . '/app/Import/MatchingPro/config/settings.json';
    $canonical = ollama_health_parse_env_file($root . '/app/Config/.env');
    $modelEnv = (string) ($canonical['OLLAMA_MODEL'] ?? '');
    $modelSettings = '';
    if (is_file($settings)) {
        $json = json_decode((string) file_get_contents($settings), true);
        if (is_array($json)) {
            $modelSettings = (string) ($json['ollama_model'] ?? '');
        }
    }
    $aligned = $modelSettings === '' || $modelEnv === '' || str_starts_with($modelSettings, explode(':', $modelEnv)[0]);

    return [
        'status' => $aligned ? 'ok' : 'warn',
        'message' => $aligned ? 'Model ERP aliniat cu Import settings.' : 'Profile diferite ERP vs Import.',
        'details' => ['erp_model' => $modelEnv, 'import_model' => $modelSettings],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_env_sync(string $root): array
{
    $diff = ollama_health_config_diff($root);
    $conflicts = count($diff['conflicts'] ?? []);

    return [
        'status' => $conflicts === 0 ? 'ok' : 'warn',
        'message' => $conflicts === 0
            ? 'Fără conflicte env Ollama între surse.'
            : $conflicts . ' conflicte env — verifică tab Config diff.',
        'details' => ['conflicts' => $conflicts],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_router_local(string $root): array
{
    $mode = strtolower(trim((string) (getenv('LLM_ROUTER_MODE') ?: $_ENV['LLM_ROUTER_MODE'] ?? 'ollama_first')));
    $localish = in_array($mode, ['local', 'ollama_only', 'ollama_first'], true);

    return [
        'status' => $localish ? 'ok' : 'warn',
        'message' => 'LLM_ROUTER_MODE=' . ($mode !== '' ? $mode : 'ollama_first') . ($localish ? ' (prioritizează local).' : ' (poate folosi cloud).'),
        'details' => ['router_mode' => $mode],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_python_bridge(string $root): array
{
    $py = $root . '/app/Import/modulOrchestr/python/orchestr_engine/nodes/llm.py';
    if (!is_file($py)) {
        return ['status' => 'warn', 'message' => 'llm.py lipsă — Python LangGraph parțial.', 'details' => []];
    }
    $content = (string) file_get_contents($py);
    $usesOllama = str_contains($content, 'ollama') || str_contains($content, 'OLLAMA');

    return [
        'status' => $usesOllama ? 'ok' : 'warn',
        'message' => $usesOllama ? 'Nod Python llm.py referă Ollama.' : 'llm.py există, verifică env partajat manual.',
        'details' => ['uses_ollama' => $usesOllama],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_catalog_rag(string $root): array
{
    $ragFile = $root . '/app/Backend/src/Services/CatalogRagService.php';
    if (!is_file($ragFile)) {
        return ['status' => 'fail', 'message' => 'CatalogRagService lipsă.', 'details' => []];
    }

    return [
        'status' => 'ok',
        'message' => 'RAG SQL disponibil (keywords + MySQL, fără embeddings vectoriale).',
        'details' => ['type' => 'sql_keywords'],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_fast_agents(string $root, bool $live): array
{
    if (!$live || !besoiu_ollama_enabled()) {
        return [
            'status' => 'warn',
            'message' => $live ? 'Ollama dezactivat — rulează cu live=1.' : 'Mod structură OK — apasă «Testează tot» pentru smoke live.',
            'details' => ['live_skipped' => !$live],
        ];
    }

    ollama_opp_boot_admin($root);
    $runner = new \Besoiu\Services\AiOllamaAgentRunnerService($root . '/app');
    $res = $runner->chat('agent-produse', 'Spune doar: OK smoke.', ['fast_test' => true, 'preset' => 'smoke_test']);
    if (!empty($res['ok'])) {
        return [
            'status' => 'ok',
            'message' => 'Smoke agent-produse OK (' . ($res['mode'] ?? 'ollama') . ').',
            'details' => ['provider' => $res['provider'] ?? '', 'mode' => $res['mode'] ?? ''],
        ];
    }

    return ['status' => 'fail', 'message' => (string) ($res['error'] ?? 'Smoke eșuat'), 'details' => $res];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_agent_imagini(string $root, bool $live): array
{
    $vision = besoiu_ollama_vision_model();
    $ping = ollama_health_ping_tags();
    $installed = ollama_health_model_installed($ping['models'], $vision);

    if (!$installed) {
        return ['status' => 'warn', 'message' => 'Model vision ' . $vision . ' neinstalat.', 'details' => []];
    }

    if ($live && besoiu_ollama_enabled()) {
        ollama_opp_boot_admin($root);
        ollama_opp_pdo($root);
        $runner = new \Besoiu\Services\AiOllamaAgentRunnerService($root . '/app');
        $noId = $runner->chat('agent-imagini', 'test', ['fast_test' => true]);
        $hasHint = str_contains(mb_strtolower((string) ($noId['content'] ?? $noId['error'] ?? ''), 'UTF-8'), 'randomn')
            || str_contains(mb_strtolower((string) ($noId['content'] ?? $noId['error'] ?? ''), 'UTF-8'), 'produs');

        return [
            'status' => !empty($noId['ok']) ? 'ok' : 'warn',
            'message' => !empty($noId['ok'])
                ? ($hasHint ? 'Agent Imagini răspunde + indică nevoia de ID produs.' : 'Agent Imagini răspunde (folosește randomn_id pentru audit).')
                : (string) ($noId['error'] ?? 'Test vision eșuat'),
            'details' => ['vision_model' => $vision],
        ];
    }

    return ['status' => 'ok', 'message' => 'Model vision instalat: ' . $vision, 'details' => []];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_agent_produse(string $root): array
{
    $ragFile = $root . '/app/Backend/src/Services/CatalogRagService.php';
    if (!is_file($ragFile)) {
        return ['status' => 'fail', 'message' => 'CatalogRagService lipsă.', 'details' => []];
    }

    ollama_opp_boot_admin($root);
    $pdo = ollama_opp_pdo($root);
    if ($pdo === null) {
        return ['status' => 'warn', 'message' => 'BD indisponibilă pentru test RAG (CLI fără .env DB).', 'details' => []];
    }

    try {
        $rag = new \Besoiu\Services\CatalogRagService($root . '/app');
        $search = $rag->searchByMessage('filtru ulei', 3, $pdo);

        return [
            'status' => 'ok',
            'message' => 'RAG produse funcțional (total=' . (int) ($search['total'] ?? 0) . ').',
            'details' => ['total_sample' => (int) ($search['total'] ?? 0)],
        ];
    } catch (Throwable $e) {
        return ['status' => 'warn', 'message' => 'RAG: ' . $e->getMessage(), 'details' => []];
    }
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_agents_scope(string $root): array
{
    $dir = $root . '/app/robot/data/ai_agents';
    $slugs = ['agent-imagini', 'agent-produse', 'agent-clienti', 'agent-statistici'];
    $found = 0;
    foreach ($slugs as $slug) {
        if (is_dir($dir . '/' . $slug)) {
            $found++;
        }
    }

    return [
        'status' => $found === 4 ? 'ok' : ($found >= 2 ? 'warn' : 'fail'),
        'message' => $found . '/4 agenți instalați în app/robot/data/ai_agents.',
        'details' => ['installed' => $found],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_ollama_daemon(string $root): array
{
    unset($root);
    if (!besoiu_ollama_enabled()) {
        return ['status' => 'warn', 'message' => 'OLLAMA_ENABLED=0 — daemon ignorat.', 'details' => []];
    }
    $ping = ollama_health_ping_tags();
    if (empty($ping['ok'])) {
        return ['status' => 'fail', 'message' => 'Ollama offline: ' . ($ping['error'] ?? ''), 'details' => $ping];
    }
    $text = besoiu_ollama_model();
    $vision = besoiu_ollama_vision_model();
    $textOk = ollama_health_model_installed($ping['models'], $text);
    $visionOk = ollama_health_model_installed($ping['models'], $vision);

    return [
        'status' => ($textOk && $visionOk) ? 'ok' : 'warn',
        'message' => sprintf(
            'Ollama activ %dms · text=%s · vision=%s',
            (int) ($ping['latency_ms'] ?? 0),
            $textOk ? 'OK' : 'LIPSĂ',
            $visionOk ? 'OK' : 'LIPSĂ'
        ),
        'details' => ['latency_ms' => $ping['latency_ms'], 'models' => count($ping['models'])],
    ];
}

/** @return array{status:string,message:string,details:array<string,mixed>} */
function ollama_opp_health_contract(string $root): array
{
    $logDir = ollama_error_log_dir($root);
    $writable = is_dir($logDir) && is_writable($logDir);
    $snapshot = ollama_health_snapshot($root);
    $hasIntegrations = count($snapshot['integrations'] ?? []) >= 8;

    if (!$writable) {
        return ['status' => 'fail', 'message' => 'Jurnal erori Ollama nu e inscriptibil.', 'details' => ['log_dir' => $logDir]];
    }

    return [
        'status' => $hasIntegrations ? 'ok' : 'warn',
        'message' => 'Health snapshot + JSONL erori + ' . count($snapshot['integrations'] ?? []) . ' integrări.',
        'details' => ['log_dir' => $logDir, 'integrations' => count($snapshot['integrations'] ?? [])],
    ];
}
