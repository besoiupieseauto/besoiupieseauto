<?php

declare(strict_types=1);

require_once __DIR__ . '/ollama_llm.php';
require_once __DIR__ . '/ollama_error_log.php';

/** @return list<string> */
function ollama_health_env_keys(): array
{
    return [
        'OLLAMA_ENABLED',
        'OLLAMA_BASE_URL',
        'OLLAMA_MODEL',
        'OLLAMA_VISION_MODEL',
        'OLLAMA_TIMEOUT_SEC',
        'OLLAMA_MODEL_PRODUSE',
        'OLLAMA_MODEL_CLIENTI',
        'OLLAMA_MODEL_STATS',
        'LLM_ROUTER_MODE',
        'OLLAMA_LOCAL_CYCLE',
        'SHOP_CHAT_OLLAMA_AGENTS',
    ];
}

/** @return list<array{path:string,label:string,kind:string}> */
function ollama_health_env_sources(?string $projectRoot = null): array
{
    $root = ollama_site_root($projectRoot);

    return [
        ['path' => $root . '/app/Config/.env', 'label' => 'app/Config/.env', 'kind' => 'canonical'],
        ['path' => $root . '/admin/.env', 'label' => 'admin/.env', 'kind' => 'admin'],
        ['path' => $root . '/app/Import/Scraper/.env', 'label' => 'Import/Scraper/.env', 'kind' => 'module'],
        ['path' => $root . '/app/Import/modulOrchestr/.env', 'label' => 'Import/modulOrchestr/.env', 'kind' => 'module'],
    ];
}

/** @return array<string, string> */
function ollama_health_parse_env_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $out = [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!in_array($key, ollama_health_env_keys(), true)) {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $out[$key] = trim($value);
    }

    return $out;
}

/** @return array{rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} */
function ollama_health_config_diff(?string $projectRoot = null): array
{
    $sources = ollama_health_env_sources($projectRoot);
    $keys = ollama_health_env_keys();
    $byKey = [];
    foreach ($sources as $src) {
        $parsed = ollama_health_parse_env_file($src['path']);
        foreach ($keys as $key) {
            $byKey[$key][$src['label']] = $parsed[$key] ?? null;
        }
    }

    $settingsPath = ollama_site_root($projectRoot) . '/app/Import/MatchingPro/config/settings.json';
    $settings = is_file($settingsPath) ? json_decode((string) file_get_contents($settingsPath), true) : [];
    if (is_array($settings)) {
        if (isset($settings['ollama_model'])) {
            $byKey['OLLAMA_MODEL']['MatchingPro/settings.json'] = (string) $settings['ollama_model'];
        }
        if (isset($settings['ollama_timeout_sec'])) {
            $byKey['OLLAMA_TIMEOUT_SEC']['MatchingPro/settings.json'] = (string) $settings['ollama_timeout_sec'];
        }
    }

    $rows = [];
    $conflicts = [];
    $canonical = ollama_health_parse_env_file(ollama_site_root($projectRoot) . '/app/Config/.env');

    foreach ($keys as $key) {
        $values = $byKey[$key] ?? [];
        $nonEmpty = array_values(array_filter($values, static fn ($v): bool => $v !== null && $v !== ''));
        $unique = array_unique($nonEmpty);
        $row = [
            'key' => $key,
            'values' => $values,
            'canonical' => $canonical[$key] ?? (function_exists('getenv') ? (getenv($key) ?: null) : null),
            'has_conflict' => count($unique) > 1,
        ];
        $rows[] = $row;
        if ($row['has_conflict']) {
            $conflicts[] = $row;
        }
    }

    return ['rows' => $rows, 'conflicts' => $conflicts];
}

