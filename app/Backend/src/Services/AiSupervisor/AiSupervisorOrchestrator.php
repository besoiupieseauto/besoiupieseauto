<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor;

use Besoiu\Services\AiSupervisor\Config\AiSupervisorConfig;
use Besoiu\Services\AiSupervisor\Phase2\CatalogQualityScanner;
use Besoiu\Services\AiSupervisor\Phase3\PipelineHealthReporter;
use Besoiu\Services\AiSupervisor\Phase3\SupplierFileWatcher;
use Besoiu\Services\AiSupervisor\Phase3\TokenBudgetAnalyzer;
use Besoiu\Services\AiSupervisor\Phase4\ConversationAnalyzer;
use Besoiu\Services\AiSupervisor\Phase4\DailyReportBuilder;
use Besoiu\Services\AiSupervisor\Phase4\DiagnosticEngine;
use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;
use Besoiu\Services\ComposerRepairAgentService;
use Throwable;

/**
 * Orchestrator — rulează fazele supervizor în fundal (cron), respectând intervale.
 */
final class AiSupervisorOrchestrator
{
    public function __construct(
        private ?AiSupervisorConfig $config = null,
        private ?AiSupervisorStore $store = null,
    ) {
        $root = dirname(__DIR__, 4);
        $this->config = $config ?? new AiSupervisorConfig($root);
        $this->store = $store ?? new AiSupervisorStore($root);
    }

