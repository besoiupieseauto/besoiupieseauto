<?php

declare(strict_types=1);

/**
 * Metro AI Orchestra — registry module, statistici, jurnal unificat.
 */

function metro_ai_orchestra_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/llm_router';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function metro_ai_orchestra_stats_path(): string
{
    return metro_ai_orchestra_storage_dir() . '/orchestra_stats.json';
}

/** @return list<array<string, mixed>> */
function metro_ai_orchestra_module_registry(): array
{
    require_once __DIR__ . '/ollama_llm.php';

    $modules = [
        ['id' => 'chat_widget', 'label' => 'Chat widget site', 'zone' => 'comunicare', 'entry' => 'ChatLlmService', 'task' => 'chat_widget'],
        ['id' => 'chat_decode', 'label' => 'Decoder mesaje chat', 'zone' => 'comunicare', 'entry' => 'ShopChatMessageDecoderService', 'task' => 'chat_decode'],
        ['id' => 'chat_whatsapp', 'label' => 'Robot WhatsApp', 'zone' => 'comunicare', 'entry' => 'robot/process.php', 'task' => 'chat_widget_whatsapp'],
        ['id' => 'section_assistant', 'label' => 'Asistent secțiune admin', 'zone' => 'comunicare', 'entry' => 'SectionAssistantService', 'task' => 'section_assistant'],
        ['id' => 'section_assistant_chat', 'label' => 'Conversație asistent', 'zone' => 'comunicare', 'entry' => 'SectionAssistantConversationService', 'task' => 'section_assistant_chat'],
        ['id' => 'image_audit', 'label' => 'Audit imagini produse', 'zone' => 'imagini', 'entry' => 'ProductImageAuditService', 'task' => 'image_audit'],
        ['id' => 'agent_imagini', 'label' => 'Agent Imagini (Ollama vision)', 'zone' => 'imagini', 'entry' => 'AiAgentRegistryService', 'task' => 'agent_imagini'],
        ['id' => 'agent_produse', 'label' => 'Agent Produse (Ollama)', 'zone' => 'catalog', 'entry' => 'AiAgentRegistryService', 'task' => 'agent_produse'],
        ['id' => 'agent_clienti', 'label' => 'Agent Clienți (Ollama)', 'zone' => 'clienti', 'entry' => 'AiAgentRegistryService', 'task' => 'agent_clienti'],
        ['id' => 'agent_statistici', 'label' => 'Agent Statistici (Ollama)', 'zone' => 'sistem', 'entry' => 'AiAgentRegistryService', 'task' => 'agent_statistici'],
        ['id' => 'import_image_gate', 'label' => 'Poartă AI imagini import', 'zone' => 'import', 'entry' => 'PipelineImageAiGate', 'task' => 'import_image_gate'],
        ['id' => 'import_pipeline', 'label' => 'Pipeline import etape', 'zone' => 'import', 'entry' => 'ImportPipelineStageTest', 'task' => 'import_pipeline'],
        ['id' => 'import_quality_match', 'label' => 'Agent Quality Import (match date)', 'zone' => 'import', 'entry' => 'ImportQualityAgentService', 'task' => 'import_quality_match'],
        ['id' => 'scraper_analyze', 'label' => 'Scraper AI agent', 'zone' => 'import', 'entry' => 'ScraperAiAgent', 'task' => 'scraper_analyze'],
        ['id' => 'import_supervisor', 'label' => 'Supervizor Import (pauză + reparare)', 'zone' => 'import', 'entry' => 'ImportSupervisorAgentService', 'task' => 'import_supervisor_repair'],
    ];

    foreach ($modules as &$row) {
        $row['profile'] = besoiu_llm_task_profile((string) $row['task']);
    }
    unset($row);

    return $modules;
}

/** @param array<string, mixed> $entry */
function metro_ai_orchestra_log_module(string $moduleId, string $action, array $entry): void
{
    require_once __DIR__ . '/metro_llm_hub.php';
    metro_llm_route_log_append(array_merge([
        'task' => $moduleId,
        'action' => $action,
        'source' => 'orchestra',
        'module' => $moduleId,
    ], $entry));
}

/** @return array<string, mixed> */
function metro_ai_orchestra_load_stats(): array
{
    $path = metro_ai_orchestra_stats_path();
    if (!is_file($path)) {
        return ['runs' => [], 'last_full_test' => null, 'modules' => []];
    }
    $json = json_decode((string) file_get_contents($path), true);

    return is_array($json) ? $json : ['runs' => [], 'last_full_test' => null, 'modules' => []];
}

/** @param array<string, mixed> $stats */
function metro_ai_orchestra_save_stats(array $stats): void
{
    $stats['updated_at'] = date('c');
    $line = json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($line !== false) {
        @file_put_contents(metro_ai_orchestra_stats_path(), $line, LOCK_EX);
    }
}

/** @param array<string, mixed> $testResult */
function metro_ai_orchestra_record_test_run(array $testResult): void
{
    $stats = metro_ai_orchestra_load_stats();
    $stats['last_full_test'] = [
        'ts' => $testResult['generated_at'] ?? date('c'),
        'summary' => $testResult['summary'] ?? [],
    ];
    $stats['modules'] = $testResult['modules'] ?? [];
    $runs = is_array($stats['runs'] ?? null) ? $stats['runs'] : [];
    array_unshift($runs, [
        'ts' => $testResult['generated_at'] ?? date('c'),
        'ok' => (int) ($testResult['summary']['ok'] ?? 0),
        'fail' => (int) ($testResult['summary']['fail'] ?? 0),
        'skip' => (int) ($testResult['summary']['skip'] ?? 0),
    ]);
    $stats['runs'] = array_slice($runs, 0, 30);
    metro_ai_orchestra_save_stats($stats);
}

/** @return array<string, mixed> */
function metro_ai_orchestra_aggregate(?string $projectRoot = null): array
{
    require_once __DIR__ . '/metro_llm_hub.php';

    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    $stats = metro_ai_orchestra_load_stats();
    $routes = metro_llm_route_log_recent(100);
    $byTask = [];
    $byProvider = [];
    $okCount = 0;
    $failCount = 0;

    foreach ($routes as $row) {
        if (!is_array($row)) {
            continue;
        }
        $task = (string) ($row['task'] ?? 'unknown');
        $via = (string) ($row['routed_via'] ?? 'unknown');
        $byTask[$task] = ($byTask[$task] ?? 0) + 1;
        $byProvider[$via] = ($byProvider[$via] ?? 0) + 1;
        if (!empty($row['ok'])) {
            $okCount++;
        } else {
            $failCount++;
        }
    }

    $routerReady = false;
    $routerFile = dirname(__DIR__) . '/src/Services/LlmRouterService.php';
    if (is_file($routerFile)) {
        require_once $routerFile;
        $routerReady = (new \Besoiu\Services\LlmRouterService($projectRoot))->isConfigured();
    }

    return [
        'registry_count' => count(metro_ai_orchestra_module_registry()),
        'router_ready' => $routerReady,
        'route_log' => [
            'total_recent' => count($routes),
            'ok' => $okCount,
            'fail' => $failCount,
            'by_task' => $byTask,
            'by_provider' => $byProvider,
        ],
        'last_test' => $stats['last_full_test'] ?? null,
        'module_results' => $stats['modules'] ?? [],
        'test_history' => array_slice(is_array($stats['runs'] ?? null) ? $stats['runs'] : [], 0, 10),
    ];
}
