<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Phase4;

use Besoiu\Services\AdminOpsAlertsService;
use Besoiu\Services\AiSupervisor\Config\AiSupervisorConfig;
use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;
use Besoiu\Services\LlmRouterService;

/**
 * Faza 4a — diagnostic inteligent: cauză + pași rezolvare pentru alerte.
 */
final class DiagnosticEngine
{
    public function __construct(
        private ?AiSupervisorConfig $config = null,
        private ?AiSupervisorStore $store = null,
        private ?AdminOpsAlertsService $alerts = null,
        private ?LlmRouterService $llm = null,
    ) {
        $root = dirname(__DIR__, 5);
        $this->config = $config ?? new AiSupervisorConfig($root);
        $this->store = $store ?? new AiSupervisorStore($root);
        $this->alerts = $alerts ?? new AdminOpsAlertsService();
        $this->llm = $llm ?? LlmRouterService::create($root);
    }

    /** @param array<string, mixed> $contextReports */
    public function run(array $contextReports = []): array
    {
        $cfg = $this->config->get();
        $maxItems = max(1, min(20, (int) ($cfg['diagnostics_max_items'] ?? 8)));
        $useLlm = !empty($cfg['diagnostics_use_llm']);

        $feed = $this->alerts->feed();
        $items = is_array($feed['items'] ?? null) ? $feed['items'] : [];
        $critical = array_values(array_filter($items, static fn (array $i): bool => ($i['level'] ?? '') === 'critical'));
        $warning = array_values(array_filter($items, static fn (array $i): bool => ($i['level'] ?? '') === 'warning'));
        $toDiag = array_slice(array_merge($critical, $warning), 0, $maxItems);

        $tokenBudget = is_array($contextReports['token_budget'] ?? null) ? $contextReports['token_budget'] : [];
        $ollamaReady = !empty($this->llm->readiness()['ollama']['ready']);
        $llmAllowed = $useLlm && (
            $ollamaReady
            || (!empty($tokenBudget['sufficient_for_supervisor']) && $this->llm->isConfigured())
        );

        $diagnostics = [];
        foreach ($toDiag as $alert) {
            if (!is_array($alert)) {
                continue;
            }
            $diagnostics[] = $this->diagnoseOne($alert, $llmAllowed, $contextReports);
        }

        $report = [
            'ok' => true,
            'http' => 200,
            'generated_at' => date('c'),
            'alert_status' => (string) ($feed['status'] ?? 'ok'),
            'items_total' => count($toDiag),
            'llm_used' => $llmAllowed && $diagnostics !== [],
            'diagnostics' => $diagnostics,
        ];

        $this->store->write('diagnostics', $report);

        return $report;
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        $report = $this->store->read('diagnostics');
        if (!is_array($report)) {
            $report = [];
        }

        $feed = $this->alerts->feed();
        $storedByCode = [];
        foreach (is_array($report['diagnostics'] ?? null) ? $report['diagnostics'] : [] as $stored) {
            if (!is_array($stored)) {
                continue;
            }
            $code = (string) ($stored['code'] ?? '');
            if ($code !== '') {
                $storedByCode[$code] = $stored;
            }
        }

        $out = [];
        foreach (is_array($feed['items'] ?? null) ? $feed['items'] : [] as $alert) {
            if (!is_array($alert)) {
                continue;
            }
            $level = (string) ($alert['level'] ?? '');
            if (!in_array($level, ['critical', 'warning'], true)) {
                continue;
            }
            $code = (string) ($alert['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $out[] = isset($storedByCode[$code])
                ? $this->mergeDiagnosticWithAlert($storedByCode[$code], $alert)
                : $this->diagnoseOne($alert, false, []);
        }

        $report['diagnostics'] = $out;
        $report['alert_status'] = (string) ($feed['status'] ?? 'ok');
        $report['items_total'] = count($out);

        return $report;
    }

    /** @param array<string, mixed> $item @param array<string, mixed> $alert @return array<string, mixed> */
    private function mergeDiagnosticWithAlert(array $item, array $alert): array
    {
        foreach ([
            'fix_guide', 'fix_action', 'fixable', 'fix_label', 'url', 'job_id',
            'entity_type', 'entity_id', 'error_id', 'channel', 'detail', 'title', 'problem', 'level',
        ] as $key) {
            if (isset($alert[$key]) && $alert[$key] !== '' && $alert[$key] !== []) {
                $item[$key] = $alert[$key];
            }
        }
        if (($item['steps'] ?? []) === []) {
            $fromGuide = $this->stepsFromGuide($alert);
            if ($fromGuide !== []) {
                $item['steps'] = $fromGuide;
            }
        }

        return $item;
    }

    /** @param array<string, mixed> $alert @param array<string, mixed> $context @return array<string, mixed> */
    private function diagnoseOne(array $alert, bool $llmAllowed, array $context): array
    {
        $code = (string) ($alert['code'] ?? 'alert');
        $base = [
            'code' => $code,
            'level' => (string) ($alert['level'] ?? 'warning'),
            'title' => (string) ($alert['title'] ?? ''),
            'problem' => (string) ($alert['problem'] ?? $alert['detail'] ?? ''),
            'detail' => (string) ($alert['detail'] ?? ''),
            'url' => (string) ($alert['url'] ?? '/admin/alerts'),
            'fix_action' => (string) ($alert['fix_action'] ?? ''),
            'fixable' => !empty($alert['fixable']),
            'fix_label' => (string) ($alert['fix_label'] ?? 'Corectează'),
            'fix_guide' => is_array($alert['fix_guide'] ?? null) ? $alert['fix_guide'] : [],
            'error_id' => (int) ($alert['error_id'] ?? 0),
            'channel' => (string) ($alert['channel'] ?? ''),
            'job_id' => (string) ($alert['job_id'] ?? ''),
            'entity_type' => (string) ($alert['entity_type'] ?? ''),
            'entity_id' => (string) ($alert['entity_id'] ?? ''),
            'steps' => $this->stepsFromGuide($alert) ?: $this->heuristicSteps($code, $alert),
            'source' => 'heuristic',
        ];

        if (!$llmAllowed) {
            return $base;
        }

        $llmSteps = $this->llmSteps($alert, $context);
        if ($llmSteps !== []) {
            $base['steps'] = $llmSteps;
            $base['source'] = 'llm+heuristic';
            $base['llm_summary'] = $llmSteps[0] ?? '';
        }

        return $base;
    }

    /** @param array<string, mixed> $alert @return list<string> */
    private function stepsFromGuide(array $alert): array
    {
        $guide = is_array($alert['fix_guide'] ?? null) ? $alert['fix_guide'] : [];
        $steps = is_array($guide['steps'] ?? null) ? $guide['steps'] : [];
        $out = [];
        foreach ($steps as $step) {
            $s = trim((string) $step);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $alert @return list<string> */
    private function heuristicSteps(string $code, array $alert): array
    {
        $steps = match ($code) {
            'import_failed', 'job_blocked' => [
                'Deschide /admin/import și verifică job-ul eșuat.',
                'Citește mesajul de eroare pe rândul problematic.',
                'Corectează CSV sau rulează re-import parțial.',
            ],
            'tecdoc_unified', 'tecdoc_dead', 'tecdoc_api' => [
                'Verifică cheia RapidAPI în Setări.',
                'Controlează cotă și IP autorizat.',
                'Site-ul folosește stoc local până revine API-ul.',
            ],
            'scrape_do_token_missing', 'scrape_do_quota' => [
                'Adaugă/reîncarcă token Scrape.do în Setări.',
                'Verifică cotă rămasă pe scrape.do.',
                'Rulează test pipeline din /admin/scraper.',
            ],
            'products_no_image' => [
                'Deschide lista produse fără imagine.',
                'Rulează cron image_pipeline_retry sau pipeline manual.',
            ],
            'ai_key_missing', 'ai_api_error' => [
                'Configurează GROQ_KEY sau OPENAI_KEY în admin/.env.',
                'Testează din /admin/ai-tokens.',
            ],
            default => [
                'Deschide: ' . (string) ($alert['url'] ?? '/admin/alerts'),
                'Verifică jurnal erori: /admin/system-errors',
                'Dacă persistă — contactează suport sau rulează fix din alertă.',
            ],
        };

        if (!empty($alert['fix_action'])) {
            $steps[] = 'Acțiune automată disponibilă: ' . (string) $alert['fix_action'];
        }

        return $steps;
    }

    /** @param array<string, mixed> $alert @param array<string, mixed> $context @return list<string> */
    private function llmSteps(array $alert, array $context): array
    {
        $catalogScore = (int) (($context['catalog_audit']['score'] ?? 0));
        $pipelineFail = (int) (($context['pipeline_health']['failed'] ?? 0));

        $system = 'Ești diagnostic Besoiu Piese Auto. Răspunde în română, max 5 pași numerotați, fără invenții.';
        $user = "Alertă: " . json_encode([
            'title' => $alert['title'] ?? '',
            'detail' => $alert['detail'] ?? '',
            'code' => $alert['code'] ?? '',
            'catalog_score' => $catalogScore,
            'pipeline_failed_tests' => $pipelineFail,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->llm->complete($system, $user, 0.3, 45, 'supervisor_diagnostics');
        if (empty($result['ok']) || trim((string) ($result['content'] ?? '')) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim((string) $result['content'])) ?: [];
        $steps = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\d+[\.\)]\s*/', '', $line) ?? $line);
            if ($line !== '' && mb_strlen($line) > 8) {
                $steps[] = $line;
            }
            if (count($steps) >= 5) {
                break;
            }
        }

        return $steps;
    }
}
