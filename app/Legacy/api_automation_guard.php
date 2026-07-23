<?php

declare(strict_types=1);

/**
 * Politică consum API — control strict de utilizator.
 *
 * - API_AUTOMATION_DISABLED=1 → fundal/cron/site fără API plătit.
 * - API_STRICT_MANUAL=1 (implicit când fundal oprit) → API live doar după «Armez API» în admin.
 * - CLI one-shot: BESOIU_API_LIVE_OVERRIDE=1 php ...
 */

function besoiu_api_automation_env_loaded(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (function_exists('tecdoc_load_env')) {
        tecdoc_load_env();
    } else {
        $envPath = BESOIU_CONFIG . '/.env';
        if (is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $k = trim($key);
                if ($k !== '' && !isset($_ENV[$k])) {
                    $_ENV[$k] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }
        }
    }

    $done = true;
}

function besoiu_api_automation_env_truthy(string $key): bool
{
    besoiu_api_automation_env_loaded();
    $raw = strtolower(trim((string) ($_ENV[$key] ?? getenv($key) ?: '0')));

    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

/** Fundal oprit — fără consum API automat (implicit recomandat: 1). */
function besoiu_api_automation_paused(): bool
{
    return besoiu_api_automation_env_truthy('API_AUTOMATION_DISABLED');
}

/**
 * Cron / site automat TecDoc + Scrape.do fără Cursor AI — când fundalul e oprit.
 * API_AUTO_TECDOC_SCRAPE=1
 */
function besoiu_api_auto_tecdoc_scrape_enabled(): bool
{
    if (!besoiu_api_automation_paused()) {
        return true;
    }

    return besoiu_api_automation_env_truthy('API_AUTO_TECDOC_SCRAPE');
}

/** @param list<string> $providers */
function besoiu_api_providers_use_llm(array $providers): bool
{
    $llmKeys = ['openai', 'groq', 'gemini', 'grok'];
    foreach ($providers as $provider) {
        if (in_array((string) $provider, $llmKeys, true)) {
            return true;
        }
    }

    return false;
}

function besoiu_api_is_public_product_tecdoc_context(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    $hay = strtolower(implode(' ', array_filter([
        (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''),
        (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
        (string) ($_SERVER['REQUEST_URI'] ?? ''),
    ])));

    return str_contains($hay, 'product.php');
}

/** Cereri TecDoc/Scrape.do permise fără «Armez API» (cron, pagină produs, scraper). */
function besoiu_api_tecdoc_scrape_implicit_allowed(string $provider): bool
{
    if (!in_array($provider, ['rapidapi_tecdoc', 'scrape_do'], true)) {
        return false;
    }

    if (!besoiu_api_auto_tecdoc_scrape_enabled()) {
        return false;
    }

    if (besoiu_api_is_cron_sync_context()) {
        return true;
    }

    if (PHP_SAPI === 'cli' && besoiu_api_automation_is_cli_batch()) {
        return true;
    }

    if (besoiu_api_is_public_product_tecdoc_context() && $provider === 'rapidapi_tecdoc') {
        return true;
    }

    return besoiu_api_is_manual_on_demand_request();
}

/** Scan Cron Sync (admin_hub scan_run) — fundal după flushJsonAndContinue. */
function besoiu_api_is_cron_sync_context(): bool
{
    return defined('BESOIU_CRON_SYNC') && BESOIU_CRON_SYNC;
}

/**
 * Mod strict: API plătit doar când adminul apasă «Armez API» (sesiune limitată).
 * Implicit activ când fundalul e oprit.
 */
function besoiu_api_strict_manual(): bool
{
    if (besoiu_api_automation_env_truthy('API_STRICT_MANUAL')) {
        return true;
    }

    if (besoiu_api_automation_env_truthy('API_STRICT_MANUAL_OFF')) {
        return false;
    }

    return besoiu_api_automation_paused();
}

function besoiu_api_live_override_active(): bool
{
    return besoiu_api_automation_env_truthy('BESOIU_API_LIVE_OVERRIDE');
}

function besoiu_api_automation_is_cli_batch(): bool
{
    if (PHP_SAPI !== 'cli') {
        return false;
    }

    $argv = $_SERVER['argv'] ?? [];
    $joined = implode(' ', array_map('strval', $argv));

    return str_contains($joined, 'cron_cli')
        || str_contains($joined, 'image_pipeline_retry')
        || str_contains($joined, 'ai_agent_cycle')
        || str_contains($joined, 'import_consumable')
        || str_contains($joined, 'import_cron')
        || str_contains($joined, 'queue_worker');
}

/** Sesiune admin autentificată (apel explicit din panou). */
function besoiu_api_admin_session_active(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    require_once __DIR__ . '/session-bridge.php';

    return besoiu_admin_session_authenticated();
}

/** @return array{armed:bool,permanent:bool,until:?string,until_ts:int,minutes_left:int,armed_by:int} */
function besoiu_api_arm_state(): array
{
    if (!empty($_SESSION['bpa_api_live_armed_permanent'])) {
        return [
            'armed' => true,
            'permanent' => true,
            'until' => null,
            'until_ts' => 0,
            'minutes_left' => -1,
            'armed_by' => (int) ($_SESSION['bpa_api_live_armed_by'] ?? 0),
        ];
    }

    $until = (int) ($_SESSION['bpa_api_live_armed_until'] ?? 0);
    if ($until <= time()) {
        besoiu_api_disarm_session();

        return [
            'armed' => false,
            'permanent' => false,
            'until' => null,
            'until_ts' => 0,
            'minutes_left' => 0,
            'armed_by' => 0,
        ];
    }

    return [
        'armed' => true,
        'permanent' => false,
        'until' => date('c', $until),
        'until_ts' => $until,
        'minutes_left' => (int) max(0, ceil(($until - time()) / 60)),
        'armed_by' => (int) ($_SESSION['bpa_api_live_armed_by'] ?? 0),
    ];
}

function besoiu_api_session_armed(): bool
{
    return besoiu_api_arm_state()['armed'];
}

/**
 * @return array{armed:bool,permanent:bool,until:?string,minutes:int}
 * minutes=0 → armare permanentă (până la «Oprit»).
 */
function besoiu_api_arm_session(int $minutes = 30): array
{
    if (!besoiu_api_admin_session_active()) {
        throw new \RuntimeException('Doar admin autentificat poate arma API live.');
    }

    $_SESSION['bpa_api_live_armed_by'] = (int) ($_SESSION['user_id'] ?? 0);

    if ($minutes <= 0) {
        $_SESSION['bpa_api_live_armed_permanent'] = 1;
        unset($_SESSION['bpa_api_live_armed_until']);

        return [
            'armed' => true,
            'permanent' => true,
            'until' => null,
            'minutes' => 0,
        ];
    }

    unset($_SESSION['bpa_api_live_armed_permanent']);
    $minutes = max(5, min(1440, $minutes));
    $_SESSION['bpa_api_live_armed_until'] = time() + ($minutes * 60);

    return [
        'armed' => true,
        'permanent' => false,
        'until' => date('c', (int) $_SESSION['bpa_api_live_armed_until']),
        'minutes' => $minutes,
    ];
}

function besoiu_api_disarm_session(): void
{
    unset(
        $_SESSION['bpa_api_live_armed_until'],
        $_SESSION['bpa_api_live_armed_by'],
        $_SESSION['bpa_api_live_armed_permanent']
    );
}

/**
 * Când fundalul e oprit — curăță doar armări expirate; păstrează 30m/4h/mereu din sesiune.
 */
function besoiu_api_automation_force_disarmed(): void
{
    if (!besoiu_api_automation_paused()) {
        return;
    }
    besoiu_api_arm_state();
}

/**
 * Oprește toți providerii plătiți în api_token_budgets (Cursor, RapidAPI, scrape.do…).
 *
 * @return list<string> provider_key dezactivați
 */
function besoiu_api_deactivate_paid_providers(?PDO $pdo = null): array
{
    // Groq/Gemini rămân pentru orchestra free; oprește doar plătit / consum credit.
    $keys = ['openai', 'scrape_do', 'rapidapi_tecdoc', 'serpapi'];
    $deactivated = [];

    try {
        require_once BESOIU_CONFIG . '/Database.php';
        if ($pdo === null) {
            besoiu_api_automation_env_loaded();
            $config = require BESOIU_CONFIG . '/config.php';
            \Config\Database::getInstance(
                (string) $config['db_host'],
                (string) $config['db_name'],
                (string) $config['db_user'],
                (string) $config['db_pass']
            );
            $pdo = \Config\Database::getDB();
        }
        $stmt = $pdo->prepare('UPDATE api_token_budgets SET is_active = 0 WHERE provider_key = ?');
        foreach ($keys as $key) {
            $stmt->execute([$key]);
            if ($stmt->rowCount() > 0) {
                $deactivated[] = $key;
            }
        }
    } catch (Throwable) {
        // BD opțională la primul deploy
    }

    return $deactivated;
}

/**
 * @return array{ok: bool, message: string, details: array<string, mixed>}
 */
function besoiu_api_stop_all_consumption(): array
{
    besoiu_api_automation_env_loaded();
    besoiu_api_disarm_session();

    $envPath = BESOIU_BACKEND . '/system/env_settings.php';
    $envUpdated = [];
    if (is_file($envPath)) {
        require_once $envPath;
        $res = besoiu_env_write_values([
            'API_AUTOMATION_DISABLED' => '1',
            'API_STRICT_MANUAL' => '1',
            'AI_AGENT_CYCLE_ENABLED' => '0',
            'OLLAMA_LOCAL_CYCLE' => '0',
            'CRON_LIGHT_ENRICH' => '0',
            'CRON_CHECK_EPIESA' => '0',
            'IMAGE_AUDIT_AUTO_RETRY' => '0',
        ]);
        if ($res['ok']) {
            $envUpdated = $res['updated'];
        }
    }

    if (function_exists('tecdoc_mark_api_unavailable')) {
        require_once BESOIU_LEGACY . '/tecdoc_stock.php';
        tecdoc_mark_api_unavailable('Consum API oprit manual — ' . date('c'));
    }

    $deactivated = besoiu_api_deactivate_paid_providers();

    $logDir = BESOIU_BACKEND . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $line = '[api_stop_all] ' . date('c') . ' env=' . json_encode($envUpdated) . ' providers=' . json_encode($deactivated) . PHP_EOL;
    @file_put_contents($logDir . '/api_automation_guard.log', $line, FILE_APPEND);

    return [
        'ok' => true,
        'message' => 'Consum API oprit: fundal dezactivat, sesiune dezarmată, provideri plătiți off.',
        'details' => [
            'env_updated' => $envUpdated,
            'providers_deactivated' => $deactivated,
            'automation_paused' => besoiu_api_automation_paused(),
            'strict_manual' => besoiu_api_strict_manual(),
        ],
    ];
}

/**
 * Endpoint-uri unde utilizatorul declanșează explicit API (mod legacy, fără strict).
 */
function besoiu_api_is_manual_on_demand_request(): bool
{
    if (defined('BESOIU_API_MANUAL_CALL') && BESOIU_API_MANUAL_CALL) {
        return true;
    }

    if (besoiu_api_live_override_active()) {
        return true;
    }

    if (PHP_SAPI === 'cli') {
        return false;
    }

    if (!besoiu_api_admin_session_active()) {
        return false;
    }

    $hay = strtolower(implode(' ', array_filter([
        (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''),
        (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
        (string) ($_SERVER['REQUEST_URI'] ?? ''),
        (string) ($_SERVER['PATH_INFO'] ?? ''),
    ])));

    /** @var list<string> */
    $allow = [
        'import_action_endpoint',
        'import_endpoint',
        'import_pro_motor_endpoint',
        'import-pro/api',
        'import-pro/_proxy/scraper-api',
        'scraper-api.php',
        'scraper_web_endpoint',
        'showcase-scan',
        'showcase-config',
        'importproduse',
        'scraper_endpoint',
        'tecdoc_endpoint',
        'ai_agent_endpoint',
        'ai_tokens_endpoint',
        'comunicare_endpoint',
        'audit_product_images',
        'productimageaudit',
        'image_audit',
        'crudu.php',
        'settings_endpoint',
        'section_assist',
        '/robot/tecdoc_proxy.php',
        'supplier_search_endpoint',
        'supplier_cart_endpoint',
        'furnizori_endpoint',
        'categorii_endpoint',
    ];

    /** Cron sync / hub scan — nu sunt „la cerere” cu API plătit. */
    $deny = [
        'admin_hub_endpoint',
        'dashboard_snapshot_cron',
        'cron_cli/',
        'import_pipeline_test_endpoint',
    ];

    foreach ($deny as $fragment) {
        if (str_contains($hay, $fragment)) {
            return false;
        }
    }

    foreach ($allow as $fragment) {
        if (str_contains($hay, $fragment)) {
            return true;
        }
    }

    return false;
}

function besoiu_api_is_authenticated_admin_context(): bool
{
    if (besoiu_api_automation_env_truthy('BESOIU_API_GUARD_TEST') && !empty($_SESSION['user_id'])) {
        return true;
    }

    if (PHP_SAPI === 'cli') {
        return false;
    }

    return besoiu_api_admin_session_active();
}

/** @return 'local'|'free_cloud'|'paid'|null */
function besoiu_api_llm_provider_tier(string $provider): ?string
{
    $provider = strtolower(trim($provider));

    return match (true) {
        $provider === 'ollama' => 'local',
        in_array($provider, ['groq', 'gemini', 'grok', 'openrouter'], true) => 'free_cloud',
        in_array($provider, ['openai', 'llm'], true) => 'paid',
        default => null,
    };
}

/** Orchestra free (Groq/Gemini/OpenRouter) — permisă când fundalul e activ, fără «Armez API». */
function besoiu_api_free_cloud_llm_allowed(string $provider = 'groq'): bool
{
    if (besoiu_api_live_override_active()) {
        return true;
    }

    if (defined('BESOIU_SETTINGS_MODULE_TEST') && BESOIU_SETTINGS_MODULE_TEST) {
        return true;
    }

    if (!besoiu_api_automation_paused()) {
        return true;
    }

    if (PHP_SAPI === 'cli' && besoiu_ai_agent_cycle_enabled()) {
        $ollamaHelper = BESOIU_BACKEND . '/system/ollama_llm.php';
        if (is_file($ollamaHelper)) {
            require_once $ollamaHelper;
            if (besoiu_ollama_local_cycle_enabled()) {
                return true;
            }
        }
    }

    if (besoiu_api_session_armed()) {
        return true;
    }

    if (defined('BESOIU_API_MANUAL_CALL') && BESOIU_API_MANUAL_CALL) {
        return true;
    }

    return besoiu_api_is_manual_on_demand_request();
}

/** OpenAI plătit — doar cu «Armez API» sau acțiune explicită admin (mod strict). */
function besoiu_api_paid_llm_allowed(): bool
{
    $budgetFile = BESOIU_BACKEND . '/system/api_token_budget.php';
    if (!function_exists('api_token_budget_provider_live_enabled') && is_file($budgetFile)) {
        require_once $budgetFile;
    }

    if (besoiu_api_live_override_active()) {
        return true;
    }

    if (defined('BESOIU_SETTINGS_MODULE_TEST') && BESOIU_SETTINGS_MODULE_TEST) {
        return true;
    }

    if (function_exists('api_token_budget_provider_live_enabled')) {
        $openaiOn = api_token_budget_provider_live_enabled('openai');
        if (!$openaiOn) {
            return false;
        }
    }

    if (besoiu_api_strict_manual()) {
        if (besoiu_api_session_armed()) {
            return true;
        }

        if (besoiu_api_is_authenticated_admin_context() && besoiu_api_is_manual_on_demand_request()) {
            return true;
        }

        return false;
    }

    if (besoiu_api_automation_paused()) {
        return besoiu_api_session_armed()
            || besoiu_api_is_manual_on_demand_request()
            || (defined('BESOIU_API_MANUAL_CALL') && BESOIU_API_MANUAL_CALL);
    }

    return besoiu_api_session_armed()
        || besoiu_api_is_manual_on_demand_request()
        || (defined('BESOIU_API_MANUAL_CALL') && BESOIU_API_MANUAL_CALL);
}

/**
 * @param 'rapidapi_tecdoc'|'scrape_do'|'llm'|string $provider
 */
function besoiu_api_live_call_allowed(string $provider = 'rapidapi_tecdoc'): bool
{
    $provider = strtolower(trim($provider));
    if ($provider === '') {
        $provider = 'rapidapi_tecdoc';
    }

    $llmTier = besoiu_api_llm_provider_tier($provider);
    if ($llmTier === 'local') {
        return true;
    }
    if ($llmTier === 'free_cloud') {
        return besoiu_api_free_cloud_llm_allowed($provider);
    }
    if ($llmTier === 'paid') {
        return besoiu_api_paid_llm_allowed();
    }

    $budgetFile = BESOIU_BACKEND . '/system/api_token_budget.php';
    if (!function_exists('api_token_budget_provider_live_enabled') && is_file($budgetFile)) {
        require_once $budgetFile;
    }

    if (besoiu_api_live_override_active()) {
        return true;
    }

    // Test explicit din Setări → Chei secrete (verificare chei de operator).
    if (defined('BESOIU_SETTINGS_MODULE_TEST') && BESOIU_SETTINGS_MODULE_TEST) {
        return true;
    }

    if (function_exists('api_token_budget_provider_live_enabled')) {
        if (!api_token_budget_provider_live_enabled($provider)) {
            return false;
        }
    }

    if (besoiu_api_tecdoc_scrape_implicit_allowed($provider)) {
        return true;
    }

    $strict = besoiu_api_strict_manual();
    $paused = besoiu_api_automation_paused();

    if (!$paused && !$strict) {
        return true;
    }

    if ($strict) {
        if (!besoiu_api_is_authenticated_admin_context()) {
            return false;
        }

        if (besoiu_api_session_armed()) {
            return true;
        }

        // Acțiuni explicite admin (scraper test, import, tecdoc_endpoint) — fără «Armez API».
        return besoiu_api_is_manual_on_demand_request();
    }

    if (defined('BESOIU_API_MANUAL_CALL') && BESOIU_API_MANUAL_CALL) {
        return true;
    }

    return besoiu_api_is_manual_on_demand_request();
}

function besoiu_api_automation_blocks_tecdoc_live(): bool
{
    return !besoiu_api_live_call_allowed('rapidapi_tecdoc');
}

function besoiu_api_automation_block_message(string $provider = 'rapidapi_tecdoc'): string
{
    $labels = [
        'rapidapi_tecdoc' => 'RapidAPI TecDoc',
        'scrape_do' => 'scrape.do',
        'llm' => 'OpenAI (plătit)',
        'openai' => 'OpenAI (plătit)',
        'groq' => 'Groq (free)',
        'gemini' => 'Gemini (free)',
        'openrouter' => 'OpenRouter (free)',
        'ollama' => 'Ollama (local)',
    ];
    $label = $labels[$provider] ?? $provider;

    $tier = besoiu_api_llm_provider_tier($provider);
    if ($tier === 'paid' && function_exists('api_token_budget_provider_live_enabled')) {
        if (!api_token_budget_provider_live_enabled('openai')) {
            return $label . ' — dezactivat în Setări → Tokeni API. Activează comutatorul OpenAI doar când vrei apel plătit.';
        }
    } elseif ($tier === null && function_exists('api_token_budget_provider_live_enabled')) {
        if (!api_token_budget_provider_live_enabled($provider)) {
            return $label . ' — dezactivat în Setări → Tokeni API. Aprinde comutatorul providerului.';
        }
    }

    if (besoiu_api_strict_manual() && !besoiu_api_session_armed()) {
        if ($tier === 'paid' && besoiu_api_is_manual_on_demand_request()) {
            return '';
        }
        if ($tier === 'paid' || $tier === null) {
            if (besoiu_api_is_manual_on_demand_request()) {
                return '';
            }

            return $label . ' — mod strict: apasă «Armez API live» pentru Cursor/plătit. Orchestra free (Ollama/Groq) rulează fără armare când fundalul e activ.';
        }
    }

    if (besoiu_api_automation_paused()) {
        return $label . ' — mod la cerere: fundal oprit (API_AUTOMATION_DISABLED=1). '
            . 'Doar cache local pe site; în admin consumă la acțiuni explicite.';
    }

    return $label . ' — apelurile live sunt oprite. Se folosește doar cache local.';
}

/** Citește o cheie scalară din admin/.env (evită $_ENV poluat pe Windows). */
function besoiu_env_file_scalar(string $key, string $default = ''): string
{
    $envPath = BESOIU_CONFIG . '/.env';
    if (!is_file($envPath)) {
        return $default;
    }

    $prefix = $key . '=';
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_starts_with($line, $prefix)) {
            continue;
        }

        return trim(substr($line, strlen($prefix)), " \t\n\r\0\x0B\"'");
    }

    return $default;
}

/** Ciclu autonom AI (Task Scheduler) — 0 = oprit complet. */
function besoiu_ai_agent_cycle_enabled(): bool
{
    $raw = strtolower(besoiu_env_file_scalar('AI_AGENT_CYCLE_ENABLED', '1'));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/** Oprește scripturile cron care consumă API plătit sau rulează agenți fără acord explicit. */
function besoiu_api_automation_guard_cron(string $scriptLabel = 'cron'): void
{
    if ($scriptLabel === 'ai_agent_cycle' && !besoiu_ai_agent_cycle_enabled()) {
        $msg = '[api_automation_guard] ai_agent_cycle oprit — AI_AGENT_CYCLE_ENABLED=0 (' . date('c') . ')' . PHP_EOL;
        echo $msg;
        exit(0);
    }

    if (!besoiu_api_automation_paused()) {
        return;
    }

    $tecScrapeCrons = ['image_pipeline_retry'];
    if (besoiu_api_auto_tecdoc_scrape_enabled() && in_array($scriptLabel, $tecScrapeCrons, true)) {
        echo '[api_automation_guard] ' . $scriptLabel . ' permis — API_AUTO_TECDOC_SCRAPE (TecDoc/Scrape.do, fără Cursor).' . PHP_EOL;

        return;
    }

    if ($scriptLabel === 'ai_agent_cycle' && !besoiu_api_strict_manual()) {
        $ollamaHelper = BESOIU_BACKEND . '/system/ollama_llm.php';
        if (is_file($ollamaHelper)) {
            require_once $ollamaHelper;
            if (besoiu_ollama_local_cycle_enabled()) {
                echo '[api_automation_guard] ai_agent_cycle permis — ciclu local Ollama (fără API plătit).' . PHP_EOL;

                return;
            }
        }
    }

    $msg = '[api_automation_guard] ' . $scriptLabel . ' oprit — API doar la cererea ta'
        . (besoiu_api_strict_manual() ? ' (mod strict)' : ' (API_AUTOMATION_DISABLED=1)')
        . ' (' . date('c') . ')' . PHP_EOL;
    echo $msg;

    $logDir = BESOIU_BACKEND . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    @file_put_contents($logDir . '/api_automation_guard.log', $msg, FILE_APPEND);

    exit(0);
}
