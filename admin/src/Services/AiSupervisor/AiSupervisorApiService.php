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
use Besoiu\Services\ComposerRepairAgentService;

/**
 * Fațadă API — acțiuni supervizor pentru endpoint și UI.
 */
final class AiSupervisorApiService
{
    public function __construct(
        private ?AiSupervisorOrchestrator $orchestrator = null,
        private ?AiSupervisorConfig $config = null,
    ) {
        $this->orchestrator = $orchestrator ?? new AiSupervisorOrchestrator();
        $this->config = $config ?? new AiSupervisorConfig();
    }

    /** @return array<string, mixed> */
    public function getStatus(): array
    {
        return [
            'success' => true,
            'http' => 200,
            'data' => $this->orchestrator->status(),
        ];
    }

    /** @param array<string, mixed> $params */
    public function postAction(string $action, array $params = []): array
    {
        return match ($action) {
            'supervisor_config_get' => $this->wrap($this->config->get()),
            'supervisor_config_set' => $this->wrap($this->config->set($params)),
            'supervisor_run_cycle' => $this->runCycle($params),
            'supervisor_run_catalog' => $this->wrap((new CatalogQualityScanner())->run()),
            'supervisor_run_pipeline' => $this->wrap((new PipelineHealthReporter())->run()),
            'supervisor_run_tokens' => $this->wrap((new TokenBudgetAnalyzer($this->config))->run()),
            'supervisor_run_suppliers' => $this->wrap((new SupplierFileWatcher())->run()),
            'supervisor_run_diagnostics' => $this->wrap((new DiagnosticEngine($this->config))->run()),
            'supervisor_run_composer_repair' => $this->wrap((new ComposerRepairAgentService(null, $this->config))->run(['force' => true])),
            'supervisor_run_conversations' => $this->wrap((new ConversationAnalyzer($this->config))->run()),
            'supervisor_run_daily_report' => $this->wrap((new DailyReportBuilder())->run([])),
            default => ['success' => false, 'http' => 400, 'message' => 'Acțiune supervizor necunoscută: ' . $action],
        };
    }

    /** @param array<string, mixed> $params @return array<string, mixed> */
    private function runCycle(array $params): array
    {
        $result = $this->orchestrator->runCycle([
            'force_all' => !empty($params['force_all']),
            'skip_daily' => !empty($params['skip_daily']),
        ]);

        return [
            'success' => !empty($result['ok']),
            'http' => (int) ($result['http'] ?? 200),
            'message' => 'Ciclu supervizor finalizat.',
            'data' => $result,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function wrap(array $data): array
    {
        $http = (int) ($data['http'] ?? 200);

        return [
            'success' => $http >= 200 && $http < 300 || $http === 207,
            'http' => $http,
            'data' => $data,
        ];
    }
}
