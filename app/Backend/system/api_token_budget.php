<?php

declare(strict_types=1);

use Config\Database;

/**
 * Buget tokeni / request-uri API — tracking consum + alerte.
 */

function api_token_budget_pdo(): PDO
{
    return Database::getDB();
}

/** @return list<string> */
function api_token_budget_valid_providers(): array
{
    return ['rapidapi_tecdoc', 'stealth_browser', 'scrape_do', 'openai', 'groq', 'gemini', 'grok', 'ollama'];
}

function api_token_budget_normalize_provider(string $key): string
{
    $k = strtolower(trim($key));

    return in_array($k, api_token_budget_valid_providers(), true) ? $k : 'rapidapi_tecdoc';
}

/** @return list<string> Provideri afișați în Setări + AI Agents (doar ce folosești). */
function api_token_budget_primary_providers(): array
{
    return ['stealth_browser', 'rapidapi_tecdoc', 'scrape_do'];
}

function api_token_budget_is_primary_provider(string $providerKey): bool
{
    return in_array(
        api_token_budget_normalize_provider($providerKey),
        api_token_budget_primary_providers(),
        true
    );
}

/** Creează / reactivează rândurile pentru cei 3 provideri folosiți în proiect. */
function api_token_budget_ensure_primary_providers(PDO $pdo): void
{
    $existing = api_token_budget_list($pdo);
    $byKey = [];
    foreach ($existing as $row) {
        $byKey[(string) ($row['provider_key'] ?? '')] = $row;
    }

    foreach (api_token_budget_primary_providers() as $key) {
        $defaults = api_token_budget_provider_defaults($key);
        if ($defaults === null) {
            continue;
        }

        $row = $byKey[$key] ?? null;
        if (!is_array($row)) {
            api_token_budget_save($pdo, $defaults);

            continue;
        }

        // Nu reactiva providerii dezactivați manual de operator.
    }
}

/** Resetează cache-ul static pentru is_active provider. */
function api_token_budget_reset_live_cache(): void
{
    $GLOBALS['API_TOKEN_BUDGET_LIVE_CACHE'] = null;
}

