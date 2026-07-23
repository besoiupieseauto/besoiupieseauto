<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/system/api_automation_guard.php';

/**
 * Catalog consum API — automat vs manual, per proiect și sursă.
 */

/** @return list<string> */
function api_automation_auto_source_patterns(): array
{
    return [
        'cron',
        'pipeline',
        'image_pipeline',
        'import',
        'scrape_do',
        'ai_agent',
        'context_brief',
        'context-master',
        'section_assistant',
        'supervisor',
        'autonomy',
        'consumable',
        'dual_scan',
        'queue_worker',
        'search-for-cross-numbers',
        'tecdoc_find',
        'epiesa',
        'emag',
    ];
}

/** @return list<string> */
function api_automation_manual_source_patterns(): array
{
    return [
        'health',
        'test_',
        'manual',
        'verify',
        'audit_user',
    ];
}

function api_automation_classify_usage(?string $source, ?string $note, ?string $providerKey = null): string
{
    $blob = strtolower(trim(($source ?? '') . ' ' . ($note ?? '')));

    foreach (api_automation_manual_source_patterns() as $needle) {
        if ($needle !== '' && str_contains($blob, $needle)) {
            return 'manual';
        }
    }

    foreach (api_automation_auto_source_patterns() as $needle) {
        if ($needle !== '' && str_contains($blob, $needle)) {
            return 'auto';
        }
    }

    if ($source === 'tecdoc_http_get') {
        if (str_contains($blob, '/models/list') || str_contains($blob, '/types/type-id')) {
            return 'manual';
        }

        return 'auto';
    }

    if (in_array($providerKey, ['cursor', 'openai', 'groq', 'gemini', 'grok'], true)) {
        return 'auto';
    }

    return 'unknown';
}

function api_automation_mode_label(string $mode): string
{
    return match ($mode) {
        'auto' => 'Automat',
        'manual' => 'Manual',
        default => 'Necunoscut',
    };
}

/** @return list<array<string, mixed>> */
function api_automation_projects(): array
{
    $besoiuAdminPaused = besoiu_api_automation_paused();

    return [
        [
            'id' => 'besoiu_admin',
            'label' => 'Besoiu Piese Auto — Admin',
            'domain' => 'besoiupieseauto.ro',
            'path' => '/admin/settings?tab=tokens',
            'is_local' => true,
            'automation_paused' => $besoiuAdminPaused,
            'pause_env' => 'API_AUTOMATION_DISABLED',
            'note' => 'Proiectul curent — control complet din acest panou.',
        ],
        [
            'id' => 'aibotpiese',
            'label' => 'aibotpiese.online',
            'domain' => 'aibotpiese.online',
            'path' => null,
            'is_local' => false,
            'automation_paused' => null,
            'pause_env' => 'API_AUTOMATION_DISABLED',
            'note' => 'Proiect separat (legacy bot). Dacă folosește aceeași cheie RapidAPI, consumul se cumulează pe contul RapidAPI.',
            'checklist' => [
                'Setează API_AUTOMATION_DISABLED=1 în .env (dacă există guard) sau oprește cron/Task Scheduler.',
                'Dezactivează robot/tecdoc_proxy.php sau scoate RAPIDAPI_AUTOPARTS_KEY din .env.',
                'Oprește webhook/cron WhatsApp dacă apelează TecDoc.',
                'Verifică pe rapidapi.com → Usage (cheia poate fi partajată cu Besoiu Admin).',
            ],
        ],
    ];
}

function api_automation_stealth_browser_available(): bool
{
    $client = dirname(__DIR__, 2) . '/lib/Scraper/StealthBrowserClient.php';
    if (!is_file($client)) {
        return false;
    }
    require_once $client;

    return class_exists('StealthBrowserClient', false) && StealthBrowserClient::isAvailable();
}

/** @param list<string> $providers */
function api_automation_consumer_provider_enabled(array $providers): bool
{
    $llmKeys = ['cursor', 'openai', 'groq', 'gemini', 'grok'];
    $budgetFile = __DIR__ . '/api_token_budget.php';
    if (!function_exists('api_token_budget_provider_live_enabled') && is_file($budgetFile)) {
        require_once $budgetFile;
    }

    foreach ($providers as $provider) {
        $provider = (string) $provider;
        if ($provider === 'stealth_browser') {
            if (api_automation_stealth_browser_available()) {
                return true;
            }
            continue;
        }
        if (in_array($provider, $llmKeys, true)) {
            if (function_exists('api_token_budget_provider_live_enabled') && api_token_budget_provider_live_enabled('llm')) {
                return true;
            }
            continue;
        }
        if (function_exists('api_token_budget_provider_live_enabled') && api_token_budget_provider_live_enabled($provider)) {
            return true;
        }
    }

    return false;
}

