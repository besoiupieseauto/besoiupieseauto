<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Fațadă Metro LLM — Setări + AI Agent.
 */
final class MetroLlmHubService
{
    public function __construct(
        private readonly string $projectRoot = '',
    ) {
    }

    private function root(): string
    {
        return $this->projectRoot !== ''
            ? $this->projectRoot
            : dirname(__DIR__, 3);
    }

    private function boot(): void
    {
        require_once dirname(__DIR__, 2) . '/system/metro_llm_hub.php';
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $this->boot();

        return metro_llm_hub_snapshot($this->root());
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function saveConfig(array $input): array
    {
        $this->boot();
        $res = metro_llm_save_config($input);
        if (!$res['ok']) {
            throw new \RuntimeException($res['message']);
        }

        return [
            'message' => 'Config Metro LLM salvat.',
            'updated' => $res['updated'],
            'metro_llm' => metro_llm_hub_snapshot($this->root()),
        ];
    }

    /** @return array<string, mixed> */
    public function testOllama(): array
    {
        $this->boot();

        return metro_llm_test_ollama($this->root());
    }

    /** @return array<string, mixed> */
    public function testCloud(): array
    {
        $this->boot();

        return metro_llm_test_cloud($this->root());
    }

    /** @return array<string, mixed> */
    public function testLocalCycle(): array
    {
        $this->boot();

        return metro_llm_test_local_cycle($this->root());
    }

    /** @return list<array<string, mixed>> */
    public function recentRoutes(int $limit = 20): array
    {
        $this->boot();

        return metro_llm_route_log_recent($limit);
    }
}