/** @return array{ok:bool,reachable:bool,latency_ms:int,models:list<string>,error:string,base_url:string} */
function ollama_health_ping_tags(?string $baseUrl = null, int $timeoutSec = 4): array
{
    $base = rtrim($baseUrl ?? besoiu_ollama_base_url(), '/');
    $started = hrtime(true);
    $url = $base . '/api/tags';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'reachable' => false, 'latency_ms' => 0, 'models' => [], 'error' => 'curl_init eșuat', 'base_url' => $base];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSec),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        if ($body === false || $code < 200 || $code >= 300) {
            return ['ok' => false, 'reachable' => false, 'latency_ms' => $latency, 'models' => [], 'error' => $err !== '' ? $err : ('HTTP ' . $code), 'base_url' => $base];
        }
        $json = json_decode((string) $body, true);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => $timeoutSec, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        if (!is_string($body) || $body === '') {
            return ['ok' => false, 'reachable' => false, 'latency_ms' => $latency, 'models' => [], 'error' => 'Fără răspuns', 'base_url' => $base];
        }
        $json = json_decode($body, true);
    }

    $models = [];
    foreach ((array) ($json['models'] ?? []) as $row) {
        if (is_array($row) && !empty($row['name'])) {
            $models[] = (string) $row['name'];
        }
    }

    return ['ok' => true, 'reachable' => true, 'latency_ms' => $latency, 'models' => $models, 'error' => '', 'base_url' => $base];
}

function ollama_health_model_installed(array $models, string $needle): bool
{
    $needle = strtolower(trim($needle));
    if ($needle === '') {
        return false;
    }
    foreach ($models as $name) {
        $name = strtolower((string) $name);
        if ($name === $needle || str_starts_with($name, $needle . ':')) {
            return true;
        }
    }

    return false;
}

/** @return array<string, mixed> */
function ollama_health_module_status(string $moduleId, ?string $projectRoot = null): array
{
    $projectRoot = ollama_site_root($projectRoot);
    $baseUrl = besoiu_ollama_base_url();
    $textModel = besoiu_ollama_model();
    $visionModel = besoiu_ollama_vision_model();

    if ($moduleId === 'import') {
        $settingsPath = $projectRoot . '/app/Import/MatchingPro/config/settings.json';
        $settings = is_file($settingsPath) ? json_decode((string) file_get_contents($settingsPath), true) : [];
        if (is_array($settings) && trim((string) ($settings['ollama_model'] ?? '')) !== '') {
            $textModel = trim((string) $settings['ollama_model']);
        }
    }

    $ping = ollama_health_ping_tags($baseUrl);
    $telemetry = ollama_telemetry_all($projectRoot)[$moduleId] ?? [];

    $ready = $ping['ok']
        && ollama_health_model_installed($ping['models'], $textModel)
        && ($moduleId === 'python' || ollama_health_model_installed($ping['models'], $visionModel));

    $status = 'red';
    if ($ping['ok'] && $ready) {
        $status = 'green';
    } elseif ($ping['ok']) {
        $status = 'yellow';
    }

    return [
        'module' => $moduleId,
        'label' => match ($moduleId) {
            'erp' => 'ERP / Backend',
            'import' => 'Import / MatchingPro',
            'scraper' => 'Scraper',
            'orchestr' => 'modulOrchestr (PHP)',
            'python' => 'modulOrchestr (Python)',
            default => $moduleId,
        },
        'base_url' => $baseUrl,
        'text_model' => $textModel,
        'vision_model' => $visionModel,
        'reachable' => $ping['ok'],
        'ready' => $ready,
        'status' => $status,
        'latency_ms' => $ping['latency_ms'],
        'last_at' => (string) ($telemetry['last_at'] ?? ''),
        'last_ok' => (bool) ($telemetry['last_ok'] ?? false),
        'last_error' => (string) ($telemetry['last_error'] ?? ''),
        'avg_latency_ms' => (int) ($telemetry['avg_latency_ms'] ?? 0),
        'error' => (string) ($ping['error'] ?? ''),
    ];
}