/** @param array<string, mixed> $consumer */
function api_automation_consumer_effective_status(array $consumer, bool $paused): string
{
    $providers = is_array($consumer['providers'] ?? null) ? $consumer['providers'] : [];
    if ($providers === [] || !api_automation_consumer_provider_enabled($providers)) {
        return 'blocked';
    }

    $id = (string) ($consumer['id'] ?? '');

    if ($id === 'cron_sync_import' && !besoiu_api_automation_env_truthy('CRON_LIGHT_ENRICH')) {
        return 'blocked';
    }
    if ($id === 'cron_epiesa' && !besoiu_api_automation_env_truthy('CRON_CHECK_EPIESA')) {
        return 'blocked';
    }

    if (($consumer['mode'] ?? '') === 'auto' && $paused) {
        if (besoiu_api_providers_use_llm($providers)) {
            return 'blocked';
        }

        if (!function_exists('besoiu_api_auto_tecdoc_scrape_enabled') || !besoiu_api_auto_tecdoc_scrape_enabled()) {
            return 'blocked';
        }
    }

    return 'active';
}

/** Motiv afișat în matrice când status = blocked (null dacă activ). */
function api_automation_consumer_block_reason(array $consumer, bool $paused): ?string
{
    if (api_automation_consumer_effective_status($consumer, $paused) !== 'blocked') {
        return null;
    }

    $providers = is_array($consumer['providers'] ?? null) ? $consumer['providers'] : [];
    if ($providers === [] || !api_automation_consumer_provider_enabled($providers)) {
        return 'Provider oprit — secțiunea «API activ per provider» de mai sus';
    }

    $id = (string) ($consumer['id'] ?? '');

    if ($id === 'cron_sync_import' && !besoiu_api_automation_env_truthy('CRON_LIGHT_ENRICH')) {
        return 'Bifează «Enrichment TecDoc la import cron» + Salvează control consum';
    }
    if ($id === 'cron_epiesa' && !besoiu_api_automation_env_truthy('CRON_CHECK_EPIESA')) {
        return 'Bifează «ePiesa la cron» + Salvează control consum';
    }

    if (($consumer['mode'] ?? '') === 'auto' && $paused) {
        if (besoiu_api_providers_use_llm($providers)) {
            return 'AI/Cursor — necesită «Armez API» sau dezactivare fundal OPRIT';
        }

        if (!function_exists('besoiu_api_auto_tecdoc_scrape_enabled') || !besoiu_api_auto_tecdoc_scrape_enabled()) {
            return 'Bifează «Cron TecDoc + Scrape.do (fără Cursor AI)» + Salvează';
        }
    }

    return 'Verifică setările de mai sus';
}

/** @param array<string, mixed> $input @return array{ok:bool,message:string,updated:list<string>} */
function api_automation_save_provider_live(array $input): array
{
    require_once __DIR__ . '/api_token_budget.php';
    require_once __DIR__ . '/env_settings.php';

    $pdo = api_token_budget_pdo();
    $map = [
        'stealth_browser' => 'stealth_browser',
        'rapidapi_tecdoc' => 'rapidapi_tecdoc',
        'scrape_do' => 'scrape_do',
        'cursor' => 'cursor',
    ];
    $updated = [];
    foreach ($map as $inputKey => $providerKey) {
        if (!array_key_exists($inputKey, $input)) {
            continue;
        }
        $raw = $input[$inputKey];
        $on = $raw === true || $raw === 1 || $raw === '1' || $raw === 'true' || $raw === 'on';
        api_token_budget_set_provider_live($pdo, $providerKey, $on);
        $updated[] = $providerKey . '=' . ($on ? '1' : '0');
    }

    if ($updated === []) {
        return ['ok' => false, 'message' => 'Niciun provider de salvat.', 'updated' => []];
    }

    $envChanges = [];
    if (array_key_exists('stealth_browser', $input)) {
        $stealthOn = $input['stealth_browser'] === true || $input['stealth_browser'] === 1 || $input['stealth_browser'] === '1';
        $envChanges['STEALTH_BROWSER_ENABLED'] = $stealthOn ? '1' : '0';
    }
    if (array_key_exists('rapidapi_tecdoc', $input)) {
        $tecOn = $input['rapidapi_tecdoc'] === true || $input['rapidapi_tecdoc'] === 1 || $input['rapidapi_tecdoc'] === '1';
        if ($tecOn && function_exists('tecdoc_clear_quota_unavailable_flag')) {
            require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';
            tecdoc_clear_quota_unavailable_flag();
        }
    }
    if ($envChanges !== []) {
        besoiu_env_write_values($envChanges);
    }

    api_token_budget_sync_supervisor_store();

    return [
        'ok' => true,
        'message' => 'Provideri API actualizați: ' . implode(', ', $updated) . '.',
        'updated' => $updated,
    ];
}

