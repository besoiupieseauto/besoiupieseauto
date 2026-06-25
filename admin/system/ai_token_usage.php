<?php

declare(strict_types=1);

use Config\Database;

/**
 * Jurnal și statistici consum tokeni AI (Grok / Gemini / Groq).
 */

function ai_token_pdo(): PDO
{
    return Database::getDB();
}

/** @return list<string> */
function ai_token_valid_providers(): array
{
    return ['grok', 'gemini', 'groq', 'openai'];
}

function ai_token_normalize_provider(string $provider): string
{
    $key = strtolower(trim($provider));
    return in_array($key, ai_token_valid_providers(), true) ? $key : 'groq';
}

function ai_token_log(
    string $provider,
    string $model,
    int $promptTokens,
    int $completionTokens,
    ?string $source = null
): void {
    $provider = ai_token_normalize_provider($provider);
    $promptTokens = max(0, $promptTokens);
    $completionTokens = max(0, $completionTokens);
    $total = $promptTokens + $completionTokens;
    if ($total === 0) {
        return;
    }

    $pdo = ai_token_pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO ai_token_usage (provider, model, prompt_tokens, completion_tokens, total_tokens, source)
         VALUES (:provider, :model, :prompt, :completion, :total, :source)'
    );
    $stmt->execute([
        ':provider' => $provider,
        ':model' => mb_substr(trim($model), 0, 96),
        ':prompt' => $promptTokens,
        ':completion' => $completionTokens,
        ':total' => $total,
        ':source' => $source !== null && $source !== '' ? mb_substr($source, 0, 64) : null,
    ]);
}

/** @return array<string, mixed> */
function ai_token_stats(PDO $pdo): array
{
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $byProvider = [];
    foreach (ai_token_valid_providers() as $p) {
        $byProvider[$p] = [
            'today' => 0,
            'month' => 0,
            'total' => 0,
            'requests_today' => 0,
        ];
    }

    $stmt = $pdo->query(
        "SELECT provider,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_tokens ELSE 0 END) AS today_tokens,
                SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN total_tokens ELSE 0 END) AS month_tokens,
                SUM(total_tokens) AS all_tokens,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS requests_today
         FROM ai_token_usage
         GROUP BY provider"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) ($row['provider'] ?? '');
        if (!isset($byProvider[$key])) {
            continue;
        }
        $byProvider[$key] = [
            'today' => (int) ($row['today_tokens'] ?? 0),
            'month' => (int) ($row['month_tokens'] ?? 0),
            'total' => (int) ($row['all_tokens'] ?? 0),
            'requests_today' => (int) ($row['requests_today'] ?? 0),
        ];
    }

    $grand = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_tokens END), 0) AS today_all,
            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN total_tokens END), 0) AS month_all,
            COALESCE(SUM(total_tokens), 0) AS total_all,
            COUNT(*) AS rows_total
         FROM ai_token_usage"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'today' => (int) ($grand['today_all'] ?? 0),
        'month' => (int) ($grand['month_all'] ?? 0),
        'total' => (int) ($grand['total_all'] ?? 0),
        'rows' => (int) ($grand['rows_total'] ?? 0),
        'by_provider' => $byProvider,
        'date_today' => $today,
        'date_month_start' => $monthStart,
    ];
}

/** @return list<array<string, mixed>> */
function ai_token_thresholds(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT provider, daily_limit, warning_pct, is_active FROM ai_token_thresholds ORDER BY provider');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

/** @return list<array<string, mixed>> */
function ai_token_alerts(PDO $pdo): array
{
    $stats = ai_token_stats($pdo);
    $thresholds = ai_token_thresholds($pdo);
    $alerts = [];

    foreach ($thresholds as $th) {
        if (empty($th['is_active'])) {
            continue;
        }
        $provider = (string) ($th['provider'] ?? '');
        $limit = max(1, (int) ($th['daily_limit'] ?? 1));
        $warningPct = max(1, min(100, (int) ($th['warning_pct'] ?? 80)));
        $used = (int) ($stats['by_provider'][$provider]['today'] ?? 0);
        $pct = (int) round(($used / $limit) * 100);

        if ($used >= $limit) {
            $alerts[] = [
                'level' => 'danger',
                'provider' => $provider,
                'message' => sprintf('Limită zilnică depășită pentru %s: %s / %s tokeni (%d%%).', strtoupper($provider), number_format($used, 0, ',', '.'), number_format($limit, 0, ',', '.'), $pct),
                'used' => $used,
                'limit' => $limit,
                'pct' => $pct,
            ];
        } elseif ($pct >= $warningPct) {
            $alerts[] = [
                'level' => 'warning',
                'provider' => $provider,
                'message' => sprintf('Atenție %s: %s / %s tokeni azi (%d%% din limită).', strtoupper($provider), number_format($used, 0, ',', '.'), number_format($limit, 0, ',', '.'), $pct),
                'used' => $used,
                'limit' => $limit,
                'pct' => $pct,
            ];
        }
    }

    return $alerts;
}

/** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
function ai_token_query(PDO $pdo, array $filters): array
{
    $where = ['1=1'];
    $params = [];

    $provider = trim((string) ($filters['provider'] ?? ''));
    if ($provider !== '' && in_array($provider, ai_token_valid_providers(), true)) {
        $where[] = 'provider = :provider';
        $params[':provider'] = $provider;
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'DATE(created_at) >= :date_from';
        $params[':date_from'] = $dateFrom;
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'DATE(created_at) <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $sqlWhere = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ai_token_usage WHERE {$sqlWhere}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $limit = max(1, min(200, (int) ($filters['limit'] ?? 25)));
    $offset = max(0, (int) ($filters['offset'] ?? 0));

    $listStmt = $pdo->prepare(
        "SELECT id, provider, model, prompt_tokens, completion_tokens, total_tokens, source, created_at
         FROM ai_token_usage
         WHERE {$sqlWhere}
         ORDER BY created_at DESC, id DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $listStmt->execute($params);

    return [
        'items' => $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'total' => $total,
    ];
}

/** @param array<string, mixed> $payload */
function ai_token_save_threshold(PDO $pdo, array $payload): void
{
    $provider = ai_token_normalize_provider((string) ($payload['provider'] ?? ''));
    $dailyLimit = max(1000, (int) ($payload['daily_limit'] ?? 500000));
    $warningPct = max(50, min(99, (int) ($payload['warning_pct'] ?? 80)));

    $stmt = $pdo->prepare(
        'INSERT INTO ai_token_thresholds (provider, daily_limit, warning_pct, is_active)
         VALUES (:provider, :daily_limit, :warning_pct, 1)
         ON DUPLICATE KEY UPDATE daily_limit = VALUES(daily_limit), warning_pct = VALUES(warning_pct), is_active = 1'
    );
    $stmt->execute([
        ':provider' => $provider,
        ':daily_limit' => $dailyLimit,
        ':warning_pct' => $warningPct,
    ]);
}