/** @return list<array<string, mixed>> */
function ollama_health_integration_registry(?string $projectRoot = null): array
{
    $site = ollama_site_root($projectRoot);
    $bridgeUsed = ollama_health_class_has_consumer('Besoiu\\Services\\ShopChatOllamaAgentBridge', $site);
    $agentEndpoint = is_file($site . '/admin/public/api/ai_agent_endpoint.php')
        && ollama_health_file_contains($site . '/admin/public/api', 'agent_ollama_status');
    $uiJs = is_file($site . '/admin/public/assets/js/admin-ollama-control.js');

    return [
        ['id' => 'erp.client', 'module' => 'erp', 'file' => 'app/Backend/src/Services/OllamaLlmClient.php', 'fn' => 'chat()/visionChat()', 'purpose' => 'Client central ERP', 'wiring' => 'active'],
        ['id' => 'erp.router', 'module' => 'erp', 'file' => 'app/Backend/src/Services/LlmRouterService.php', 'fn' => 'routeChain()', 'purpose' => 'Router Metro LLM', 'wiring' => 'active'],
        ['id' => 'erp.agents', 'module' => 'erp', 'file' => 'app/Backend/src/Services/AiOllamaAgentRunnerService.php', 'fn' => 'chat()', 'purpose' => 'Agenți specializați', 'wiring' => $agentEndpoint ? 'active' : 'dead'],
        ['id' => 'erp.shop_bridge', 'module' => 'erp', 'file' => 'app/Backend/src/Services/ShopChatOllamaAgentBridge.php', 'fn' => 'tryReply()', 'purpose' => 'Chat public → agenți', 'wiring' => $bridgeUsed ? 'active' : 'dead'],
        ['id' => 'erp.image_audit', 'module' => 'erp', 'file' => 'app/Backend/src/Services/ProductImageAuditService.php', 'fn' => 'analyzeProductWithOllamaVision()', 'purpose' => 'Audit imagini', 'wiring' => 'active'],
        ['id' => 'import.mapping', 'module' => 'import', 'file' => 'app/Import/MatchingPro/api/mapping-ollama.php', 'fn' => 'import_ollama_chat_json()', 'purpose' => 'Mapping CSV OEM', 'wiring' => 'active'],
        ['id' => 'scraper.relevance', 'module' => 'scraper', 'file' => 'app/Import/Scraper/lib/ScraperOllamaClient.php', 'fn' => 'analyzeProductRelevance()', 'purpose' => 'Relevanță produs', 'wiring' => 'active'],
        ['id' => 'orchestr.router', 'module' => 'orchestr', 'file' => 'app/Import/modulOrchestr/lib/OrchestrLocalRouter.php', 'fn' => 'ollamaComplete()', 'purpose' => 'Router local Import', 'wiring' => 'active'],
        ['id' => 'orchestr.vision', 'module' => 'orchestr', 'file' => 'app/Import/modulOrchestr/lib/OrchestrOllamaVisionClient.php', 'fn' => 'analyzeProductRelevance()', 'purpose' => 'Vision modulOrchestr', 'wiring' => 'active'],
        ['id' => 'python.llm', 'module' => 'python', 'file' => 'app/Import/modulOrchestr/python/orchestr_engine/nodes/llm.py', 'fn' => 'llm_node()', 'purpose' => 'Import quality LangGraph', 'wiring' => 'partial'],
        ['id' => 'admin.ui_ollama', 'module' => 'erp', 'file' => 'admin/public/assets/js/admin-ollama-control.js', 'fn' => 'loadAll()', 'purpose' => 'Panou Control Ollama', 'wiring' => $uiJs ? 'active' : 'dead'],
    ];
}

function ollama_health_class_has_consumer(string $className, string $siteRoot): bool
{
    $siteRoot = ollama_site_root($siteRoot);
    $dirs = [
        $siteRoot . '/app/Backend/src',
        $siteRoot . '/app/Modules',
        $siteRoot . '/admin/public/api',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            if (str_contains($path, 'ShopChatOllamaAgentBridge.php')) {
                continue;
            }
            $content = @file_get_contents($path);
            if (is_string($content) && (str_contains($content, $className) || str_contains($content, 'ShopChatOllamaAgentBridge'))) {
                return true;
            }
        }
    }

    return false;
}