/** @return list<array<string, mixed>> */
function api_automation_consumer_catalog(): array
{
    $paused = besoiu_api_automation_paused();
    $cronLight = false;
    $cronEpiesa = false;

    $rows = [
        [
            'id' => 'cron_ai_agent',
            'project' => 'besoiu_admin',
            'label' => 'Ciclu autonom AI Agent',
            'mode' => 'auto',
            'providers' => ['cursor', 'openai', 'groq', 'gemini', 'grok'],
            'schedule' => 'Dezactivat',
            'script' => 'Modul cron eliminat',
            'expect' => 'Modul dezactivat',
            'status' => 'blocked',
        ],
        [
            'id' => 'cron_image_pipeline',
            'project' => 'besoiu_admin',
            'label' => 'Retry imagini pipeline',
            'mode' => 'auto',
            'providers' => ['rapidapi_tecdoc', 'scrape_do'],
            'schedule' => 'Dezactivat',
            'script' => 'Modul cron eliminat',
            'expect' => 'Modul dezactivat',
            'status' => 'blocked',
        ],
        [
            'id' => 'cron_sync_import',
            'project' => 'besoiu_admin',
            'label' => 'Cron Sync — import furnizori',
            'mode' => 'auto',
            'providers' => ['rapidapi_tecdoc', 'scrape_do'],
            'schedule' => 'Dezactivat',
            'script' => 'Modul cron/import eliminat',
            'expect' => 'Modul dezactivat',
            'status' => 'blocked',
        ],
        [
            'id' => 'cron_epiesa',
            'project' => 'besoiu_admin',
            'label' => 'Import ePiesa la cron',
            'mode' => 'auto',
            'providers' => ['stealth_browser'],
            'schedule' => 'Dezactivat',
            'script' => 'Modul cron eliminat',
            'expect' => 'Modul dezactivat',
            'status' => 'blocked',
        ],
        [
            'id' => 'public_tecdoc_proxy',
            'project' => 'besoiu_admin',
            'label' => 'Site public — filterbar TecDoc',
            'mode' => 'manual',
            'providers' => ['rapidapi_tecdoc'],
            'schedule' => 'La fiecare vizită / selectare marcă',
            'script' => 'tecdoc_proxy.php?action=get_models',
            'expect' => '1 credit RapidAPI / selectare marcă (dacă nu e în cache)',
            'status' => $paused ? 'blocked' : 'active',
        ],
        [
            'id' => 'product_page_tecdoc',
            'project' => 'besoiu_admin',
            'label' => 'Pagini produs — descriere TecDoc',
            'mode' => 'auto',
            'providers' => ['rapidapi_tecdoc'],
            'schedule' => 'La vizită produs fără descriere completă',
            'script' => 'product.php → tecdoc_build_product_description',
            'expect' => '1–10 credite / produs (căutări OEM)',
            'status' => $paused ? 'blocked' : 'active',
        ],
        [
            'id' => 'import_manual',
            'project' => 'besoiu_admin',
            'label' => 'Import / scan manual admin',
            'mode' => 'manual',
            'providers' => ['rapidapi_tecdoc', 'stealth_browser', 'cursor'],
            'schedule' => 'Dezactivat',
            'script' => 'Modul import eliminat',
            'expect' => 'Modul dezactivat',
            'status' => 'blocked',
        ],
        [
            'id' => 'scraper_admin',
            'project' => 'besoiu_admin',
            'label' => 'Scraper admin (ePiesa / eMAG / plan)',
            'mode' => 'manual',
            'providers' => ['stealth_browser', 'cursor'],
            'schedule' => 'La rulare din /admin/scraper',
            'script' => 'lib/Scraper/* → StealthBrowserClient',
            'expect' => 'Chrome local nodriver — 0 credite / cerere (+ LLM opțional)',
            'status' => $paused ? 'blocked' : 'active',
        ],
        [
            'id' => 'robot_tecdoc',
            'project' => 'besoiu_admin',
            'label' => 'Robot admin — tecdoc_proxy',
            'mode' => 'manual',
            'providers' => ['rapidapi_tecdoc'],
            'schedule' => 'Sesione admin autentificat',
            'script' => 'robot/tecdoc_proxy.php',
            'expect' => '1 credit / endpoint (cache reduce)',
            'status' => $paused ? 'blocked' : 'active',
        ],
        [
            'id' => 'scraper_tecdoc_api',
            'project' => 'besoiu_admin',
            'label' => 'Scraper admin — TecDoc API',
            'mode' => 'manual',
            'providers' => ['rapidapi_tecdoc'],
            'schedule' => 'La test din /admin/scraper → TecDoc API',
            'script' => 'lib/Scraper/TecDocApiTestRunner.php',
            'expect' => '1 credit RapidAPI / cod OEM sau vehicul',
            'status' => $paused ? 'blocked' : 'active',
        ],
    ];

    foreach ($rows as &$row) {
        $row['status'] = api_automation_consumer_effective_status($row, $paused);
        $row['block_reason'] = api_automation_consumer_block_reason($row, $paused);
    }
    unset($row);

    return $rows;
}

