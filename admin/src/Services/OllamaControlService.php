<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Panou control Ollama — snapshot health, diff config, test integrări.
 */
final class OllamaControlService
{
    public function __construct(
        private readonly string $projectRoot = '',
    ) {
    }

    private function root(): string
    {
        if ($this->projectRoot !== '') {
            return $this->projectRoot;
        }
        if (defined('BESOIU_ROOT')) {
            return (string) BESOIU_ROOT;
        }

        return dirname(__DIR__, 4);
    }

    private function bootHealth(): void
    {
        require_once dirname(__DIR__, 2) . '/system/ollama_health.php';
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $this->bootHealth();

        return ollama_health_snapshot($this->root());
    }

    /** @return array<string, mixed> */
    public function testIntegration(string $integrationId): array
    {
        $this->bootHealth();
        $integrationId = trim($integrationId);
        if ($integrationId === '') {
            return ['ok' => false, 'error' => 'integration_id lipsă'];
        }

        return ollama_health_test_integration($integrationId, $this->root());
    }

    /** @return list<array<string, mixed>> */
    public function recentErrors(int $limit = 50): array
    {
        require_once dirname(__DIR__, 2) . '/system/ollama_error_log.php';

        return ollama_error_log_recent($limit, $this->root());
    }

    /** @param array{live?:bool,ids?:list<string>} $options @return array<string, mixed> */
    public function verifyAll(array $options = []): array
    {
        require_once dirname(__DIR__, 2) . '/system/ollama_opportunities.php';

        return ollama_opportunities_run_all($this->root(), $options);
    }

    /** @return array<string, mixed> */
    public function verifyOne(string $opportunityId, bool $live = false): array
    {
        require_once dirname(__DIR__, 2) . '/system/ollama_opportunities.php';

        return ollama_opportunities_run_one($opportunityId, $this->root(), $live);
    }
}