function ollama_health_file_contains(string $dir, string $needle): bool
{
    if (!is_dir($dir)) {
        return false;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $content = @file_get_contents($file->getPathname());
        if (is_string($content) && str_contains($content, $needle)) {
            return true;
        }
    }

    return false;
}

/** @return array<string, mixed> */
function ollama_health_test_integration(string $integrationId, ?string $projectRoot = null): array
{
    $projectRoot = ollama_site_root($projectRoot);
    $started = hrtime(true);
    $baseUrl = besoiu_ollama_base_url();
    $model = besoiu_ollama_model();

    try {
        $result = match ($integrationId) {
            'erp.client', 'erp.router', 'erp.agents' => ollama_health_test_chat($baseUrl, $model, 'Spune exact: OK ERP Ollama.'),
            'erp.shop_bridge' => ollama_health_test_shop_bridge($projectRoot),
            'admin.ui_ollama' => ['ok' => is_file($projectRoot . '/admin/public/assets/js/admin-ollama-control.js'), 'content' => 'UI OK', 'error' => ''],
            'erp.image_audit' => ollama_health_test_chat($baseUrl, besoiu_ollama_vision_model(), 'Răspunde JSON: {"verdict":"ok"}', true),
            'import.mapping' => ollama_health_test_chat($baseUrl, $model, 'Răspunde JSON: {"columns":{"sku":["x"]},"confidence":0.5}', false, 'json'),
            'scraper.relevance' => ollama_health_test_chat($baseUrl, $model, 'Răspunde JSON: {"relevant":true,"score":80,"verdict":"match","reason_ro":"test"}', false, 'json'),
            'orchestr.router', 'orchestr.vision', 'python.llm' => ollama_health_test_chat($baseUrl, $model, 'Răspunde JSON: {"verdict":"match","confidence":0.5}', false, 'json'),
            default => ['ok' => false, 'error' => 'Integrare necunoscută: ' . $integrationId, 'raw' => ''],
        };
    } catch (Throwable $e) {
        $result = ['ok' => false, 'error' => $e->getMessage(), 'raw' => ''];
    }

    $latency = (int) round((hrtime(true) - $started) / 1_000_000);
    $module = explode('.', $integrationId)[0] ?? 'erp';

    ollama_telemetry_record([
        'module' => $module,
        'url' => $baseUrl,
        'model' => $model,
        'ok' => !empty($result['ok']),
        'latency_ms' => $latency,
        'error' => (string) ($result['error'] ?? ''),
    ], $projectRoot);

    if (empty($result['ok'])) {
        ollama_error_log_append([
            'source' => 'health.test.' . $integrationId,
            'ok' => false,
            'error' => (string) ($result['error'] ?? 'eșec'),
            'model' => $model,
            'url' => $baseUrl,
            'latency_ms' => $latency,
        ], $projectRoot);
    }

    return array_merge($result, [
        'integration_id' => $integrationId,
        'latency_ms' => $latency,
        'model' => $model,
        'base_url' => $baseUrl,
    ]);
}

