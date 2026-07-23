<?php

declare(strict_types=1);

/**
 * Hub Metro LLM — monitorizare Ollama + Cursor, jurnal rutare, ecosistem.
 */

function metro_llm_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/llm_router';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function metro_llm_route_log_path(): string
{
    return metro_llm_storage_dir() . '/routes.jsonl';
}

/** @param array<string, mixed> $entry */
function metro_llm_route_log_append(array $entry): void
{
    $entry['ts'] = $entry['ts'] ?? date('c');
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }
    @file_put_contents(metro_llm_route_log_path(), $line . "\n", FILE_APPEND | LOCK_EX);

    $path = metro_llm_route_log_path();
    if (!is_file($path) || filesize($path) < 512000) {
        return;
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || count($lines) <= 400) {
        return;
    }
    $tail = array_slice($lines, -300);
    @file_put_contents($path, implode("\n", $tail) . "\n", LOCK_EX);
}

/** @return list<array<string, mixed>> */
function metro_llm_route_log_recent(int $limit = 20): array
{
    $path = metro_llm_route_log_path();
    if (!is_file($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }
    $out = [];
    foreach (array_slice(array_reverse($lines), 0, max(1, min(100, $limit))) as $line) {
        $row = json_decode((string) $line, true);
        if (is_array($row)) {
            $out[] = $row;
        }
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function metro_llm_task_matrix(): array
{
    require_once __DIR__ . '/ollama_llm.php';

    $rows = [
        ['id' => 'supervisor_diagnostics', 'label' => 'Diagnostic supervizor', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'Ciclu / manual'],
        ['id' => 'supervisor_conversations', 'label' => 'Analiză conversații', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'Ciclu'],
        ['id' => 'section_assistant_intent', 'label' => 'Intent asistent secțiune', 'profile' => 'local', 'provider' => 'Ollama', 'trigger' => 'Widget Composer'],
        ['id' => 'section_assistant', 'label' => 'Asistent secțiune admin', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'La cerere'],
        ['id' => 'section_assistant_chat', 'label' => 'Conversație asistent', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'La cerere'],
        ['id' => 'context_brief', 'label' => 'Briefing context agenți', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'Cron agent'],
        ['id' => 'composer_repair', 'label' => 'Composer Repair', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'Manual supervizor'],
        ['id' => 'chat_widget', 'label' => 'Chat widget site', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'La cerere'],
        ['id' => 'chat_decode', 'label' => 'Decoder mesaje chat', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'Widget chat'],
        ['id' => 'chat_widget_whatsapp', 'label' => 'Robot WhatsApp', 'profile' => 'hybrid', 'provider' => 'Ollama → Groq → Gemini → OpenRouter → Cursor', 'trigger' => 'Webhook'],
        ['id' => 'image_audit', 'label' => 'Audit imagini produse', 'profile' => 'cloud', 'provider' => 'Cursor batch / OpenAI Vision', 'trigger' => 'Manual admin'],
        ['id' => 'import_image_gate', 'label' => 'Poartă AI imagini import', 'profile' => 'cloud', 'provider' => 'Heuristic + Vision', 'trigger' => 'Pipeline import'],
        ['id' => 'import_pipeline', 'label' => 'Pipeline import etape', 'profile' => 'hybrid', 'provider' => 'Orchestrator', 'trigger' => 'Test / cron'],
        ['id' => 'scraper_analyze', 'label' => 'Scraper AI agent', 'profile' => 'hybrid', 'provider' => 'Metro Orchestra → Cursor', 'trigger' => 'Scraper admin'],
    ];
    foreach ($rows as &$row) {
        $row['profile_key'] = besoiu_llm_task_profile((string) $row['id']);
    }
    unset($row);

    return $rows;
}

/** @return array{today: array<string, int>, month: array<string, int>} */
function metro_llm_usage_split(?PDO $pdo = null): array
{
    require_once __DIR__ . '/api_token_budget.php';
    $pdo = $pdo ?? api_token_budget_pdo();
    $stats = api_token_budget_stats($pdo);
    $by = is_array($stats['by_provider'] ?? null) ? $stats['by_provider'] : [];

    $today = ['ollama' => 0, 'cursor' => 0, 'cloud_other' => 0];
    $month = ['ollama' => 0, 'cursor' => 0, 'cloud_other' => 0];

    foreach ($by as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $tu = (int) ($row['today_units'] ?? 0);
        $mu = (int) ($row['month_units'] ?? 0);
        if ($key === 'ollama') {
            $today['ollama'] += $tu;
            $month['ollama'] += $mu;
        } elseif ($key === 'cursor') {
            $today['cursor'] += $tu;
            $month['cursor'] += $mu;
        } elseif (in_array($key, ['openai', 'groq', 'gemini', 'grok'], true)) {
            $today['cloud_other'] += $tu;
            $month['cloud_other'] += $mu;
        }
    }

    $log = metro_llm_route_log_recent(200);
    if ($today['ollama'] === 0 && $today['cursor'] === 0) {
        foreach ($log as $e) {
            if ((string) ($e['ts'] ?? '') === '' || substr((string) $e['ts'], 0, 10) !== date('Y-m-d')) {
                continue;
            }
            $via = (string) ($e['routed_via'] ?? '');
            if ($via === 'ollama') {
                ++$today['ollama'];
            } elseif ($via === 'cursor') {
                ++$today['cursor'];
            }
        }
    }

    return ['today' => $today, 'month' => $month];
}

/** @return array<string, mixed> */
function metro_llm_cron_status(?string $projectRoot = null): array
{
    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    require_once dirname(__DIR__, 2) . '/system/api_automation_guard.php';
    require_once __DIR__ . '/ollama_llm.php';

    $paused = besoiu_api_automation_paused();
    $localAllowed = $paused && besoiu_ollama_local_cycle_enabled();
    $cycleFile = $projectRoot . '/robot/data/ai_supervisor/last_cycle.json';
    $last = null;
    $ageMin = null;
    if (is_file($cycleFile)) {
        $raw = @file_get_contents($cycleFile);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $last = $decoded;
            $at = (string) ($decoded['started_at'] ?? '');
            if ($at !== '') {
                $ts = strtotime($at);
                if ($ts !== false) {
                    $ageMin = (int) max(0, floor((time() - $ts) / 60));
                }
            }
        }
    }

    return [
        'api_on_demand' => $paused,
        'local_cycle_allowed' => $localAllowed,
        'cron_blocked' => $paused && !$localAllowed,
        'last_cycle' => $last,
        'last_cycle_age_minutes' => $ageMin,
        'jobs_last' => is_array($last['jobs'] ?? null) ? count($last['jobs']) : 0,
        'duration_ms' => (int) ($last['duration_ms'] ?? 0),
    ];
}

/** @param array<string, mixed> $ollama @param array<string, mixed> $cron @param array<string, mixed> $usage */
function metro_llm_ecosystem_status(array $ollama, array $cron, array $usage, int $opsCritical = 0, int $opsWarning = 0): array
{
    $level = 'ok';
    $label = 'Ecosistem viu';
    $reasons = [];

    if ($opsCritical > 0) {
        $level = 'critical';
        $label = 'Alerte critice';
        $reasons[] = $opsCritical . ' alerte critice';
    } elseif (!$ollama['ready'] && empty($cron['api_on_demand'])) {
        $level = 'warning';
        $label = 'Ollama indisponibil';
        $reasons[] = 'Pornește Ollama (ollama serve)';
    } elseif ($cron['cron_blocked'] ?? false) {
        $level = 'warning';
        $label = 'Ciclu blocat';
        $reasons[] = 'API oprit fără ciclu local';
    } elseif (($cron['last_cycle_age_minutes'] ?? 9999) > 60) {
        $level = 'warning';
        $label = 'Date vechi';
        $reasons[] = 'Ultimul ciclu acum ' . ($cron['last_cycle_age_minutes'] ?? '?') . ' min';
    } elseif (!$ollama['ready'] && ($cron['api_on_demand'] ?? false)) {
        $level = 'warning';
        $label = 'Ollama oprit';
        $reasons[] = 'Ciclu local necesită Ollama';
    } elseif ($opsWarning > 0) {
        $level = 'warning';
        $label = 'Atenție';
        $reasons[] = $opsWarning . ' avertismente';
    }

    if ($level === 'ok' && $ollama['ready']) {
        $reasons[] = 'Ollama ' . ($ollama['model'] ?? '');
    }
    if ($level === 'ok' && ($usage['today']['ollama'] ?? 0) > 0) {
        $reasons[] = $usage['today']['ollama'] . ' apeluri locale azi';
    }

    return [
        'level' => $level,
        'label' => $label,
        'reasons' => $reasons,
    ];
}

/** @return array<string, mixed> */
function metro_llm_hub_snapshot(?string $projectRoot = null, ?PDO $pdo = null): array
{
    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    require_once __DIR__ . '/ollama_llm.php';
    require_once __DIR__ . '/api_automation_catalog.php';

    $ollamaClient = dirname(__DIR__) . '/src/Services/OllamaLlmClient.php';
    $routerClient = dirname(__DIR__) . '/src/Services/LlmRouterService.php';
    $ollamaReady = ['enabled' => false, 'ready' => false];
    $routerReady = ['router_mode' => besoiu_llm_router_mode(), 'ready' => false];
    if (is_file($ollamaClient)) {
        require_once $ollamaClient;
        $ollamaReady = (new \Besoiu\Services\OllamaLlmClient($projectRoot))->readiness();
    }
    if (is_file($routerClient)) {
        require_once $routerClient;
        $routerReady = (new \Besoiu\Services\LlmRouterService($projectRoot))->readiness();
    }

    $pulse = function_exists('api_supervisor_live_pulse')
        ? api_supervisor_live_pulse($projectRoot)
        : [];

    require_once __DIR__ . '/api_token_budget.php';
    $usage = ['today' => ['ollama' => 0, 'cursor' => 0, 'cloud_other' => 0], 'month' => ['ollama' => 0, 'cursor' => 0, 'cloud_other' => 0]];
    try {
        $pdo = $pdo ?? api_token_budget_pdo();
        $usage = metro_llm_usage_split($pdo);
    } catch (Throwable) {
        // BD opțională la snapshot
    }
    $cron = metro_llm_cron_status($projectRoot);
    $ops = is_array($pulse['ops_alerts'] ?? null) ? $pulse['ops_alerts'] : ['critical' => 0, 'warning' => 0];
    $ecosystem = metro_llm_ecosystem_status(
        $ollamaReady,
        $cron,
        $usage,
        (int) ($ops['critical'] ?? 0),
        (int) ($ops['warning'] ?? 0)
    );

    $orchestra = [];
    $orchestraFile = __DIR__ . '/metro_ai_orchestra.php';
    if (is_file($orchestraFile)) {
        require_once $orchestraFile;
        if (function_exists('metro_ai_orchestra_aggregate')) {
            $orchestra = metro_ai_orchestra_aggregate($projectRoot);
        }
    }

    return [
        'generated_at' => date('c'),
        'config' => [
            'ollama_enabled' => besoiu_ollama_enabled(),
            'ollama_model' => besoiu_ollama_model(),
            'ollama_base_url' => besoiu_ollama_base_url(),
            'ollama_local_cycle' => besoiu_ollama_local_cycle_enabled(),
            'llm_router_mode' => besoiu_llm_router_mode(),
        ],
        'ollama' => $ollamaReady,
        'router' => $routerReady,
        'usage' => $usage,
        'cron' => $cron,
        'ecosystem' => $ecosystem,
        'orchestra' => $orchestra,
        'task_matrix' => metro_llm_task_matrix(),
        'route_log' => metro_llm_route_log_recent(20),
        'live_pulse' => $pulse,
    ];
}

/** @param array<string, mixed> $input @return array{ok:bool,message:string,updated:list<string>} */
function metro_llm_save_config(array $input): array
{
    require_once __DIR__ . '/env_settings.php';

    $map = [
        'ollama_enabled' => 'OLLAMA_ENABLED',
        'ollama_local_cycle' => 'OLLAMA_LOCAL_CYCLE',
        'ollama_model' => 'OLLAMA_MODEL',
        'ollama_base_url' => 'OLLAMA_BASE_URL',
        'llm_router_mode' => 'LLM_ROUTER_MODE',
    ];

    $changes = [];
    foreach ($map as $inKey => $envKey) {
        if (!array_key_exists($inKey, $input)) {
            continue;
        }
        $val = $input[$inKey];
        if (in_array($inKey, ['ollama_enabled', 'ollama_local_cycle'], true)) {
            $on = $val === true || $val === 1 || $val === '1' || $val === 'true' || $val === 'on';
            $changes[$envKey] = $on ? '1' : '0';
        } else {
            $changes[$envKey] = trim((string) $val);
        }
    }

    if ($changes === []) {
        return ['ok' => false, 'message' => 'Nicio setare Metro LLM de salvat.', 'updated' => []];
    }

    return besoiu_env_write_values($changes);
}

/** @return array<string, mixed> */
function metro_llm_test_ollama(?string $projectRoot = null): array
{
    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    require_once dirname(__DIR__) . '/src/Services/LlmRouterService.php';
    $router = new \Besoiu\Services\LlmRouterService($projectRoot);
    $res = $router->complete(
        'Răspunde foarte scurt în română.',
        'Spune exact: OK Metro Ollama.',
        0.1,
        45,
        'supervisor_diagnostics'
    );
    metro_llm_route_log_append([
        'task' => 'test_ollama',
        'routed_via' => (string) ($res['routed_via'] ?? 'unknown'),
        'ok' => !empty($res['ok']),
        'model' => (string) ($res['model'] ?? ''),
        'source' => 'settings_test',
    ]);

    return $res;
}

/** @return array<string, mixed> */
function metro_llm_test_cloud(?string $projectRoot = null): array
{
    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    require_once dirname(__DIR__) . '/src/Services/LlmRouterService.php';
    $router = new \Besoiu\Services\LlmRouterService($projectRoot);
    if (!$router->isConfigured()) {
        return [
            'ok' => false,
            'error' => 'Niciun provider cloud/local configurat (Ollama, Groq, Gemini, OpenRouter).',
            'readiness' => $router->readiness(),
        ];
    }

    $res = $router->complete('Răspunde scurt.', 'Spune: OK Metro LLM.', 0.1, 90, 'supervisor_diagnostics');
    metro_llm_route_log_append([
        'task' => 'test_cloud',
        'routed_via' => (string) ($res['routed_via'] ?? 'unknown'),
        'ok' => !empty($res['ok']),
        'model' => (string) ($res['model'] ?? ''),
        'source' => 'settings_test',
    ]);

    return array_merge($res, ['readiness' => $router->readiness()]);
}

/** @return array<string, mixed> */
function metro_llm_test_chat(?string $projectRoot = null): array
{
    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    require_once dirname(__DIR__) . '/src/Services/ChatLlmService.php';
    $chat = new \Besoiu\Services\ChatLlmService($projectRoot);
    $res = $chat->chat(
        [['role' => 'user', 'content' => 'Spune exact: OK Metro Chat.']],
        'Răspunde foarte scurt în română.',
        0.2,
        45,
        'chat-widget-test'
    );
    metro_llm_route_log_append([
        'task' => 'chat_widget',
        'routed_via' => (string) ($res['routed_via'] ?? 'unknown'),
        'ok' => !empty($res['ok']),
        'model' => (string) ($res['model'] ?? ''),
        'source' => 'settings_test',
    ]);

    return array_merge($res, ['readiness' => $chat->readiness()]);
}

/** @return array<string, mixed> */
function metro_llm_test_local_cycle(?string $projectRoot = null): array
{
    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    $cron = metro_llm_cron_status($projectRoot);
    if (!empty($cron['cron_blocked'])) {
        return [
            'ok' => false,
            'error' => 'Ciclu local blocat — activează OLLAMA_LOCAL_CYCLE=1 sau dezactivează API_AUTOMATION_DISABLED.',
            'cron' => $cron,
        ];
    }

    require_once dirname(__DIR__) . '/src/Services/AiSupervisor/AiSupervisorOrchestrator.php';
    $orch = new \Besoiu\Services\AiSupervisor\AiSupervisorOrchestrator();
    $result = $orch->runCycle(['force_all' => true]);

    return [
        'ok' => !empty($result['ok']),
        'message' => 'Ciclu local finalizat.',
        'data' => $result,
        'cron' => metro_llm_cron_status($projectRoot),
    ];
}

/** @return list<array<string, mixed>> */
function metro_llm_collect_ops_alerts(?string $projectRoot = null): array
{
    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    require_once __DIR__ . '/ollama_llm.php';

    $items = [];
    $ollama = ['enabled' => false, 'reachable' => false, 'ready' => false, 'model' => ''];
    $ollamaClient = dirname(__DIR__) . '/src/Services/OllamaLlmClient.php';
    if (is_file($ollamaClient)) {
        require_once $ollamaClient;
        $ollama = (new \Besoiu\Services\OllamaLlmClient($projectRoot))->readiness();
    }
    $cron = metro_llm_cron_status($projectRoot);

    if (besoiu_ollama_enabled() && empty($ollama['reachable'])) {
        $items[] = [
            'code' => 'ollama_unreachable',
            'level' => 'warning',
            'title' => 'Ollama nu răspunde',
            'problem' => 'Serviciul local de la ' . besoiu_ollama_base_url() . ' este oprit.',
            'detail' => 'Rulează: ollama serve sau repornește aplicația Ollama.',
            'url' => '/admin/settings?tab=tokens',
        ];
    } elseif (besoiu_ollama_enabled() && empty($ollama['model_installed'])) {
        $items[] = [
            'code' => 'ollama_model_missing',
            'level' => 'warning',
            'title' => 'Model Ollama lipsă',
            'problem' => 'Modelul ' . besoiu_ollama_model() . ' nu e instalat.',
            'detail' => 'Rulează: ollama pull ' . besoiu_ollama_model(),
            'url' => '/admin/settings?tab=tokens',
        ];
    }

    if (!empty($cron['cron_blocked'])) {
        $items[] = [
            'code' => 'metro_cycle_blocked',
            'level' => 'warning',
            'title' => 'Ciclu AI blocat',
            'problem' => 'API_AUTOMATION_DISABLED=1 fără ciclu local Ollama.',
            'detail' => 'Activează OLLAMA_LOCAL_CYCLE sau rulează ciclu manual din Supervizor.',
            'url' => '/admin/ai-agent#supervizor',
        ];
    } elseif (($cron['last_cycle_age_minutes'] ?? 0) > 90 && besoiu_ollama_local_cycle_enabled()) {
        $items[] = [
            'code' => 'metro_cycle_stale',
            'level' => 'warning',
            'title' => 'Supervizor întârziat',
            'problem' => 'Ultimul ciclu acum ' . (int) $cron['last_cycle_age_minutes'] . ' minute.',
            'detail' => 'Verifică Task Scheduler ai_agent_cycle sau apasă Rulează ciclu.',
            'url' => '/admin/ai-agent#supervizor',
        ];
    }

    return $items;
}