/** @return array<string, bool> */
function api_token_budget_live_states(?PDO $pdo = null): array
{
    if (isset($GLOBALS['API_TOKEN_BUDGET_LIVE_CACHE']) && is_array($GLOBALS['API_TOKEN_BUDGET_LIVE_CACHE'])) {
        return $GLOBALS['API_TOKEN_BUDGET_LIVE_CACHE'];
    }

    $states = [];
    try {
        $pdo = $pdo ?? api_token_budget_pdo();
        foreach (api_token_budget_list($pdo) as $row) {
            $key = (string) ($row['provider_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $states[$key] = !empty($row['is_active']);
        }
    } catch (Throwable) {
        // ignore
    }

    foreach (api_token_budget_primary_providers() as $key) {
        if (!array_key_exists($key, $states)) {
            $states[$key] = true;
        }
    }

    $GLOBALS['API_TOKEN_BUDGET_LIVE_CACHE'] = $states;

    return $states;
}

function api_token_budget_provider_live_enabled(string $providerKey, ?PDO $pdo = null): bool
{
    $key = api_token_budget_normalize_provider($providerKey);
    $states = api_token_budget_live_states($pdo);

    if ($key === 'llm' || in_array($key, ['openai', 'groq', 'gemini', 'grok'], true)) {
        foreach (['cursor', 'openai', 'groq', 'gemini', 'grok'] as $llmKey) {
            if (!empty($states[$llmKey])) {
                return true;
            }
        }

        return false;
    }

    return !empty($states[$key]);
}

function api_token_budget_set_provider_live(PDO $pdo, string $providerKey, bool $enabled): bool
{
    $key = api_token_budget_normalize_provider($providerKey);
    api_token_budget_ensure_primary_providers($pdo);
    $rows = api_token_budget_list($pdo);
    $row = null;
    foreach ($rows as $candidate) {
        if ((string) ($candidate['provider_key'] ?? '') === $key) {
            $row = $candidate;
            break;
        }
    }
    if ($row === null) {
        $defaults = api_token_budget_provider_defaults($key);
        if ($defaults === null) {
            return false;
        }
        $row = $defaults;
    }

    $row['is_active'] = $enabled ? 1 : 0;

    $ok = api_token_budget_save($pdo, $row);
    api_token_budget_reset_live_cache();

    if ($key === 'cursor' && !$enabled) {
        foreach (['openai', 'groq', 'gemini', 'grok'] as $llmKey) {
            foreach ($rows as $candidate) {
                if ((string) ($candidate['provider_key'] ?? '') !== $llmKey) {
                    continue;
                }
                $candidate['is_active'] = 0;
                api_token_budget_save($pdo, $candidate);
            }
        }
        api_token_budget_reset_live_cache();
    }

    return $ok;
}

/** @return list<array<string, mixed>> */
function api_token_budget_list_for_ui(PDO $pdo): array
{
    api_token_budget_ensure_primary_providers($pdo);

    $primary = api_token_budget_primary_providers();
    $rows = api_token_budget_list($pdo);
    $byKey = [];
    foreach ($rows as $row) {
        $byKey[(string) ($row['provider_key'] ?? '')] = $row;
    }

    $out = [];
    foreach ($primary as $key) {
        if (!isset($byKey[$key])) {
            continue;
        }
        $row = $byKey[$key];
        $out[] = $row;
    }

    return $out;
}

/** @return array<string, string> env_key => provider_key */
function api_token_budget_env_provider_map(): array
{
    return [
        'SCRAPE_DO_TOKEN' => 'scrape_do',
        'RAPIDAPI_AUTOPARTS_KEY' => 'rapidapi_tecdoc',
        'RAPIDAPI_TECDOC_KEY' => 'rapidapi_tecdoc',
        'GROQ_KEY' => 'groq',
        'GEMINI_KEY' => 'gemini',
    ];
}

/** Valori de bază la token nou — Scrape.do: 1000 credite, 20/cerere; RapidAPI: 100 cereri, 1/cerere. */
function api_token_budget_provider_defaults(string $providerKey): ?array
{
    return match (api_token_budget_normalize_provider($providerKey)) {
        'stealth_browser' => [
            'provider_key' => 'stealth_browser',
            'label' => 'Stealth browser (nodriver)',
            'env_key' => 'STEALTH_BROWSER_ENABLED',
            'monthly_quota' => 999999999,
            'tokens_per_request' => 0,
            'cost_per_unit' => 0.0,
            'warning_pct' => 95,
            'is_active' => 1,
            'notes' => 'Chrome local tools/stealth-browser-mcp — motor scraper ePiesa/eMAG/Autodoc. 0 credite cloud.',
        ],
        'scrape_do' => [
            'provider_key' => 'scrape_do',
            'label' => 'Scrape.do (fallback opțional)',
            'env_key' => 'SCRAPE_DO_TOKEN',
            'monthly_quota' => 1000,
            'tokens_per_request' => 20,
            'cost_per_unit' => 0.12,
            'warning_pct' => 80,
            'is_active' => 1,
            'notes' => '1.000 credite/lună · 20 credite/cerere API (inclusiv eșec HTML)',
        ],
        'rapidapi_tecdoc' => [
            'provider_key' => 'rapidapi_tecdoc',
            'label' => 'RapidAPI TecDoc',
            'env_key' => 'RAPIDAPI_AUTOPARTS_KEY',
            'monthly_quota' => 100,
            'tokens_per_request' => 1,
            'cost_per_unit' => 0.05,
            'warning_pct' => 80,
            'is_active' => 1,
            'notes' => '100 cereri/lună · 1 credit/căutare TecDoc',
        ],
        'ollama' => [
            'provider_key' => 'ollama',
            'label' => 'Ollama (local)',
            'env_key' => 'OLLAMA_ENABLED',
            'monthly_quota' => 999999999,
            'tokens_per_request' => 1,
            'cost_per_unit' => 0.0,
            'warning_pct' => 95,
            'is_active' => 1,
            'notes' => 'LLM local — 0 tokeni cloud. Metro: Ollama first, Cursor la escaladare.',
        ],
        default => null,
    };
}

/**
 * Cursor: separă consumul local (server + CURSOR_API_KEY) de dashboard-ul Cursor (API vs Auto+Composer IDE).
 *
 * @return array<string, mixed>
 */
function api_token_budget_cursor_billing_info(?PDO $pdo = null): array
{
    $pdo = $pdo ?? api_token_budget_pdo();
    $stats = api_token_budget_stats($pdo);
    $cursorStats = is_array($stats['by_provider']['cursor'] ?? null) ? $stats['by_provider']['cursor'] : [];
    $monthRequests = (int) ($cursorStats['month_units'] ?? 0);
    $todayRequests = (int) ($cursorStats['today_units'] ?? 0);
    $tpr = api_token_budget_tokens_per_request('cursor');

    return [
        'scope' => 'server_api_key',
        'local_month_requests' => $monthRequests,
        'local_today_requests' => $todayRequests,
        'local_tokens_per_request' => $tpr,
        'local_estimated_tokens' => $monthRequests * $tpr,
        'dashboard_usage_url' => 'https://cursor.com/dashboard?tab=usage',
        'dashboard_billing_url' => 'https://cursor.com/dashboard?tab=billing',
        'note_ro' => 'Contorul Besoiu = apeluri de pe server prin CURSOR_API_KEY (agenți, Composer widget, audit). '
            . 'Nu include chatul din Cursor IDE (Auto + Composer 100% = alt buget, separat de API).',
        'labels' => [
            'local' => 'Consum local server (Besoiu)',
            'external' => 'Cotă reală → Cursor Dashboard → API',
            'ide_bucket' => 'Auto + Composer (IDE) — nu apare aici',
        ],
    ];
}

/** Șterge consumul logat pentru provider (reset la token nou). */
function api_token_budget_clear_usage(PDO $pdo, string $providerKey): int
{
    $providerKey = api_token_budget_normalize_provider($providerKey);
    try {
        $stmt = $pdo->prepare('DELETE FROM api_token_usage_log WHERE provider_key = ?');

        return $stmt->execute([$providerKey]) ? $stmt->rowCount() : 0;
    } catch (Throwable) {
        return 0;
    }
}

/** Reset buget + consum la înlocuire token API (valorile de bază). */
function api_token_budget_reset_provider_on_new_token(string $providerKey): bool
{
    $defaults = api_token_budget_provider_defaults($providerKey);
    if ($defaults === null) {
        return false;
    }

    try {
        $pdo = api_token_budget_pdo();
        api_token_budget_clear_usage($pdo, $defaults['provider_key']);
        $defaults['remaining_override'] = null;
        api_token_budget_save($pdo, $defaults);

        if ($defaults['provider_key'] === 'scrape_do') {
            $bootstrap = dirname(__DIR__, 2) . '/lib/Scraper/bootstrap.php';
            if (is_file($bootstrap)) {
                require_once $bootstrap;
                ScrapeDoConfig::clearQuotaExceeded();
            }
        }

        if ($defaults['provider_key'] === 'rapidapi_tecdoc') {
            $tecdocPath = dirname(__DIR__, 2) . '/system/tecdoc_stock.php';
            if (is_file($tecdocPath)) {
                require_once $tecdocPath;
                if (function_exists('tecdoc_clear_quota_unavailable_flag')) {
                    tecdoc_clear_quota_unavailable_flag();
                }
            }
        }

        api_token_budget_sync_supervisor_store();

        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * La salvare chei .env — dacă token Scrape.do / RapidAPI s-a schimbat, resetează bugetul de bază.
 *
 * @param array<string, string> $changes
 * @param array<string, string> $beforeValues
 * @return list<string> mesaje pentru UI
 */
function api_token_budget_sync_env_token_changes(array $changes, array $beforeValues): array
{
    $map = api_token_budget_env_provider_map();
    $messages = [];
    $handled = [];

    foreach ($map as $envKey => $provider) {
        if (!array_key_exists($envKey, $changes) || isset($handled[$provider])) {
            continue;
        }

        $old = trim((string) ($beforeValues[$envKey] ?? ''));
        $new = trim((string) ($changes[$envKey] ?? ''));
        if ($new === '' || $new === $old) {
            continue;
        }

        $handled[$provider] = true;
        if (!api_token_budget_reset_provider_on_new_token($provider)) {
            continue;
        }

        $defaults = api_token_budget_provider_defaults($provider);
        if ($defaults === null) {
            continue;
        }

        $messages[] = sprintf(
            '%s: token nou — buget resetat (%s tokeni, %s/cerere, consum 0).',
            $defaults['label'],
            number_format((int) $defaults['monthly_quota'], 0, ',', '.'),
            number_format((int) $defaults['tokens_per_request'], 0, ',', '.')
        );
    }

    return $messages;
}

/** @return list<array<string, mixed>> */
function api_token_budget_list(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            'SELECT provider_key, label, env_key, monthly_quota, tokens_per_request, remaining_override, cost_per_unit, warning_pct, is_active, notes, updated_at
             FROM api_token_budgets ORDER BY provider_key'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

/** @param array<string, mixed> $row */
function api_token_budget_save(PDO $pdo, array $row): bool
{
    $key = api_token_budget_normalize_provider((string) ($row['provider_key'] ?? ''));
    $overrideRaw = $row['remaining_override'] ?? null;
    $remainingOverride = null;
    if ($overrideRaw !== null && $overrideRaw !== '') {
        $remainingOverride = max(0, (int) $overrideRaw);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO api_token_budgets (provider_key, label, env_key, monthly_quota, tokens_per_request, remaining_override, cost_per_unit, warning_pct, is_active, notes)
         VALUES (:k, :label, :env, :quota, :tpr, :ro, :cost, :warn, :active, :notes)
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            env_key = VALUES(env_key),
            monthly_quota = VALUES(monthly_quota),
            tokens_per_request = VALUES(tokens_per_request),
            remaining_override = VALUES(remaining_override),
            cost_per_unit = VALUES(cost_per_unit),
            warning_pct = VALUES(warning_pct),
            is_active = VALUES(is_active),
            notes = VALUES(notes)'
    );

    return $stmt->execute([
        ':k' => $key,
        ':label' => mb_substr(trim((string) ($row['label'] ?? $key)), 0, 120),
        ':env' => trim((string) ($row['env_key'] ?? '')) !== '' ? trim((string) $row['env_key']) : null,
        ':quota' => max(1, (int) ($row['monthly_quota'] ?? 1000)),
        ':tpr' => max(1, (int) ($row['tokens_per_request'] ?? 1)),
        ':ro' => $remainingOverride,
        ':cost' => max(0, (float) ($row['cost_per_unit'] ?? 0)),
        ':warn' => max(50, min(99, (int) ($row['warning_pct'] ?? 80))),
        ':active' => !empty($row['is_active']) ? 1 : 0,
        ':notes' => trim((string) ($row['notes'] ?? '')) !== '' ? mb_substr(trim((string) $row['notes']), 0, 255) : null,
    ]);
}

function api_token_budget_log(
    string $providerKey,
    int $units = 1,
    ?string $source = null,
    ?string $note = null
): void {
    $units = max(1, $units);
    $providerKey = api_token_budget_normalize_provider($providerKey);

    try {
        $pdo = api_token_budget_pdo();
        $budgets = api_token_budget_list($pdo);
        $costPer = 0.0;
        foreach ($budgets as $b) {
            if (($b['provider_key'] ?? '') === $providerKey) {
                $costPer = (float) ($b['cost_per_unit'] ?? 0);
                break;
            }
        }
        $costRon = round($costPer * $units, 4);

        $stmt = $pdo->prepare(
            'INSERT INTO api_token_usage_log (provider_key, units, cost_ron, source, note)
             VALUES (:k, :u, :c, :s, :n)'
        );
        $stmt->execute([
            ':k' => $providerKey,
            ':u' => $units,
            ':c' => $costRon,
            ':s' => $source !== null && $source !== '' ? mb_substr($source, 0, 64) : null,
            ':n' => $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null,
        ]);
    } catch (Throwable) {
        // nu bloca fluxul principal
    }
}

function api_token_budget_tokens_per_request(string $providerKey): int
{
    $providerKey = api_token_budget_normalize_provider($providerKey);
    $defaults = [
        'cursor' => 2500,
        'openai' => 1500,
        'groq' => 800,
        'gemini' => 1200,
        'grok' => 1200,
        'rapidapi_tecdoc' => 1,
        'scrape_do' => 20,
        'ollama' => 1,
    ];

    try {
        $pdo = api_token_budget_pdo();
        foreach (api_token_budget_list($pdo) as $budget) {
            if (($budget['provider_key'] ?? '') === $providerKey) {
                return max(1, (int) ($budget['tokens_per_request'] ?? 1));
            }
        }
    } catch (Throwable) {
        // ignore
    }

    return max(1, (int) ($defaults[$providerKey] ?? 1));
}

/**
 * Credite scrape.do per cerere API — fix din Setări (implicit 20), inclusiv răspuns eșuat.
 */
function api_token_budget_scrape_do_credits(bool $super = false, bool $render = false): int
{
    unset($super, $render);

    return max(1, api_token_budget_tokens_per_request('scrape_do'));
}

/**
 * Log request + sincronizare consum tokeni pentru supervizor AI (#38).
 */
function api_token_budget_log_llm_call(
    string $providerKey,
    string $model,
    ?string $source = null,
    ?int $totalTokensOverride = null
): void {
    $providerKey = api_token_budget_normalize_provider($providerKey);
    api_token_budget_log($providerKey, 1, $source);

    $tokens = max(1, $totalTokensOverride ?? api_token_budget_tokens_per_request($providerKey));
    if ($providerKey === 'ollama') {
        return;
    }
    $aiFile = __DIR__ . '/ai_token_usage.php';
    if (!is_file($aiFile)) {
        return;
    }
    require_once $aiFile;
    if (!function_exists('ai_token_log')) {
        return;
    }

    $aiProvider = $providerKey === 'cursor' ? 'cursor' : $providerKey;
    $prompt = (int) max(1, floor($tokens * 0.65));
    $completion = max(0, $tokens - $prompt);
    ai_token_log($aiProvider, $model, $prompt, $completion, $source);
}

/** @return array{used_tokens:int,remaining_tokens:int,used_pct:int,remaining_pct:int,month_requests:int,tokens_per_request:int,monthly_quota:int,max_requests:int,requests_left:int,is_manual_remaining:bool,queries_left:int,quota_inconsistent:bool} */
function api_token_budget_compute_usage(array $budget, array $providerStats): array
{
    $quota = max(1, (int) ($budget['monthly_quota'] ?? 1));
    $tpr = max(1, (int) ($budget['tokens_per_request'] ?? 1));
    $requests = max(0, (int) ($providerStats['month_units'] ?? 0));
    $usedTokensAuto = $requests * $tpr;
    $remainingAuto = max(0, $quota - $usedTokensAuto);
    $maxRequests = (int) floor($quota / $tpr);

    $hasOverride = array_key_exists('remaining_override', $budget)
        && $budget['remaining_override'] !== null
        && $budget['remaining_override'] !== '';
    $remaining = $hasOverride ? max(0, (int) $budget['remaining_override']) : $remainingAuto;
    $usedTokens = $hasOverride ? max(0, $quota - $remaining) : $usedTokensAuto;
    $usedPct = (int) min(100, round(($usedTokens / $quota) * 100));
    $remainingPct = max(0, 100 - $usedPct);

    // Cereri rămase = din consumul logat (sursă de adevăr pentru scrape/RapidAPI).
    $requestsLeft = max(0, $maxRequests - $requests);
    $queriesFromTokens = max(0, (int) floor($remaining / $tpr));
    // Dacă override manual contrazice jurnalul, folosim minimul (conservator).
    $queriesLeft = $hasOverride && $requests > 0
        ? min($queriesFromTokens, $requestsLeft)
        : $requestsLeft;
    $quotaInconsistent = $hasOverride && $requests > 0 && $queriesFromTokens > $requestsLeft + 1;

    return [
        'used_tokens' => $usedTokens,
        'remaining_tokens' => $remaining,
        'remaining_auto' => $remainingAuto,
        'used_pct' => $usedPct,
        'remaining_pct' => $remainingPct,
        'month_requests' => $requests,
        'tokens_per_request' => $tpr,
        'monthly_quota' => $quota,
        'max_requests' => $maxRequests,
        'requests_left' => $requestsLeft,
        'is_manual_remaining' => $hasOverride,
        'queries_left' => $queriesLeft,
        'quota_inconsistent' => $quotaInconsistent,
    ];
}

/** @return array<string, mixed> */
function api_token_budget_stats(PDO $pdo): array
{
    $monthStart = date('Y-m-01');
    $byProvider = [];
    foreach (api_token_budget_valid_providers() as $p) {
        $byProvider[$p] = ['month_units' => 0, 'month_cost' => 0.0, 'total_units' => 0, 'total_cost' => 0.0, 'today_units' => 0];
    }

    try {
        $stmt = $pdo->query(
            "SELECT provider_key,
                    SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN units ELSE 0 END) AS month_units,
                    SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN cost_ron ELSE 0 END) AS month_cost,
                    SUM(units) AS total_units,
                    SUM(cost_ron) AS total_cost,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN units ELSE 0 END) AS today_units
             FROM api_token_usage_log
             GROUP BY provider_key"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $k = (string) ($row['provider_key'] ?? '');
            if (!isset($byProvider[$k])) {
                continue;
            }
            $byProvider[$k] = [
                'month_units' => (int) ($row['month_units'] ?? 0),
                'month_cost' => (float) ($row['month_cost'] ?? 0),
                'total_units' => (int) ($row['total_units'] ?? 0),
                'total_cost' => (float) ($row['total_cost'] ?? 0),
                'today_units' => (int) ($row['today_units'] ?? 0),
            ];
        }
    } catch (Throwable) {
        // tabele lipsă
    }

    return [
        'by_provider' => $byProvider,
        'month_start' => $monthStart,
    ];
}

/** @return list<array<string, mixed>> */
function api_token_budget_alerts(PDO $pdo): array
{
    $budgets = api_token_budget_list($pdo);
    $stats = api_token_budget_stats($pdo);
    $alerts = [];

    foreach ($budgets as $b) {
        if (empty($b['is_active'])) {
            continue;
        }
        $key = (string) ($b['provider_key'] ?? '');
        if (!api_token_budget_is_primary_provider($key)) {
            continue;
        }
        $warnPct = max(1, min(100, (int) ($b['warning_pct'] ?? 80)));
        $cost = (float) ($stats['by_provider'][$key]['month_cost'] ?? 0);
        $label = (string) ($b['label'] ?? $key);
        $usage = api_token_budget_compute_usage($b, $stats['by_provider'][$key] ?? []);
        $usedTokens = $usage['used_tokens'];
        $remaining = $usage['remaining_tokens'];
        $quota = $usage['monthly_quota'];
        $tpr = $usage['tokens_per_request'];
        $usedPct = $usage['used_pct'];

        if ($usedTokens >= $quota) {
            $alerts[] = [
                'level' => 'danger',
                'provider_key' => $key,
                'message' => sprintf(
                    '%s: tokeni epuizați — %s consumați din %s (%d%%). Rămas: 0. Cost estimat luna: %.2f RON.',
                    $label,
                    number_format($usedTokens, 0, ',', '.'),
                    number_format($quota, 0, ',', '.'),
                    $usedPct,
                    $cost
                ),
            ];
        } elseif ($usedPct >= $warnPct) {
            $alerts[] = [
                'level' => 'warning',
                'provider_key' => $key,
                'message' => sprintf(
                    '%s: %d%% consumați — rămas %s tokeni din %s (%s tokeni/query). Cost luna: %.2f RON.',
                    $label,
                    $usedPct,
                    number_format($remaining, 0, ',', '.'),
                    number_format($quota, 0, ',', '.'),
                    number_format($tpr, 0, ',', '.'),
                    $cost
                ),
            ];
        }
    }

    return $alerts;
}

function api_token_budget_mask_secret(string $value): string
{
    $value = trim($value);
    $len = strlen($value);
    if ($len === 0) {
        return '';
    }
    if ($len <= 8) {
        return str_repeat('•', $len);
    }

    return substr($value, 0, 4) . str_repeat('•', min(12, $len - 8)) . substr($value, -4);
}

/** @return array<string, mixed>|null */
function api_token_budget_provider_usage(string $providerKey): ?array
{
    $providerKey = api_token_budget_normalize_provider($providerKey);

    try {
        $pdo = api_token_budget_pdo();
        $budgets = api_token_budget_list($pdo);
        $stats = api_token_budget_stats($pdo);
        foreach ($budgets as $b) {
            if (($b['provider_key'] ?? '') !== $providerKey) {
                continue;
            }
            if (empty($b['is_active'])) {
                return null;
            }

            return api_token_budget_compute_usage($b, $stats['by_provider'][$providerKey] ?? []);
        }
    } catch (Throwable) {
        return null;
    }

    return null;
}

/**
 * Snapshot unificat buget API — aceeași sursă pentru Setări, AI Agents și Composer.
 *
 * @return array{
 *   generated_at:string,
 *   month_start:string,
 *   budgets:list<array<string,mixed>>,
 *   stats:array<string,mixed>,
 *   alerts:list<array<string,mixed>>,
 *   providers:array<string,array<string,mixed>>,
 *   priority_providers:list<array<string,mixed>>,
 *   llm_context:string,
 *   settings_link:string
 * }
 */
function api_token_budget_hub_snapshot(?PDO $pdo = null): array
{
    $pdo = $pdo ?? api_token_budget_pdo();
    $budgets = api_token_budget_list($pdo);
    $stats = api_token_budget_stats($pdo);
    $alerts = array_values(array_filter(
        api_token_budget_alerts($pdo),
        static fn (array $a): bool => api_token_budget_is_primary_provider((string) ($a['provider_key'] ?? ''))
    ));
    $providers = [];
    $priorityKeys = api_token_budget_primary_providers();

    foreach ($budgets as $budget) {
        $key = (string) ($budget['provider_key'] ?? '');
        if ($key === '' || empty($budget['is_active']) || !api_token_budget_is_primary_provider($key)) {
            continue;
        }
        $providerStats = $stats['by_provider'][$key] ?? [];
        $usage = api_token_budget_compute_usage($budget, $providerStats);
        $providers[$key] = array_merge($budget, [
            'usage' => $usage,
            'month_cost' => (float) ($providerStats['month_cost'] ?? 0),
            'month_units' => (int) ($providerStats['month_units'] ?? 0),
        ]);
        if ($key === 'cursor') {
            $providers[$key]['billing_info'] = api_token_budget_cursor_billing_info($pdo);
        }
    }

    $contextLines = [];
    $cursorBilling = api_token_budget_cursor_billing_info($pdo);
    if (isset($providers['cursor'])) {
        $cu = is_array($providers['cursor']['usage'] ?? null) ? $providers['cursor']['usage'] : [];
        $contextLines[] = sprintf(
            'Cursor (server): %s apeluri luna, ~%s tokeni estimați local — cotă reală API pe cursor.com (separat de Auto+Composer IDE).',
            number_format((int) ($cu['month_requests'] ?? 0), 0, ',', '.'),
            number_format((int) ($cursorBilling['local_estimated_tokens'] ?? 0), 0, ',', '.')
        );
    }

    foreach (['scrape_do', 'rapidapi_tecdoc'] as $pk) {
        if (!isset($providers[$pk])) {
            continue;
        }
        $p = $providers[$pk];
        $u = is_array($p['usage'] ?? null) ? $p['usage'] : [];
        $contextLines[] = sprintf(
            '%s: %s/%s cereri luna, ~%s rămase, %s credite/cerere, %s credite consumate (sursă: Setări → Tokeni API).',
            (string) ($p['label'] ?? $pk),
            number_format((int) ($u['month_requests'] ?? 0), 0, ',', '.'),
            number_format((int) ($u['max_requests'] ?? 0), 0, ',', '.'),
            number_format((int) ($u['requests_left'] ?? 0), 0, ',', '.'),
            number_format((int) ($u['tokens_per_request'] ?? 1), 0, ',', '.'),
            number_format((int) ($u['used_tokens'] ?? 0), 0, ',', '.')
        );
    }

    $priorityProviders = [];
    foreach ($priorityKeys as $pk) {
        if (isset($providers[$pk])) {
            $priorityProviders[] = $providers[$pk];
        }
    }

    return [
        'generated_at' => date('c'),
        'month_start' => (string) ($stats['month_start'] ?? date('Y-m-01')),
        'primary_providers' => $priorityKeys,
        'budgets' => api_token_budget_list_for_ui($pdo),
        'stats' => $stats,
        'alerts' => $alerts,
        'providers' => $providers,
        'priority_providers' => $priorityProviders,
        'cursor_billing' => $cursorBilling,
        'llm_context' => implode(' ', $contextLines),
        'settings_link' => '/admin/settings?tab=tokens',
    ];
}

/** Sincronizează snapshot-ul API în cache-ul supervizor (robot/data/ai_supervisor). */
function api_token_budget_sync_supervisor_store(): void
{
    if (!function_exists('api_token_budget_hub_snapshot')) {
        return;
    }

    $storeDir = dirname(__DIR__, 2) . '/robot/data/ai_supervisor';
    if (!is_dir($storeDir)) {
        @mkdir($storeDir, 0775, true);
    }

    $path = $storeDir . '/token_budget.json';
    $existing = [];
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        $existing = is_array($decoded) ? $decoded : [];
    }

    $hub = api_token_budget_hub_snapshot();
    $existing['api_hub'] = $hub;
    $existing['api_hub_synced_at'] = date('c');
    $existing['stored_at'] = date('c');
    @file_put_contents(
        $path,
        json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

/** Îmbogățește raportul supervizor cu date live din Setări (tokeni API). */
function api_token_budget_enrich_supervisor_report(array $report): array
{
    $pdo = api_token_budget_pdo();
    $hub = api_token_budget_hub_snapshot($pdo);
    $report['api_hub'] = $hub;
    $report['api_hub_synced_at'] = $hub['generated_at'];
    $report['live_synced_at'] = date('c');

    if (class_exists(\Besoiu\Services\AiSupervisor\Support\ApiKeysStatus::class)) {
        $report['keys_status'] = \Besoiu\Services\AiSupervisor\Support\ApiKeysStatus::snapshot();
    } else {
        $keysHelper = __DIR__ . '/../src/Services/AiSupervisor/Support/ApiKeysStatus.php';
        if (is_file($keysHelper)) {
            require_once $keysHelper;
            $report['keys_status'] = \Besoiu\Services\AiSupervisor\Support\ApiKeysStatus::snapshot();
        }
    }

    try {
        $stats = api_token_budget_stats($pdo);
        $byProvider = is_array($stats['by_provider'] ?? null) ? $stats['by_provider'] : [];
        $report['live_usage'] = [
            'by_provider' => $byProvider,
            'month_units' => (int) ($stats['month_units'] ?? 0),
            'today_units' => (int) ($stats['today_units'] ?? 0),
            'synced_at' => date('c'),
        ];

        $todayTokens = (int) ($report['tokens_today'] ?? 0);
        if ($todayTokens <= 0) {
            $estimated = 0;
            foreach ($byProvider as $provider => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $units = (int) ($row['today_units'] ?? 0);
                if ($units <= 0) {
                    continue;
                }
                $estimated += $units * api_token_budget_tokens_per_request((string) $provider);
            }
            if ($estimated > 0) {
                $report['tokens_today'] = $estimated;
                $report['tokens_today_source'] = 'api_usage_log';
            }
        }
    } catch (Throwable) {
        // jurnal opțional
    }

    return $report;
}