/** @return array{ok:bool,content?:string,error?:string,raw?:string} */
function ollama_health_test_shop_bridge(string $projectRoot): array
{
    $bridgeFile = $projectRoot . '/app/Backend/src/Services/ShopChatOllamaAgentBridge.php';
    if (!is_file($bridgeFile)) {
        return ['ok' => false, 'error' => 'ShopChatOllamaAgentBridge lipsă', 'raw' => ''];
    }

    if (!defined('BESOIU_ROOT')) {
        define('BESOIU_ROOT', $projectRoot);
    }
    if (!defined('BESOIU_APP')) {
        define('BESOIU_APP', $projectRoot . '/app');
    }
    if (!defined('BESOIU_ADMIN')) {
        define('BESOIU_ADMIN', $projectRoot . '/admin');
    }
    if (!is_file($projectRoot . '/admin/vendor/autoload.php')) {
        return ['ok' => false, 'error' => 'Composer admin lipsă', 'raw' => ''];
    }
    require_once $projectRoot . '/admin/vendor/autoload.php';
    require_once $bridgeFile;

    try {
        if (!\Config\Database::hasConnection()) {
            $config = require $projectRoot . '/admin/config/config.php';
            \Config\Database::getInstance(
                (string) ($config['db_host'] ?? '127.0.0.1'),
                (string) ($config['db_name'] ?? ''),
                (string) ($config['db_user'] ?? ''),
                (string) ($config['db_pass'] ?? '')
            );
        }
    } catch (Throwable) {
        // agent chat may still work fără BD
    }

    if (!\Besoiu\Services\ShopChatOllamaAgentBridge::isEnabled()) {
        return ['ok' => false, 'error' => 'SHOP_CHAT_OLLAMA_AGENTS=0', 'raw' => ''];
    }

    $bridge = new \Besoiu\Services\ShopChatOllamaAgentBridge($projectRoot . '/app');
    $reply = $bridge->tryReply('agent-produse', 'Spune doar: OK bridge.', [], '/', ['channel' => 'health_test']);
    if (is_array($reply) && !empty($reply['ok']) && trim((string) ($reply['content'] ?? '')) !== '') {
        return ['ok' => true, 'content' => trim((string) $reply['content']), 'raw' => ''];
    }

    return ['ok' => false, 'error' => 'Bridge fără răspuns valid', 'raw' => ''];
}

/** @return array{ok:bool,content?:string,error?:string,raw?:string} */
function ollama_health_test_chat(string $baseUrl, string $model, string $prompt, bool $vision = false, ?string $format = null): array
{
    if (!besoiu_ollama_enabled()) {
        return ['ok' => false, 'error' => 'OLLAMA_ENABLED=0', 'raw' => ''];
    }

    $payload = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'stream' => false,
        'options' => ['temperature' => 0.1, 'num_predict' => 64],
    ];
    if ($format !== null && $format !== '') {
        $payload['format'] = $format;
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return ['ok' => false, 'error' => 'Payload invalid', 'raw' => ''];
    }

    $ch = curl_init(rtrim($baseUrl, '/') . '/api/chat');
    if ($ch === false) {
        return ['ok' => false, 'error' => 'curl_init eșuat', 'raw' => ''];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $code >= 400) {
        return ['ok' => false, 'error' => 'HTTP ' . $code . ($err !== '' ? ': ' . $err : ''), 'raw' => ''];
    }

    $json = json_decode((string) $raw, true);
    $content = trim((string) ($json['message']['content'] ?? ''));
    if ($content === '') {
        return ['ok' => false, 'error' => 'Răspuns gol', 'raw' => (string) $raw];
    }

    return ['ok' => true, 'content' => $content, 'raw' => (string) $raw];
}

/** @return array<string, mixed> */
function ollama_health_snapshot(?string $projectRoot = null): array
{
    $projectRoot = ollama_site_root($projectRoot);
    require_once dirname(__DIR__) . '/src/Services/OllamaLlmClient.php';
    $client = new \Besoiu\Services\OllamaLlmClient($projectRoot);

    $modules = [];
    foreach (['erp', 'import', 'scraper', 'orchestr', 'python'] as $moduleId) {
        $modules[] = ollama_health_module_status($moduleId, $projectRoot);
    }

    return [
        'generated_at' => date('c'),
        'enabled' => besoiu_ollama_enabled(),
        'erp_readiness' => $client->readiness(),
        'vision_readiness' => $client->visionReadiness(),
        'modules' => $modules,
        'config_diff' => ollama_health_config_diff($projectRoot),
        'integrations' => ollama_health_integration_registry($projectRoot),
        'error_log' => ollama_error_log_recent(50, $projectRoot),
        'telemetry' => ollama_telemetry_all($projectRoot),
    ];
}
