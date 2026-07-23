<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Services\AiSupervisor\Config\AiSupervisorConfig;
use Besoiu\Services\AiSupervisor\Phase3\TokenBudgetAnalyzer;
use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;
use Throwable;

/**
 * Agent Composer 2.5 — analizează alerte ops și execută reparări sigure (sistem individual).
 */
final class ComposerRepairAgentService
{
    /** @var list<string> */
    private const ALLOWED_FIX_ACTIONS = [
        'resolve_system_error',
        'refresh_tecdoc',
        'clear_ai_error',
        'dismiss_import_error',
        'cancel_blocked_job',
        'test_integration',
    ];

    /** @var list<string> */
    private const AUTO_SAFE_ACTIONS = [
        'dismiss_import_error',
        'cancel_blocked_job',
        'clear_ai_error',
        'resolve_system_error',
        'refresh_tecdoc',
    ];

    private string $projectRoot;

    public function __construct(
        ?string $projectRoot = null,
        private ?AiSupervisorConfig $config = null,
        private ?AiSupervisorStore $store = null,
        private ?AdminOpsAlertsService $alerts = null,
        private ?AdminOpsAlertsFixService $fixService = null,
        private ?LlmRouterService $llm = null,
    ) {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->config = $config ?? new AiSupervisorConfig($this->projectRoot);
        $this->store = $store ?? new AiSupervisorStore($this->projectRoot);
        $this->alerts = $alerts ?? new AdminOpsAlertsService();
        $this->fixService = $fixService ?? new AdminOpsAlertsFixService();
        $this->llm = $llm ?? LlmRouterService::create($this->projectRoot);
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        $report = $this->store->read('composer_repair');
        if (!is_array($report)) {
            return [
                'ok' => true,
                'http' => 200,
                'generated_at' => null,
                'items' => [],
                'summary' => 'Agent Composer — nerulat încă.',
            ];
        }

        return $report;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $cfg = $this->config->get();
        $latest = $this->latest();
        $tokenBudget = (new TokenBudgetAnalyzer($this->config, $this->store))->latest();

        return [
            'agent_id' => 'ops-composer-repair',
            'model' => $this->llm->model(),
            'configured' => $this->llm->isConfigured(),
            'enabled' => !empty($cfg['phase5_composer_repair']),
            'auto_execute' => !empty($cfg['composer_repair_auto_execute']),
            'min_confidence' => (float) ($cfg['composer_repair_min_confidence'] ?? 0.72),
            'max_items' => (int) ($cfg['composer_repair_max_items'] ?? 5),
            'llm_allowed' => !empty($tokenBudget['sufficient_for_supervisor']) && $this->llm->isConfigured(),
            'last_run' => $latest['generated_at'] ?? null,
            'last_summary' => (string) ($latest['summary'] ?? ''),
            'items' => is_array($latest['items'] ?? null) ? $latest['items'] : [],
            'stats' => is_array($latest['stats'] ?? null) ? $latest['stats'] : [],
        ];
    }

