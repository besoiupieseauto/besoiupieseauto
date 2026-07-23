<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Phase3;

use Config\Database;
use Besoiu\Services\AiSupervisor\Support\AiSupervisorBootstrap;
use Besoiu\Services\AiSupervisor\Support\ApiKeysStatus;
use Besoiu\Services\AiSupervisor\Config\AiSupervisorConfig;
use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;
use PDO;
use Throwable;

/**
 * Faza 3b — buget tokeni AI: consum, prag, estimare task-uri rămase.
 */
final class TokenBudgetAnalyzer
{
    public function __construct(
        private ?AiSupervisorConfig $config = null,
        private ?AiSupervisorStore $store = null,
    ) {
        $this->config = $config ?? new AiSupervisorConfig();
        $this->store = $store ?? new AiSupervisorStore();
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        AiSupervisorBootstrap::ensureDatabase();
        $cfg = $this->config->get();
        $dailyLimit = max(1000, (int) ($cfg['token_daily_limit'] ?? 500000));
        $warnPct = max(50, min(99, (int) ($cfg['token_warn_pct'] ?? 80)));

        $today = 0;
        $byProvider = [];
        $avgPerCall = 800;
        $callsToday = 0;

        try {
            $pdo = Database::getDB();
            $today = (int) $pdo->query(
                'SELECT COALESCE(SUM(total_tokens), 0) FROM ai_token_usage WHERE usage_date = CURDATE()'
            )->fetchColumn();
            $callsToday = (int) $pdo->query(
                'SELECT COUNT(*) FROM ai_token_usage WHERE usage_date = CURDATE()'
            )->fetchColumn();
            if ($callsToday > 0) {
                $avgPerCall = (int) max(100, round($today / $callsToday));
            }
            $rows = $pdo->query(
                "SELECT provider, COALESCE(SUM(total_tokens), 0) AS tokens
                 FROM ai_token_usage WHERE usage_date = CURDATE()
                 GROUP BY provider"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $byProvider[(string) ($row['provider'] ?? 'unknown')] = (int) ($row['tokens'] ?? 0);
            }
        } catch (Throwable) {
            // tabel opțional
        }

        $today += $this->supplementCursorTokensFromApiLog($byProvider);
        if ($callsToday > 0 && $today > 0) {
            $avgPerCall = (int) max(100, round($today / max(1, $callsToday)));
        }

        $usedPct = $dailyLimit > 0 ? round(($today / $dailyLimit) * 100, 1) : 0;
        $remaining = max(0, $dailyLimit - $today);
        $estimatedTasksLeft = $avgPerCall > 0 ? (int) floor($remaining / $avgPerCall) : 0;

        $status = 'ok';
        if ($usedPct >= 100) {
            $status = 'critical';
        } elseif ($usedPct >= $warnPct) {
            $status = 'warning';
        }

        $report = [
            'ok' => $status !== 'critical',
            'http' => $status === 'critical' ? 429 : 200,
            'generated_at' => date('c'),
            'status' => $status,
            'tokens_today' => $today,
            'daily_limit' => $dailyLimit,
            'used_pct' => $usedPct,
            'warn_pct' => $warnPct,
            'remaining_tokens' => $remaining,
            'calls_today' => $callsToday,
            'avg_tokens_per_call' => $avgPerCall,
            'estimated_llm_tasks_left' => $estimatedTasksLeft,
            'by_provider' => $byProvider,
            'sufficient_for_supervisor' => $estimatedTasksLeft >= 3,
            'recommendations' => $this->recommendations($status, $usedPct, $estimatedTasksLeft),
            'keys_status' => ApiKeysStatus::snapshot(),
        ];

        if (is_file(dirname(__DIR__, 5) . '/admin/system/api_token_budget.php')) {
            require_once dirname(__DIR__, 5) . '/admin/system/api_token_budget.php';
            if (function_exists('api_token_budget_hub_snapshot')) {
                $report['api_hub'] = api_token_budget_hub_snapshot(Database::getDB());
            }
        }

        $this->store->write('token_budget', $report);
        $this->recordEvent($report);

        return $report;
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        $report = $this->store->read('token_budget');
        if (!is_array($report)) {
            $report = [];
        }
        $budgetFile = dirname(__DIR__, 5) . '/admin/system/api_token_budget.php';
        if (!is_file($budgetFile)) {
            return $report;
        }
        require_once $budgetFile;
        if (!function_exists('api_token_budget_enrich_supervisor_report')) {
            return $report;
        }

        return api_token_budget_enrich_supervisor_report($report);
    }

    /** @param array<string, int> $byProvider */
    private function supplementCursorTokensFromApiLog(array &$byProvider): int
    {
        $helper = dirname(__DIR__, 5) . '/admin/system/api_token_budget.php';
        if (!is_file($helper)) {
            return 0;
        }
        require_once $helper;
        if (!function_exists('api_token_budget_stats') || !function_exists('api_token_budget_tokens_per_request')) {
            return 0;
        }

        try {
            $stats = api_token_budget_stats(Database::getDB());
            $cursorToday = (int) (($stats['by_provider']['cursor']['today_units'] ?? 0));
            if ($cursorToday <= 0) {
                return 0;
            }
            $estimated = $cursorToday * api_token_budget_tokens_per_request('cursor');
            $logged = (int) ($byProvider['cursor'] ?? 0);
            if ($estimated <= $logged) {
                return 0;
            }
            $delta = $estimated - $logged;
            $byProvider['cursor'] = $estimated;

            return $delta;
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return list<string> */
    private function recommendations(string $status, float $usedPct, int $tasksLeft): array
    {
        if ($status === 'critical') {
            return [
                'Limită zilnică tokeni atinsă — diagnostic LLM dezactivat până mâine.',
                'Verifică /admin/ai-tokens și crește limita sau reduce apelurile automate.',
            ];
        }
        if ($status === 'warning') {
            return [
                sprintf('Consum %.1f%% din limită — rămân ~%d apeluri LLM estimate.', $usedPct, $tasksLeft),
                'Prioritizează doar alerte critice în supervizor.',
            ];
        }
        if ($tasksLeft < 5) {
            return ['Buget redus pentru diagnostic — supervizor rulează fără LLM dacă e nevoie.'];
        }

        return [sprintf('Buget OK — ~%d task-uri LLM estimate rămase azi.', $tasksLeft)];
    }

    /** @param array<string, mixed> $report */
    private function recordEvent(array $report): void
    {
        $helper = dirname(__DIR__, 5) . '/system/ai_action_events.php';
        if (!is_file($helper)) {
            return;
        }
        require_once $helper;
        if (function_exists('ai_action_event_record')) {
            ai_action_event_record('cron', 'supervisor_token_budget', 'pct=' . ($report['used_pct'] ?? 0), [
                'phase' => 3,
                'tokens_today' => $report['tokens_today'] ?? 0,
                'status' => $report['status'] ?? 'ok',
            ]);
        }
    }
}
