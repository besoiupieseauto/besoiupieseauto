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
    return ['rapidapi_tecdoc', 'scrape_do', 'openai', 'cursor', 'groq', 'gemini', 'grok'];
}

function api_token_budget_normalize_provider(string $key): string
{
    $k = strtolower(trim($key));

    return in_array($k, api_token_budget_valid_providers(), true) ? $k : 'rapidapi_tecdoc';
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

/** @return array{used_tokens:int,remaining_tokens:int,used_pct:int,remaining_pct:int,month_requests:int,tokens_per_request:int,monthly_quota:int} */
function api_token_budget_compute_usage(array $budget, array $providerStats): array
{
    $quota = max(1, (int) ($budget['monthly_quota'] ?? 1));
    $tpr = max(1, (int) ($budget['tokens_per_request'] ?? 1));
    $requests = max(0, (int) ($providerStats['month_units'] ?? 0));
    $usedTokensAuto = $requests * $tpr;
    $remainingAuto = max(0, $quota - $usedTokensAuto);

    $hasOverride = array_key_exists('remaining_override', $budget)
        && $budget['remaining_override'] !== null
        && $budget['remaining_override'] !== '';
    $remaining = $hasOverride ? max(0, (int) $budget['remaining_override']) : $remainingAuto;
    $usedTokens = $hasOverride ? max(0, $quota - $remaining) : $usedTokensAuto;
    $usedPct = (int) min(100, round(($usedTokens / $quota) * 100));
    $remainingPct = max(0, 100 - $usedPct);
    $queriesLeft = (int) floor($remaining / $tpr);

    return [
        'used_tokens' => $usedTokens,
        'remaining_tokens' => $remaining,
        'remaining_auto' => $remainingAuto,
        'used_pct' => $usedPct,
        'remaining_pct' => $remainingPct,
        'month_requests' => $requests,
        'tokens_per_request' => $tpr,
        'monthly_quota' => $quota,
        'is_manual_remaining' => $hasOverride,
        'queries_left' => $queriesLeft,
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