    /**
     * Ciclu complet: analiză Composer + reparare automată (dacă e activată).
     *
     * @param array<string, mixed> $options force, auto_execute, code
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        $cfg = $this->config->get();
        if (empty($cfg['phase5_composer_repair']) && empty($options['force'])) {
            return [
                'ok' => true,
                'http' => 200,
                'skipped' => true,
                'reason' => 'disabled',
                'summary' => 'Agent Composer dezactivat în config.',
            ];
        }

        $tokenBudget = (new TokenBudgetAnalyzer($this->config, $this->store))->run();
        $llmAllowed = $this->llmRepairAllowed($tokenBudget);

        $singleCode = trim((string) ($options['code'] ?? ''));
        $items = $this->collectAlerts($singleCode !== '' ? $singleCode : null);
        $autoExecute = array_key_exists('auto_execute', $options)
            ? !empty($options['auto_execute'])
            : !empty($cfg['composer_repair_auto_execute']);
        $minConfidence = (float) ($cfg['composer_repair_min_confidence'] ?? 0.72);

        $results = [];
        $stats = ['analyzed' => 0, 'repaired' => 0, 'skipped' => 0, 'failed' => 0, 'manual_only' => 0];

        foreach ($items as $alert) {
            ++$stats['analyzed'];
            $one = $this->analyzeAlert($alert, $llmAllowed, $tokenBudget);
            if ($autoExecute && !empty($one['should_auto_fix']) && !empty($one['fix_action'])) {
                $one = $this->executeItem($one, $minConfidence);
            }
            $results[] = $one;

            match ($one['outcome'] ?? 'pending') {
                'repaired' => ++$stats['repaired'],
                'failed' => ++$stats['failed'],
                'manual_only' => ++$stats['manual_only'],
                default => ++$stats['skipped'],
            };
        }

        $summary = $this->buildSummary($results, $stats, $llmAllowed);
        $report = [
            'ok' => true,
            'http' => 200,
            'generated_at' => date('c'),
            'model' => $this->llm->model(),
            'llm_used' => $llmAllowed,
            'auto_execute' => $autoExecute,
            'summary' => $summary,
            'stats' => $stats,
            'items' => $results,
        ];

        $this->store->write('composer_repair', $report);
        $this->writeAgentRuntime($report);

        return $report;
    }

    /**
     * Analizează o singură alertă (fără execuție).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function analyzeOne(string $code, array $options = []): array
    {
        $feed = $this->alerts->feed();
        $alert = $this->findAlert($feed, $code);
        if ($alert === null) {
            return ['ok' => false, 'http' => 404, 'message' => 'Alertă negăsită: ' . $code];
        }

        $tokenBudget = (new TokenBudgetAnalyzer($this->config, $this->store))->latest();
        $llmAllowed = $this->llmRepairAllowed($tokenBudget);

        $item = $this->analyzeAlert($alert, $llmAllowed, $tokenBudget);

        return ['ok' => true, 'http' => 200, 'data' => $item];
    }

    /**
     * Execută repararea pentru un plan/analiză existentă.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function executeItem(array $item, ?float $minConfidence = null): array
    {
        $minConfidence ??= (float) ($this->config->get()['composer_repair_min_confidence'] ?? 0.72);
        $confidence = (float) ($item['confidence'] ?? 0);
        $fixAction = trim((string) ($item['fix_action'] ?? ''));

        if ($fixAction === '' || !in_array($fixAction, self::ALLOWED_FIX_ACTIONS, true)) {
            $item['outcome'] = 'manual_only';
            $item['execute_message'] = 'Fără acțiune automată validă — intervenție manuală.';

            return $item;
        }

        if (!$this->isAutoSafe($fixAction, $item)) {
            $item['outcome'] = 'manual_only';
            $item['execute_message'] = 'Acțiunea necesită confirmare manuală: ' . $fixAction;

            return $item;
        }

        if ($confidence < $minConfidence && empty($item['force_execute'])) {
            $item['outcome'] = 'skipped';
            $item['execute_message'] = 'Încredere sub prag (' . round($confidence * 100) . '% < ' . round($minConfidence * 100) . '%).';

            return $item;
        }

        $params = is_array($item['fix_params'] ?? null) ? $item['fix_params'] : [];
        $params['fix_action'] = $fixAction;
        $params['code'] = (string) ($item['code'] ?? '');

        foreach (['job_id', 'error_id', 'channel', 'entity_type', 'entity_id'] as $key) {
            if (!isset($params[$key]) && isset($item[$key]) && (string) $item[$key] !== '') {
                $params[$key] = $item[$key];
            }
        }

        try {
            $result = $this->fixService->fix((string) ($item['code'] ?? ''), $params);
            $item['execute_result'] = $result;
            $item['outcome'] = !empty($result['fixed']) ? 'repaired' : (!empty($result['success']) ? 'partial' : 'failed');
            $item['execute_message'] = (string) ($result['message'] ?? 'Executat.');
        } catch (Throwable $e) {
            $item['outcome'] = 'failed';
            $item['execute_message'] = $e->getMessage();
        }

        return $item;
    }

    /** @param array<string, mixed> $report */
    private function writeAgentRuntime(array $report): void
    {
        $dir = $this->projectRoot . '/robot/data/ai_agents/ops-composer-repair';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $lines = [
            '# Ops Composer Repair — ' . date('Y-m-d H:i:s'),
            '',
            '**Model:** ' . ($report['model'] ?? 'composer-2.5'),
            '**Rezumat:** ' . ($report['summary'] ?? ''),
            '',
        ];

        foreach (is_array($report['items'] ?? null) ? $report['items'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = '## ' . ($item['title'] ?? $item['code'] ?? 'Alertă');
            $lines[] = '- Cod: `' . ($item['code'] ?? '') . '`';
            $lines[] = '- Analiză: ' . ($item['analysis_ro'] ?? $item['root_cause'] ?? '—');
            $lines[] = '- Outcome: **' . ($item['outcome'] ?? 'pending') . '**';
            if (!empty($item['execute_message'])) {
                $lines[] = '- Exec: ' . $item['execute_message'];
            }
            $lines[] = '';
        }

        $md = implode("\n", $lines);
        @file_put_contents($dir . '/runtime.md', $md);
        @file_put_contents($dir . '/runtime.json', json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @file_put_contents($dir . '/state.json', json_encode([
            'last_run_at' => date('c'),
            'runtime_chars' => strlen($md),
            'stats' => $report['stats'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** @param array<int, array<string, mixed>> $results @param array<string, int> $stats */
    private function buildSummary(array $results, array $stats, bool $llmUsed): string
    {
        if ($results === []) {
            return 'Nicio alertă critică/warning — sistem OK.';
        }

        $parts = [];
        if ($llmUsed) {
            $parts[] = 'Composer 2.5';
        } else {
            $parts[] = 'Mod heuristic (fără LLM sau buget insuficient)';
        }
        $parts[] = $stats['analyzed'] . ' analizate';
        if ($stats['repaired'] > 0) {
            $parts[] = $stats['repaired'] . ' reparate';
        }
        if ($stats['failed'] > 0) {
            $parts[] = $stats['failed'] . ' eșuate';
        }
        if ($stats['manual_only'] > 0) {
            $parts[] = $stats['manual_only'] . ' manual';
        }

        return implode(' · ', $parts);
    }

    /** @return list<array<string, mixed>> */
    private function collectAlerts(?string $onlyCode = null): array
    {
        $cfg = $this->config->get();
        $max = max(1, min(10, (int) ($cfg['composer_repair_max_items'] ?? 5)));
        $feed = $this->alerts->feed();
        $items = is_array($feed['items'] ?? null) ? $feed['items'] : [];

        if ($onlyCode !== null) {
            $found = $this->findAlert($feed, $onlyCode);

            return $found !== null ? [$found] : [];
        }

        $critical = array_values(array_filter($items, static fn (array $i): bool => ($i['level'] ?? '') === 'critical'));
        $warning = array_values(array_filter($items, static fn (array $i): bool => ($i['level'] ?? '') === 'warning'));

        return array_slice(array_merge($critical, $warning), 0, $max);
    }

    /** @param array<string, mixed> $feed @return array<string, mixed>|null */
    private function findAlert(array $feed, string $code): ?array
    {
        $code = strtolower(trim($code));
        foreach (is_array($feed['items'] ?? null) ? $feed['items'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (strtolower((string) ($item['code'] ?? '')) === $code) {
                return $item;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $alert @param array<string, mixed> $tokenBudget @return array<string, mixed> */
    private function analyzeAlert(array $alert, bool $llmAllowed, array $tokenBudget): array
    {
        $base = [
            'code' => (string) ($alert['code'] ?? ''),
            'level' => (string) ($alert['level'] ?? 'warning'),
            'title' => (string) ($alert['title'] ?? ''),
            'detail' => (string) ($alert['detail'] ?? ''),
            'fixable' => !empty($alert['fixable']),
            'fix_action' => (string) ($alert['fix_action'] ?? ''),
            'fix_label' => (string) ($alert['fix_label'] ?? 'Corectează'),
            'url' => (string) ($alert['url'] ?? '/admin/alerts'),
            'job_id' => (string) ($alert['job_id'] ?? ''),
            'error_id' => (int) ($alert['error_id'] ?? 0),
            'channel' => (string) ($alert['channel'] ?? ''),
            'entity_type' => (string) ($alert['entity_type'] ?? ''),
            'entity_id' => (string) ($alert['entity_id'] ?? ''),
            'fix_params' => [],
            'source' => 'heuristic',
            'outcome' => 'pending',
            'confidence' => 0.0,
            'should_auto_fix' => false,
            'analysis_ro' => '',
            'root_cause' => '',
            'manual_steps' => [],
        ];

        if ($base['fixable'] && $base['fix_action'] !== '') {
            $base['should_auto_fix'] = in_array($base['fix_action'], self::AUTO_SAFE_ACTIONS, true);
            $base['confidence'] = 0.78;
            $base['analysis_ro'] = 'Alertă cu acțiune automată disponibilă: ' . $base['fix_action'];
            $base['root_cause'] = $base['detail'];
        } else {
            $base['manual_steps'] = $this->heuristicManualSteps($base['code'], $alert);
            $base['analysis_ro'] = 'Necesită intervenție manuală — fără fix automat mapat.';
            $base['root_cause'] = $base['detail'];
            $base['outcome'] = 'manual_only';
        }

        if (!$llmAllowed) {
            return $base;
        }

        $plan = $this->llmPlan($alert, $tokenBudget);
        if ($plan === null) {
            return $base;
        }

        $base['source'] = 'composer-2.5';
        if (trim((string) ($plan['analysis_ro'] ?? '')) !== '') {
            $base['analysis_ro'] = (string) $plan['analysis_ro'];
        }
        if (trim((string) ($plan['root_cause'] ?? '')) !== '') {
            $base['root_cause'] = (string) $plan['root_cause'];
        }
        if (is_array($plan['manual_steps'] ?? null)) {
            $base['manual_steps'] = array_values(array_filter(array_map('strval', $plan['manual_steps'])));
        }
        $base['confidence'] = max(0.0, min(1.0, (float) ($plan['confidence'] ?? $base['confidence'])));

        $suggestedAction = trim((string) ($plan['fix_action'] ?? ''));
        if ($suggestedAction !== '' && in_array($suggestedAction, self::ALLOWED_FIX_ACTIONS, true)) {
            $base['fix_action'] = $suggestedAction;
            $base['should_auto_fix'] = !empty($plan['should_auto_fix'])
                && in_array($suggestedAction, self::AUTO_SAFE_ACTIONS, true);
        }

        if (is_array($plan['fix_params'] ?? null)) {
            $base['fix_params'] = $plan['fix_params'];
        }

        return $base;
    }

    /**
     * Flux unificat pentru ops_alert_fix — Composer analizează apoi execută.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function fixAlert(string $code, array $params = [], bool $execute = true): array
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return [
                'success' => false,
                'fixed' => false,
                'message' => 'Lipsește codul alertei.',
            ];
        }

        $feed = $this->alerts->feed();
        $alert = $this->findAlert($feed, $code);
        if ($alert === null) {
            $alert = ['code' => $code, 'title' => $code, 'detail' => ''];
        }

        foreach (['fix_action', 'job_id', 'error_id', 'channel', 'entity_type', 'entity_id'] as $key) {
            if (isset($params[$key]) && (string) $params[$key] !== '') {
                $alert[$key] = $params[$key];
            }
        }
        if (isset($params['fix_action']) && (string) $params['fix_action'] !== '') {
            $alert['fixable'] = true;
            $alert['fix_action'] = (string) $params['fix_action'];
        }

        $tokenBudget = (new TokenBudgetAnalyzer($this->config, $this->store))->latest();
        $cfg = $this->config->get();
        $llmAllowed = $this->llmRepairAllowed($tokenBudget);

        $item = $this->analyzeAlert($alert, $llmAllowed, $tokenBudget);

        if ($execute && (!empty($item['should_auto_fix']) || !empty($params['force_execute']))) {
            $item['force_execute'] = !empty($params['force_execute']);
            $item = $this->executeItem($item);
        } elseif ($execute && !empty($item['fixable']) && trim((string) ($item['fix_action'] ?? '')) !== '') {
            // Confirmare manuală din UI (ops_alert_fix) — execută cu job_id obligatoriu unde e cazul
            $item['force_execute'] = true;
            $item = $this->executeItem($item);
        }

        $feedAfter = $this->alerts->feed();
        $stillActive = $this->alertStillActive($feedAfter, $code, $params);

        $execResult = is_array($item['execute_result'] ?? null) ? $item['execute_result'] : [];
        $fixed = !$stillActive;
        $message = $this->composeFixMessage($code, $item, $stillActive);

        return [
            'success' => true,
            'fixed' => $fixed,
            'still_active' => $stillActive,
            'partial' => !$fixed && !empty($execResult['success']),
            'message' => $message,
            'composer' => $item,
            'data' => $feedAfter,
        ];
    }

    /** @param array<string, mixed> $item */
    private function composeFixMessage(string $code, array $item, bool $stillActive): string
    {
        $execMsg = trim((string) ($item['execute_message'] ?? ''));
        $analysis = trim((string) ($item['analysis_ro'] ?? ''));
        $base = $execMsg !== '' ? $execMsg : $analysis;

        if (!$stillActive) {
            return $base !== ''
                ? $base . ' Alerta a dispărut din monitor.'
                : 'Reparat — alerta a dispărut din monitor.';
        }

        $reason = match ($code) {
            'import_failed' => 'Mai există job(uri) eșuate sau CSV-ul trebuie corectat manual înainte de relansare.',
            'job_blocked' => 'Job-ul încă rulează sau nu s-a putut opri — deschide Import (#job-progress-wrap) și oprește manual.',
            'link_broken' => 'Testul conexiune încă eșuează — verifică URL/credențiale furnizor.',
            'tecdoc_unified', 'tecdoc_dead', 'tecdoc_api', 'tecdoc_ip_invalid' => 'API TecDoc / RapidAPI încă indisponibil — verifică cheia, cota sau IP whitelist.',
            'ai_api_error' => 'API AI încă raportează eroare — verifică cheile în Setări.',
            default => 'Monitorul încă detectează problema — urmează pașii manuali din ghid.',
        };

        if ($base !== '') {
            return $base . ' Nu e rezolvat complet: ' . $reason;
        }

        return 'Acțiune parțială — ' . $reason;
    }

    /** @param array<string, mixed> $feed @param array<string, mixed> $params */
    private function alertStillActive(array $feed, string $code, array $params): bool
    {
        foreach (is_array($feed['items'] ?? null) ? $feed['items'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (strtolower((string) ($item['code'] ?? '')) !== $code) {
                continue;
            }
            if ($code === 'import_failed' || $code === 'job_blocked') {
                $jobId = trim((string) ($params['job_id'] ?? ''));
                if ($jobId !== '' && trim((string) ($item['job_id'] ?? '')) !== '' && $jobId !== (string) ($item['job_id'] ?? '')) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $tokenBudget */
    private function llmRepairAllowed(array $tokenBudget): bool
    {
        $cfg = $this->config->get();
        if (empty($cfg['composer_repair_use_llm'])) {
            return false;
        }

        $readiness = $this->llm->readiness();
        $ollamaOk = !empty($readiness['ollama']['ready']);
        $cursorOk = !empty($readiness['cursor']['ready']);

        if (!$ollamaOk && !$cursorOk) {
            return false;
        }

        // Ollama local = fără consum cloud — nu blocăm pe buget tokeni
        if ($ollamaOk) {
            return true;
        }

        return !empty($tokenBudget['sufficient_for_supervisor']) && $cursorOk;
    }

    /** @param array<string, mixed> $alert @param array<string, mixed> $context @return array<string, mixed>|null */
    private function llmPlan(array $alert, array $context): ?array
    {
        $allowed = implode(', ', self::ALLOWED_FIX_ACTIONS);
        $system = <<<PROMPT
Ești agentul Composer 2.5 Ops Repair pentru Besoiu Piese Auto (admin PHP).
Analizezi alerte operaționale și propui reparare SIGURĂ.
Răspunde DOAR cu JSON valid (fără markdown), schema:
{
  "analysis_ro": "2-3 propoziții în română",
  "root_cause": "cauză probabilă scurtă",
  "should_auto_fix": true|false,
  "fix_action": "una din: {$allowed} sau string gol",
  "fix_params": {"job_id":"","error_id":0,"channel":"","entity_type":"","entity_id":""},
  "confidence": 0.0-1.0,
  "manual_steps": ["pas 1", "pas 2"]
}
Reguli:
- should_auto_fix=true DOAR dacă fix_action e sigur (curățare job, oprire job blocat, clear eroare AI, refresh tecdoc).
- Nu inventa job_id — folosește cel din alertă.
- Dacă alerta e import_failed sau job_blocked, preferă dismiss_import_error sau cancel_blocked_job.
PROMPT;

        $user = json_encode([
            'alert' => [
                'code' => $alert['code'] ?? '',
                'title' => $alert['title'] ?? '',
                'detail' => $alert['detail'] ?? '',
                'fixable' => $alert['fixable'] ?? false,
                'fix_action' => $alert['fix_action'] ?? '',
                'job_id' => $alert['job_id'] ?? '',
                'error_id' => $alert['error_id'] ?? 0,
                'entity_type' => $alert['entity_type'] ?? '',
                'entity_id' => $alert['entity_id'] ?? '',
            ],
            'context' => [
                'tokens_today' => $context['tokens_today'] ?? 0,
                'pipeline_failed' => $context['pipeline_health']['failed'] ?? null,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->llm->complete($system, $user, 0.2, 60, 'composer_repair');
        if (empty($result['ok']) || trim((string) ($result['content'] ?? '')) === '') {
            return null;
        }

        return $this->parseJsonFromLlm((string) $result['content']);
    }

    /** @return array<string, mixed>|null */
    private function parseJsonFromLlm(string $content): ?array
    {
        $content = trim($content);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/u', $content, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** @param array<string, mixed> $item */
    private function isAutoSafe(string $fixAction, array $item): bool
    {
        if (!in_array($fixAction, self::AUTO_SAFE_ACTIONS, true)) {
            return false;
        }

        if ($fixAction === 'test_integration' && trim((string) ($item['entity_id'] ?? '')) === '') {
            return false;
        }

        if (in_array($fixAction, ['dismiss_import_error', 'cancel_blocked_job'], true)) {
            $jobId = trim((string) ($item['job_id'] ?? ''));
            if ($jobId === '') {
                $jobId = trim((string) (($item['fix_params']['job_id'] ?? '')));
            }

            return $jobId !== '';
        }

        if (in_array($fixAction, ['resolve_system_error', 'refresh_tecdoc'], true)) {
            return (int) ($item['error_id'] ?? 0) > 0;
        }

        return true;
    }

    /** @param array<string, mixed> $alert @return list<string> */
    private function heuristicManualSteps(string $code, array $alert): array
    {
        return match ($code) {
            'products_no_image' => [
                'Deschide /admin/product și filtrează fără imagine.',
                'Rulează pipeline Scraper Plan 1→3 sau cron imagini.',
            ],
            'import_queue_pending' => [
                'Deschide /admin/importreview și publică sau respinge rândurile.',
            ],
            default => [
                'Deschide: ' . (string) ($alert['url'] ?? '/admin/alerts'),
                'Verifică jurnal: /admin/system-errors',
            ],
        };
    }
}
