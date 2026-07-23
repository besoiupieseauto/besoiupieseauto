<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Scorecard compact pentru panoul Section Assistant — organism, LLM, alerte ops, jurnal query.
 */
final class SectionAssistantSupervisorService
{
    public function __construct(
        private ?string $projectRoot = null,
        private ?ChatOrganismOrchestrator $organism = null,
        private ?LlmRouterService $llm = null,
        private ?AdminOpsAlertsService $alerts = null,
        private ?SectionAssistantQueryLogService $queryLog = null,
    ) {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
        $this->organism = $organism ?? ChatOrganismOrchestrator::create($this->projectRoot);
        $this->llm = $llm ?? LlmRouterService::create($this->projectRoot);
        $this->alerts = $alerts ?? new AdminOpsAlertsService();
        $this->queryLog = $queryLog ?? new SectionAssistantQueryLogService();
    }

    /** @return array<string, mixed> */
    public function scorecard(string $section = 'produse', int $queryDays = 3): array
    {
        $section = trim($section) !== '' ? trim($section) : 'produse';

        $organism = $this->organism->status($section);
        $stats = is_array($organism['stats'] ?? null) ? $organism['stats'] : [];
        $liveCaps = (int) ($stats['live'] ?? 0);

        $llm = $this->llm->readiness();
        $ollama = is_array($llm['ollama'] ?? null) ? $llm['ollama'] : [];
        $llmReady = !empty($llm['ready']);
        $llmPrimary = (string) ($llm['primary'] ?? 'none');
        $llmModel = (string) ($ollama['model'] ?? $this->llm->model());

        $ops = $this->alerts->summary();
        $critical = (int) ($ops['critical_count'] ?? 0);
        $warnings = (int) ($ops['warning_count'] ?? 0);
        $bell = (int) ($ops['bell_count'] ?? 0);

        $qlog = $this->queryLog->summary(max(1, $queryDays));
        $qTotal = (int) ($qlog['total'] ?? 0);
        $qOk = (int) ($qlog['ok'] ?? 0);
        $qFail = (int) ($qlog['fail'] ?? 0);
        $qPartial = (int) ($qlog['partial'] ?? 0);
        $okRate = $qTotal > 0 ? (int) round(($qOk / $qTotal) * 100) : 100;

        $score = 0;
        if ($liveCaps >= 5) {
            $score += 25;
        } elseif ($liveCaps >= 1) {
            $score += 12;
        }
        if ($llmReady) {
            $score += 25;
        } elseif (!empty($ollama['reachable'])) {
            $score += 10;
        }
        if ($critical === 0 && $warnings === 0) {
            $score += 25;
        } elseif ($critical === 0) {
            $score += 15;
        } elseif ($critical <= 2) {
            $score += 5;
        }
        if ($qTotal === 0) {
            $score += 20;
        } elseif ($okRate >= 80) {
            $score += 25;
        } elseif ($okRate >= 50) {
            $score += 15;
        } else {
            $score += 5;
        }

        $grade = match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 50 => 'C',
            default => 'D',
        };

        $tiles = [
            [
                'key' => 'organism',
                'label' => 'Organism',
                'value' => $liveCaps . ' live',
                'status' => $liveCaps >= 5 ? 'ok' : ($liveCaps >= 1 ? 'warn' : 'bad'),
                'hint' => (int) ($stats['modules'] ?? 0) . ' module · ' . (int) ($stats['navigate_only'] ?? 0) . ' navigare',
            ],
            [
                'key' => 'llm',
                'label' => strtoupper($llmPrimary !== 'none' ? $llmPrimary : 'LLM'),
                'value' => $llmReady ? ($llmModel !== '' ? $llmModel : 'ready') : 'offline',
                'status' => $llmReady ? 'ok' : (!empty($ollama['reachable']) ? 'warn' : 'bad'),
                'hint' => (string) ($llm['router_mode'] ?? ''),
            ],
            [
                'key' => 'ops',
                'label' => 'Alerte',
                'value' => $bell > 0 ? ($bell . ' active') : '0',
                'status' => $critical > 0 ? 'bad' : ($warnings > 0 ? 'warn' : 'ok'),
                'hint' => $critical . ' critice · ' . $warnings . ' avertismente',
            ],
            [
                'key' => 'queries',
                'label' => 'Chat ' . $queryDays . 'z',
                'value' => $qTotal > 0 ? ($qOk . '/' . $qTotal . ' ok') : '—',
                'status' => $qTotal === 0 ? 'ok' : ($okRate >= 80 ? 'ok' : ($okRate >= 50 ? 'warn' : 'bad')),
                'hint' => $qFail . ' esec · ' . $qPartial . ' partial',
            ],
        ];

        return [
            'section' => $section,
            'generated_at' => date('c'),
            'grade' => $grade,
            'score' => min(100, $score),
            'tiles' => $tiles,
            'organism' => [
                'stats' => $stats,
                'name' => (string) (($organism['organism']['name'] ?? '') ?: 'Besoiu Chat Organism'),
            ],
            'llm' => [
                'ready' => $llmReady,
                'primary' => $llmPrimary,
                'model' => $llmModel,
                'router_mode' => (string) ($llm['router_mode'] ?? ''),
                'ollama_reachable' => !empty($ollama['reachable']),
            ],
            'ops_alerts' => [
                'status' => (string) ($ops['status'] ?? 'unknown'),
                'bell_count' => $bell,
                'critical_count' => $critical,
                'warning_count' => $warnings,
                'ai_key_ok' => !empty($ops['ai_key_ok']),
            ],
            'query_log' => [
                'days' => $queryDays,
                'total' => $qTotal,
                'ok' => $qOk,
                'partial' => $qPartial,
                'fail' => $qFail,
                'ok_rate' => $okRate,
            ],
        ];
    }
}
