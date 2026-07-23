<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\OllamaControlService;
use Besoiu\Services\OllamaLlmClient;

/**
 * Dashboard AI & RAG — snapshot pentru panou admin.
 */
final class AiRagDashboardService
{
    private string $root;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $ollama = new OllamaLlmClient($this->root . '/app');
        $readiness = $ollama->readiness();
        $vector = (new AiVectorStoreService($this->root))->status();
        $logStats = (new AiInteractionLogService(null, $this->root))->todayStats();
        $modules = (new AiRagConfigService($this->root))->allModulesWithConfig();

        $enabledCount = 0;
        foreach ($modules['modules'] ?? [] as $m) {
            if (!empty($m['enabled'])) {
                $enabledCount++;
            }
        }

        $ollamaHealth = [];
        try {
            require_once dirname(__DIR__, 3) . '/system/ollama_health.php';
            $ollamaHealth = ollama_health_ping_tags();
        } catch (\Throwable) {
            $ollamaHealth = ['ok' => false, 'error' => 'health unavailable'];
        }

        return [
            'generated_at' => date('c'),
            'principle' => 'AI propune, omul aprobă — fără auto-apply în Faza 1',
            'ollama' => [
                'enabled' => $ollama->isEnabled(),
                'ready' => !empty($readiness['ready']),
                'model' => (string) ($readiness['model'] ?? ''),
                'reachable' => !empty($ollamaHealth['ok']),
                'latency_ms' => (int) ($ollamaHealth['latency_ms'] ?? 0),
            ],
            'vector_store' => $vector,
            'corpus_rag' => (new AiRagCorpusService($this->root))->status(),
            'activity_today' => $logStats,
            'modules_enabled' => $enabledCount,
            'modules_total' => count($modules['modules'] ?? []),
            'phase' => 2,
            'alerts' => $this->activeAlerts(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function activeAlerts(): array
    {
        $alerts = [];
        if (!is_file($this->root . '/app/Import/MatchingPro/state/cron_progress.json')) {
            return $alerts;
        }
        $progress = json_decode((string) file_get_contents($this->root . '/app/Import/MatchingPro/state/cron_progress.json'), true);
        if (is_array($progress) && ($progress['status'] ?? '') === 'error') {
            $alerts[] = [
                'type' => 'cron_error',
                'message' => (string) ($progress['message'] ?? 'Eroare cron import'),
                'severity' => 'high',
            ];
        }

        return $alerts;
    }
}