/** @return array<string, array{auto:int,manual:int,unknown:int,total:int}> */
function api_automation_usage_mode_stats(PDO $pdo): array
{
    $stats = [];
    try {
        $stmt = $pdo->query(
            "SELECT provider_key, source, note, units
             FROM api_token_usage_log
             WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pk = (string) ($row['provider_key'] ?? '');
            if ($pk === '') {
                continue;
            }
            if (!isset($stats[$pk])) {
                $stats[$pk] = ['auto' => 0, 'manual' => 0, 'unknown' => 0, 'total' => 0];
            }
            $units = max(1, (int) ($row['units'] ?? 1));
            $mode = api_automation_classify_usage(
                isset($row['source']) ? (string) $row['source'] : null,
                isset($row['note']) ? (string) $row['note'] : null,
                $pk
            );
            $stats[$pk][$mode] = ($stats[$pk][$mode] ?? 0) + $units;
            $stats[$pk]['total'] += $units;
        }
    } catch (Throwable) {
        // ignore
    }

    return $stats;
}

/** @return list<array<string, mixed>> */
function api_automation_recent_usage(PDO $pdo, int $limit = 25): array
{
    $limit = max(1, min(100, $limit));
    $rows = [];

    try {
        $stmt = $pdo->query(
            "SELECT provider_key, units, source, note, created_at
             FROM api_token_usage_log
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $provider = (string) ($row['provider_key'] ?? '');
            $source = isset($row['source']) ? (string) $row['source'] : '';
            $note = isset($row['note']) ? (string) $row['note'] : '';
            $mode = api_automation_classify_usage($source, $note, $provider);
            $rows[] = [
                'provider_key' => $provider,
                'units' => (int) ($row['units'] ?? 1),
                'source' => $source,
                'note' => $note,
                'mode' => $mode,
                'mode_label' => api_automation_mode_label($mode),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
    } catch (Throwable) {
        return [];
    }

    return $rows;
}

/** @return array<string, mixed> */
function api_automation_controls_state(): array
{
    $budgetFile = __DIR__ . '/api_token_budget.php';
    $providerLive = [];
    if (is_file($budgetFile)) {
        require_once $budgetFile;
        $providerLive = api_token_budget_live_states();
    }

    return [
        'api_automation_disabled' => besoiu_api_automation_paused(),
        'api_strict_manual' => besoiu_api_strict_manual(),
        'api_live_armed' => besoiu_api_session_armed(),
        'api_live_arm' => besoiu_api_arm_state(),
        'cron_light_enrich' => besoiu_api_automation_env_truthy('CRON_LIGHT_ENRICH'),
        'cron_check_epiesa' => besoiu_api_automation_env_truthy('CRON_CHECK_EPIESA'),
        'image_audit_auto_retry' => besoiu_api_automation_env_truthy('IMAGE_AUDIT_AUTO_RETRY'),
        'api_auto_tecdoc_scrape' => function_exists('besoiu_api_auto_tecdoc_scrape_enabled')
            ? besoiu_api_auto_tecdoc_scrape_enabled()
            : !besoiu_api_automation_paused(),
        'provider_live' => [
            'stealth_browser' => api_automation_stealth_browser_available(),
            'rapidapi_tecdoc' => !empty($providerLive['rapidapi_tecdoc']),
            'scrape_do' => !empty($providerLive['scrape_do']),
            'cursor' => !empty($providerLive['cursor']),
        ],
        'provider_live_allowed' => [
            'rapidapi_tecdoc' => besoiu_api_live_call_allowed('rapidapi_tecdoc'),
            'scrape_do' => besoiu_api_live_call_allowed('scrape_do'),
            'llm' => besoiu_api_live_call_allowed('llm'),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function api_automation_hub_snapshot(?PDO $pdo = null): array
{
    require_once __DIR__ . '/api_token_budget.php';

    $pdo = $pdo ?? api_token_budget_pdo();
    $controls = api_automation_controls_state();
    $consumers = api_automation_consumer_catalog();
    $activeAuto = 0;
    $blockedAuto = 0;
    foreach ($consumers as $c) {
        if (($c['mode'] ?? '') !== 'auto') {
            continue;
        }
        if (($c['status'] ?? '') === 'blocked') {
            ++$blockedAuto;
        } elseif (($c['status'] ?? '') === 'active') {
            ++$activeAuto;
        }
    }

    return [
        'generated_at' => date('c'),
        'controls' => $controls,
        'projects' => api_automation_projects(),
        'consumers' => $consumers,
        'usage_mode_stats' => api_automation_usage_mode_stats($pdo),
        'recent_usage' => api_automation_recent_usage($pdo, 30),
        'summary' => [
            'automation_paused' => $controls['api_automation_disabled'],
            'strict_manual' => $controls['api_strict_manual'],
            'api_live_armed' => $controls['api_live_armed'],
            'api_live_arm' => $controls['api_live_arm'],
            'auto_consumers_active' => $activeAuto,
            'auto_consumers_blocked' => $blockedAuto,
            'live_calls_allowed' => besoiu_api_live_call_allowed('rapidapi_tecdoc'),
        ],
    ];
}

/**
 * @param array<string, string|int|bool> $input
 * @return array{ok:bool,message:string,updated:list<string>}
 */
function api_automation_save_controls(array $input): array
{
    require_once __DIR__ . '/env_settings.php';

    $map = [
        'api_automation_disabled' => 'API_AUTOMATION_DISABLED',
        'api_auto_tecdoc_scrape' => 'API_AUTO_TECDOC_SCRAPE',
        'cron_light_enrich' => 'CRON_LIGHT_ENRICH',
        'cron_check_epiesa' => 'CRON_CHECK_EPIESA',
        'image_audit_auto_retry' => 'IMAGE_AUDIT_AUTO_RETRY',
    ];

    $changes = [];
    foreach ($map as $inputKey => $envKey) {
        if (!array_key_exists($inputKey, $input)) {
            continue;
        }
        $raw = $input[$inputKey];
        $on = $raw === true || $raw === 1 || $raw === '1' || $raw === 'true' || $raw === 'on';
        $changes[$envKey] = $on ? '1' : '0';
    }

    if ($changes === []) {
        return ['ok' => false, 'message' => 'Nicio setare de salvat.', 'updated' => []];
    }

    $res = besoiu_env_write_values($changes);
    if (!$res['ok']) {
        return $res;
    }

    if (($changes['API_AUTOMATION_DISABLED'] ?? null) === '0') {
        if (function_exists('tecdoc_clear_quota_unavailable_flag')) {
            require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';
            tecdoc_clear_quota_unavailable_flag();
        }
        $cycleRes = besoiu_env_write_values([
            'AI_AGENT_CYCLE_ENABLED' => '1',
            'OLLAMA_LOCAL_CYCLE' => '1',
            'LLM_ROUTER_MODE' => 'ollama_first',
        ]);
        if (!empty($cycleRes['updated'])) {
            $res['updated'] = array_merge($res['updated'] ?? [], $cycleRes['updated']);
        }
    }

    if (($changes['API_AUTO_TECDOC_SCRAPE'] ?? null) === '1') {
        if (function_exists('tecdoc_clear_quota_unavailable_flag')) {
            require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';
            tecdoc_clear_quota_unavailable_flag();
        }
    }

    return [
        'ok' => true,
        'message' => 'Control consum API salvat.',
        'updated' => $res['updated'],
    ];
}

/**
 * @return array{ok:bool,message:string,arm:array<string,mixed>}
 */
function api_automation_arm_live(int $minutes = 30): array
{
    if (!besoiu_api_admin_session_active()) {
        return ['ok' => false, 'message' => 'Autentificare admin necesară.', 'arm' => besoiu_api_arm_state()];
    }

    $arm = besoiu_api_arm_session($minutes);
    $state = besoiu_api_arm_state();

    if (!empty($arm['permanent'])) {
        $message = 'API live MEREU ACTIV — consum plătit permis până apeși «Oprit complet».';
    } elseif ($minutes >= 240) {
        $hours = (int) round($minutes / 60);
        $message = 'API live ARMAT ' . $hours . ' h — poți rula import, sync furnizori, scraper, TecDoc.';
    } else {
        $message = 'API live ARMAT ' . $arm['minutes'] . ' min — poți rula import, sync furnizori, scraper, TecDoc.';
    }

    return [
        'ok' => true,
        'message' => $message,
        'arm' => array_merge($state, ['armed' => true]),
    ];
}

/** @return array{ok:bool,message:string,arm:array<string,mixed>} */
function api_automation_disarm_live(): array
{
    besoiu_api_disarm_session();
    $deactivated = besoiu_api_deactivate_paid_providers();

    return [
        'ok' => true,
        'message' => 'API live OPRIT COMPLET — Cursor, RapidAPI, scrape.do dezactivate. Groq/Gemini/Ollama rămân pentru orchestra free.',
        'arm' => besoiu_api_arm_state(),
        'providers_deactivated' => $deactivated,
    ];
}

/**
 * Puls live pentru tab Supervizor — fără rulare ciclu complet.
 *
 * @return array<string, mixed>
 */
function api_supervisor_live_pulse(?string $projectRoot = null): array
{
    $projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    $paused = besoiu_api_automation_paused();
    $controls = api_automation_controls_state();

    $lastCycleAt = null;
    $lastCycleAgeMin = null;
    $cycleStale = false;
    $cycleFile = $projectRoot . '/robot/data/ai_supervisor/last_cycle.json';
    if (is_file($cycleFile)) {
        $raw = @file_get_contents($cycleFile);
        if (is_string($raw) && $raw !== '') {
            $cycle = json_decode($raw, true);
            if (is_array($cycle)) {
                $lastCycleAt = (string) ($cycle['started_at'] ?? $cycle['finished_at'] ?? '');
                if ($lastCycleAt !== '') {
                    $ts = strtotime($lastCycleAt);
                    if ($ts !== false) {
                        $lastCycleAgeMin = (int) max(0, floor((time() - $ts) / 60));
                        $threshold = $paused ? 60 : 10;
                        $cycleStale = $lastCycleAgeMin >= $threshold;
                    }
                }
            }
        }
    }

    $ops = ['status' => 'ok', 'critical' => 0, 'warning' => 0, 'total' => 0];
    $alertsHelper = dirname(__DIR__) . '/src/Services/AdminOpsAlertsService.php';
    if (is_file($alertsHelper)) {
        require_once $alertsHelper;
        try {
            $feed = (new \Besoiu\Services\AdminOpsAlertsService())->feed();
            $items = is_array($feed['items'] ?? null) ? $feed['items'] : [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $level = (string) ($item['level'] ?? '');
                if ($level === 'critical') {
                    ++$ops['critical'];
                } elseif ($level === 'warning') {
                    ++$ops['warning'];
                }
            }
            $ops['total'] = $ops['critical'] + $ops['warning'];
            $ops['status'] = (string) ($feed['status'] ?? 'ok');
        } catch (Throwable) {
            $ops['status'] = 'unknown';
        }
    }

    if ($paused) {
        $ollamaCycle = false;
        $ollamaReady = false;
        $ollamaHelper = __DIR__ . '/ollama_llm.php';
        if (is_file($ollamaHelper)) {
            require_once $ollamaHelper;
            $ollamaCycle = besoiu_ollama_local_cycle_enabled();
            $ollamaClient = dirname(__DIR__) . '/src/Services/OllamaLlmClient.php';
            if (is_file($ollamaClient)) {
                require_once $ollamaClient;
                $ollamaReady = (new \Besoiu\Services\OllamaLlmClient($projectRoot))->readiness()['ready'] ?? false;
            }
        }
        if ($ollamaCycle && $ollamaReady) {
            $message = 'Mod la cerere — API cloud oprit. Ciclu local Ollama activ (fără tokeni Cursor). Apasă «Rulează ciclu» pentru scan proaspăt.';
        } else {
            $message = 'Mod la cerere — fundal oprit (API_AUTOMATION_DISABLED). Pornește Ollama (ollama serve) sau apasă «Rulează ciclu».';
        }
    } elseif ($cycleStale) {
        $message = 'Cron întârziat — ultimul ciclu are ' . ($lastCycleAgeMin ?? '?') . ' min. Verifică task-ul ai_agent_cycle.';
    } else {
        $message = 'Orchestră free activă — Ollama + cloud gratuit în fundal. Cursor plătit doar după «Armez API» când free eșuează.';
    }

    $ollamaStatus = ['enabled' => false, 'ready' => false, 'model' => ''];
    $llmRouterMode = 'ollama_first';
    $ollamaHelper = __DIR__ . '/ollama_llm.php';
    if (is_file($ollamaHelper)) {
        require_once $ollamaHelper;
        $llmRouterMode = besoiu_llm_router_mode();
        $ollamaClient = dirname(__DIR__) . '/src/Services/OllamaLlmClient.php';
        if (is_file($ollamaClient)) {
            require_once $ollamaClient;
            $ollamaStatus = (new \Besoiu\Services\OllamaLlmClient($projectRoot))->readiness();
        }
    }

    return [
        'synced_at' => date('c'),
        'api_on_demand' => $paused,
        'cron_expected' => !$paused,
        'last_cycle_at' => $lastCycleAt,
        'last_cycle_age_minutes' => $lastCycleAgeMin,
        'cycle_stale' => $cycleStale,
        'message' => $message,
        'controls' => $controls,
        'ops_alerts' => $ops,
        'ollama' => $ollamaStatus,
        'llm_router_mode' => $llmRouterMode,
        'local_cycle_allowed' => $paused && function_exists('besoiu_ollama_local_cycle_enabled') && besoiu_ollama_local_cycle_enabled(),
    ];
}

function api_automation_provider_external_hint(string $providerKey): string
{
    require_once __DIR__ . '/api_token_budget.php';

    return match (api_token_budget_normalize_provider($providerKey)) {
        'stealth_browser' => 'ePiesa, eMAG, Autodoc — fetch HTML (stealth-browser-mcp local)',
        'scrape_do' => 'Fallback opțional (SCRAPER_FALLBACK_SCRAPE_DO=1) — proxy Scrape.do',
        'rapidapi_tecdoc' => 'RapidAPI auto-parts-catalog — OEM, VIN, imagini TecDoc',
        'cursor' => 'Cursor API — agent AI, audit imagini, reparații cod',
        'openai' => 'OpenAI — chat, vision, analiză produse',
        'groq' => 'Groq — robot WhatsApp, răspunsuri rapide',
        'gemini' => 'Google Gemini — alternative AI',
        'grok' => 'xAI Grok — alternative AI',
        'ollama' => 'Ollama local pe server — fără cost cloud',
        default => '',
    };
}

/** @return list<array{hour:int,provider_key:string,units:int}> */
function api_automation_hourly_usage_today(PDO $pdo): array
{
    $rows = [];

    try {
        $stmt = $pdo->query(
            "SELECT HOUR(created_at) AS hour_slot, provider_key, SUM(units) AS units
             FROM api_token_usage_log
             WHERE DATE(created_at) = CURDATE()
             GROUP BY HOUR(created_at), provider_key
             ORDER BY hour_slot ASC"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'hour' => (int) ($row['hour_slot'] ?? 0),
                'provider_key' => (string) ($row['provider_key'] ?? ''),
                'units' => (int) ($row['units'] ?? 0),
            ];
        }
    } catch (Throwable) {
        return [];
    }

    return $rows;
}

/** @return list<array<string, mixed>> */
function api_automation_recent_ai_usage(PDO $pdo, int $limit = 25): array
{
    $aiFile = __DIR__ . '/ai_token_usage.php';
    if (!is_file($aiFile)) {
        return [];
    }

    try {
        require_once $aiFile;
        if (!function_exists('ai_token_query')) {
            return [];
        }

        $items = ai_token_query($pdo, ['limit' => max(1, min(100, $limit))])['items'] ?? [];
    } catch (Throwable) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'kind' => 'ai',
            'provider_key' => (string) ($row['provider'] ?? ''),
            'units' => (int) ($row['total_tokens'] ?? 0),
            'source' => (string) ($row['source'] ?? ''),
            'note' => (string) ($row['model'] ?? ''),
            'mode' => 'ai',
            'mode_label' => 'Tokeni AI',
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }, $items);
}

/** @return array<string, mixed> */
function api_automation_usage_live_snapshot(?PDO $pdo = null): array
{
    require_once __DIR__ . '/api_token_budget.php';

    $pdo = $pdo ?? api_token_budget_pdo();
    $controls = api_automation_controls_state();
    $budgets = api_token_budget_list($pdo);
    $stats = api_token_budget_stats($pdo);
    $modeStats = api_automation_usage_mode_stats($pdo);
    $consumers = api_automation_consumer_catalog();
    $providerLive = api_token_budget_live_states($pdo);

    $providers = [];
    foreach ($budgets as $budget) {
        $key = (string) ($budget['provider_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $providerStats = $stats['by_provider'][$key] ?? [];
        $usage = api_token_budget_compute_usage($budget, $providerStats);
        $modes = $modeStats[$key] ?? ['auto' => 0, 'manual' => 0, 'unknown' => 0, 'total' => 0];

        $providers[] = [
            'provider_key' => $key,
            'label' => (string) ($budget['label'] ?? $key),
            'env_key' => (string) ($budget['env_key'] ?? ''),
            'external_hint' => api_automation_provider_external_hint($key),
            'is_active' => !empty($budget['is_active']),
            'live_enabled' => !empty($providerLive[$key]),
            'calls_allowed' => (static function () use ($key): bool {
                try {
                    return besoiu_api_live_call_allowed(
                        $key === 'cursor' || in_array($key, ['openai', 'groq', 'gemini', 'grok'], true) ? 'llm' : $key
                    );
                } catch (Throwable) {
                    return false;
                }
            })(),
            'today_units' => (int) ($providerStats['today_units'] ?? 0),
            'month_units' => (int) ($providerStats['month_units'] ?? 0),
            'month_cost_ron' => (float) ($providerStats['month_cost'] ?? 0),
            'tokens_per_request' => (int) ($usage['tokens_per_request'] ?? 1),
            'month_tokens_used' => (int) ($usage['used_tokens'] ?? 0),
            'month_tokens_remaining' => (int) ($usage['remaining_tokens'] ?? 0),
            'monthly_quota' => (int) ($usage['monthly_quota'] ?? 0),
            'used_pct' => (int) ($usage['used_pct'] ?? 0),
            'mode_auto' => (int) ($modes['auto'] ?? 0),
            'mode_manual' => (int) ($modes['manual'] ?? 0),
        ];
    }

    usort($providers, static function (array $a, array $b): int {
        $ta = (int) ($a['today_units'] ?? 0);
        $tb = (int) ($b['today_units'] ?? 0);
        if ($ta !== $tb) {
            return $tb <=> $ta;
        }

        return strcmp((string) ($a['provider_key'] ?? ''), (string) ($b['provider_key'] ?? ''));
    });

    $recentApi = array_map(static function (array $row): array {
        $row['kind'] = 'api';

        return $row;
    }, api_automation_recent_usage($pdo, 40));

    $recentAi = api_automation_recent_ai_usage($pdo, 20);
    $timeline = array_merge($recentApi, $recentAi);
    usort($timeline, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    $timeline = array_slice($timeline, 0, 50);

    $externalResources = [];
    foreach ($consumers as $consumer) {
        if (!is_array($consumer)) {
            continue;
        }
        $externalResources[] = [
            'label' => (string) ($consumer['label'] ?? ''),
            'mode' => (string) ($consumer['mode'] ?? ''),
            'mode_label' => api_automation_mode_label((string) ($consumer['mode'] ?? '')),
            'providers' => array_values(array_map('strval', $consumer['providers'] ?? [])),
            'schedule' => (string) ($consumer['schedule'] ?? ''),
            'expect' => (string) ($consumer['expect'] ?? ''),
            'status' => (string) ($consumer['status'] ?? ''),
            'block_reason' => (string) ($consumer['block_reason'] ?? ''),
        ];
    }

    $aiStats = [];
    $aiFile = __DIR__ . '/ai_token_usage.php';
    if (is_file($aiFile)) {
        try {
            require_once $aiFile;
            if (function_exists('ai_token_stats')) {
                $aiStats = ai_token_stats($pdo);
            }
        } catch (Throwable) {
            $aiStats = [];
        }
    }

    $todayApiUnits = 0;
    foreach ($stats['by_provider'] ?? [] as $row) {
        $todayApiUnits += (int) ($row['today_units'] ?? 0);
    }

    return [
        'generated_at' => date('c'),
        'controls' => $controls,
        'summary' => [
            'automation_paused' => !empty($controls['api_automation_disabled']),
            'api_live_armed' => !empty($controls['api_live_armed']),
            'strict_manual' => ($controls['api_strict_manual'] ?? true) !== false,
            'today_api_units' => $todayApiUnits,
            'today_ai_tokens' => (int) ($aiStats['today'] ?? 0),
            'month_ai_tokens' => (int) ($aiStats['month'] ?? 0),
            'auto_consumers_active' => count(array_filter(
                $externalResources,
                static fn (array $r): bool => ($r['mode'] ?? '') === 'auto' && ($r['status'] ?? '') === 'active'
            )),
        ],
        'providers' => $providers,
        'external_resources' => $externalResources,
        'hourly_today' => api_automation_hourly_usage_today($pdo),
        'timeline' => $timeline,
        'ai_stats' => $aiStats,
    ];
}