    /**
     * @param array<string, mixed> $options force_all, skip_daily
     * @return array<string, mixed>
     */
    public function runCycle(array $options = []): array
    {
        $started = microtime(true);
        $forceAll = !empty($options['force_all']);

        if (!$this->config->isEnabled() && !$forceAll) {
            return [
                'ok' => true,
                'http' => 200,
                'skipped' => true,
                'reason' => 'disabled',
            ];
        }

        $cfg = $this->config->get();
        $reports = [];
        $jobs = [];

        if (!empty($cfg['phase3_token_budget'])) {
            $jobs['token_budget'] = $this->runJob('token_budget', 300, $forceAll, fn () => (new TokenBudgetAnalyzer($this->config, $this->store))->run());
            $reports['token_budget'] = $jobs['token_budget']['result'] ?? [];
        }

        if (!empty($cfg['phase2_catalog_scan'])) {
            $limit = (int) ($cfg['catalog_scan_limit'] ?? 500);
            $jobs['catalog_audit'] = $this->runJob(
                'catalog_scan',
                $this->config->intervalSec('catalog_scan'),
                $forceAll,
                fn () => (new CatalogQualityScanner($this->store, $limit))->run()
            );
            $reports['catalog_audit'] = $jobs['catalog_audit']['result'] ?? [];
        }

        if (!empty($cfg['phase3_pipeline_health'])) {
            $jobs['pipeline_health'] = $this->runJob(
                'pipeline_health',
                $this->config->intervalSec('pipeline_health'),
                $forceAll,
                fn () => (new PipelineHealthReporter($this->store))->run()
            );
            $reports['pipeline_health'] = $jobs['pipeline_health']['result'] ?? [];
        }

        if (!empty($cfg['phase3_supplier_watch'])) {
            $jobs['supplier_watch'] = $this->runJob(
                'supplier_watch',
                $this->config->intervalSec('supplier_watch'),
                $forceAll,
                fn () => (new SupplierFileWatcher($this->store))->run()
            );
            $reports['supplier_watch'] = $jobs['supplier_watch']['result'] ?? [];
        }

        if (!empty($cfg['phase4_conversations'])) {
            $jobs['conversations'] = $this->runJob(
                'conversations',
                $this->config->intervalSec('conversations'),
                $forceAll,
                fn () => (new ConversationAnalyzer($this->config, $this->store))->run()
            );
            $reports['conversations'] = $jobs['conversations']['result'] ?? [];
        }

        if (!empty($cfg['phase4_diagnostics'])) {
            $jobs['diagnostics'] = $this->runJob(
                'diagnostics',
                $this->config->intervalSec('diagnostics'),
                $forceAll,
                fn () => (new DiagnosticEngine($this->config, $this->store))->run($reports)
            );
            $reports['diagnostics'] = $jobs['diagnostics']['result'] ?? [];
        }

        if (!empty($cfg['phase5_composer_repair'])) {
            $jobs['composer_repair'] = $this->runJob(
                'composer_repair',
                $this->config->intervalSec('composer_repair'),
                $forceAll,
                fn () => (new ComposerRepairAgentService(dirname(__DIR__, 4), $this->config, $this->store))->run()
            );
            $reports['composer_repair'] = $jobs['composer_repair']['result'] ?? [];
        }

        if (!empty($cfg['phase4_daily_report']) && empty($options['skip_daily'])) {
            $jobs['daily_report'] = $this->runJob(
                'daily_report',
                $this->config->intervalSec('daily_report'),
                $forceAll,
                fn () => (new DailyReportBuilder($this->store))->run($reports)
            );
            $reports['daily_report'] = $jobs['daily_report']['result'] ?? [];
        }

        $summary = [
            'ok' => true,
            'http' => 200,
            'started_at' => date('c', (int) $started),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'jobs' => $jobs,
            'reports' => array_map(static function ($r): array {
                if (!is_array($r)) {
                    return ['ok' => false];
                }

                return [
                    'ok' => !empty($r['ok']),
                    'http' => (int) ($r['http'] ?? 200),
                ];
            }, $reports),
        ];

        $this->store->write('last_cycle', $summary);

        return $summary;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $base = [
            'config' => $this->config->get(),
            'store' => $this->store->statusSummary(),
            'last_cycle' => $this->store->read('last_cycle'),
            'catalog_audit' => (new CatalogQualityScanner($this->store))->latest(),
            'pipeline_health' => (new PipelineHealthReporter($this->store))->latest(),
            'token_budget' => (new TokenBudgetAnalyzer($this->config, $this->store))->latest(),
            'supplier_watch' => (new SupplierFileWatcher($this->store))->latest(),
            'diagnostics' => (new DiagnosticEngine($this->config, $this->store))->latest(),
            'composer_repair' => (new ComposerRepairAgentService(dirname(__DIR__, 4), $this->config, $this->store))->latest(),
            'conversations' => (new ConversationAnalyzer($this->config, $this->store))->latest(),
            'daily_report' => (new DailyReportBuilder($this->store))->latest(),
        ];
        $base['deep_report'] = (new SupervisorDeepReportBuilder())->build($base);

        $catalogFile = dirname(__DIR__, 3) . '/system/api_automation_catalog.php';
        if (is_file($catalogFile)) {
            require_once $catalogFile;
            if (function_exists('api_supervisor_live_pulse')) {
                $base['live_pulse'] = api_supervisor_live_pulse(dirname(__DIR__, 4));
            }
        }

        $base['llm_router'] = \Besoiu\Services\LlmRouterService::create(dirname(__DIR__, 4))->readiness();
        $metroFile = dirname(__DIR__, 3) . '/system/metro_llm_hub.php';
        if (is_file($metroFile)) {
            require_once $metroFile;
            $base['metro_llm'] = metro_llm_hub_snapshot(dirname(__DIR__, 4));
        }

        return $base;
    }

    /**
     * @param callable(): array<string, mixed> $runner
     * @return array<string, mixed>
     */
    private function runJob(string $schedulerKey, int $intervalSec, bool $force, callable $runner): array
    {
        $check = $force ? ['ran' => true, 'reason' => 'forced'] : $this->store->shouldRun($schedulerKey, $intervalSec);
        if (!$check['ran']) {
            return [
                'ran' => false,
                'reason' => $check['reason'],
                'result' => $this->store->read(str_replace('_scan', '_audit', $schedulerKey)) ?: [],
            ];
        }

        try {
            $result = $runner();
            $http = (int) ($result['http'] ?? 200);
            $this->store->markRun($schedulerKey, $http >= 200 && $http < 300 ? 'ok' : 'warn', 'http=' . $http);

            return ['ran' => true, 'reason' => $check['reason'], 'result' => $result];
        } catch (Throwable $e) {
            $this->store->markRun($schedulerKey, 'error', $e->getMessage());

            return [
                'ran' => true,
                'reason' => 'error',
                'error' => $e->getMessage(),
                'result' => ['ok' => false, 'http' => 500, 'error' => $e->getMessage()],
            ];
        }
    }
}
