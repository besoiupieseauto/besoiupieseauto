<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Orchestrator Metro AI — punct unic pentru toate modulele LLM din proiect.
 *
 * Pași orchestra:
 * 1. Registry module (chat, import, imagini, scraper, supervizor)
 * 2. Profil task → LlmRouterService (local / hybrid / cloud)
 * 3. Jurnal unificat routes.jsonl + orchestra_stats.json
 * 4. Bridge scraper / import gate / audit imagini
 * 5. Test suite + statistici agregate
 */
final class MetroAiOrchestrator
{
    private LlmRouterService $router;

    public function __construct(
        private readonly string $projectRoot,
        ?LlmRouterService $router = null,
    ) {
        LlmClientSupport::loadEnv($projectRoot);
        $this->router = $router ?? LlmRouterService::create($projectRoot);
    }

    public static function create(string $projectRoot): self
    {
        return new self($projectRoot);
    }

    /** @return array<string, mixed> */
    public function readiness(): array
    {
        return [
            'ready' => $this->router->isConfigured(),
            'router' => $this->router->readiness(),
            'registry' => $this->registry(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function registry(): array
    {
        $this->loadOrchestraHelper();

        return metro_ai_orchestra_module_registry();
    }

    /** @return array<string, mixed> */
    public function statistics(): array
    {
        $this->loadOrchestraHelper();

        return metro_ai_orchestra_aggregate($this->projectRoot);
    }

    /**
     * @param array<string, mixed> $options temperature, timeout_sec, module_id
     * @return array<string, mixed>
     */
    public function dispatchComplete(
        string $task,
        string $systemPrompt,
        string $userPrompt,
        array $options = [],
    ): array {
        $temp = (float) ($options['temperature'] ?? 0.7);
        $timeout = (int) ($options['timeout_sec'] ?? 90);
        $moduleId = (string) ($options['module_id'] ?? $task);

        $res = $this->router->complete($systemPrompt, $userPrompt, $temp, $timeout, $task);
        $this->logModule($moduleId, 'complete', $res);

        return $res;
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function dispatchChat(
        string $task,
        array $messages,
        string $systemPrompt = '',
        array $options = [],
    ): array {
        $temp = (float) ($options['temperature'] ?? 0.7);
        $timeout = (int) ($options['timeout_sec'] ?? 90);
        $moduleId = (string) ($options['module_id'] ?? $task);

        $res = $this->router->chat($messages, $systemPrompt, $temp, $timeout, $task);
        $this->logModule($moduleId, 'chat', $res);

        return $res;
    }

    /**
     * Audit imagine produs — Cursor/OpenAI vision via ProductImageAuditService.
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function dispatchImageAudit(array $product, array $options = []): array
    {
        $service = new ProductImageAuditService($this->projectRoot);
        $engine = ProductImageAuditService::auditEngine();

        try {
            if ($engine === 'openai' || !empty($options['force_openai'])) {
                require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperLlmConfig.php';
                $key = \ScraperLlmConfig::openaiKey();
                if ($key === '') {
                    throw new \RuntimeException('OPENAI_KEY lipsește pentru audit vision.');
                }
                $model = trim((string) ($_ENV['IMAGE_AUDIT_MODEL'] ?? getenv('IMAGE_AUDIT_MODEL') ?: 'gpt-4o-mini'));
                $res = $service->analyzeProductWithVision($product, $key, $model);
                $res['ok'] = !in_array(strtolower((string) ($res['verdict'] ?? '')), ['error', 'no_image'], true);
                $res['routed_via'] = 'openai_vision';
            } else {
                $res = [
                    'ok' => true,
                    'verdict' => 'review',
                    'match_score' => 0,
                    'summary_ro' => 'Audit Cursor — mod batch (prepare job în admin).',
                    'routed_via' => 'cursor_batch',
                    'engine' => $engine,
                ];
            }
            $this->logModule('image_audit', 'vision', $res);

            return $res;
        } catch (\Throwable $e) {
            $err = ['ok' => false, 'error' => $e->getMessage(), 'routed_via' => 'none'];
            $this->logModule('image_audit', 'vision', $err);

            return $err;
        }
    }

    /**
     * Poartă AI imagini import — heuristic + vision opțional.
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed> $hit
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    public function dispatchImportImageGate(array $product, array $hit, array $opts = []): array
    {
        require_once dirname(__DIR__, 3) . '/lib/Scraper/PipelineImageAiGate.php';
        $opts['orchestrator'] = true;
        $res = \PipelineImageAiGate::filterHit($product, $hit, $opts);
        $res['ok'] = !empty($res['accepted']);
        $res['routed_via'] = (string) ($res['provider'] ?? 'heuristic');
        $this->logModule('import_image_gate', 'filter', $res);

        return $res;
    }

    /**
     * Scraper AI — analiză HTML via Metro LLM (fallback heuristic/Cursor legacy în ScraperAiAgent).
     *
     * @param list<string> $fieldsNeeded
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function dispatchScraperAnalyze(
        string $html,
        string $sourceId,
        string $userGoals,
        array $fieldsNeeded = ['title', 'image', 'url', 'price'],
        array $options = [],
    ): array {
        require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperAiAgent.php';
        $options['use_metro_orchestrator'] = true;
        try {
            $res = \ScraperAiAgent::analyze($html, $sourceId, $userGoals, $fieldsNeeded, 3, $options);
            $res['ok'] = !empty($res['ok']);
            $this->logModule('scraper_analyze', 'analyze', $res);

            return $res;
        } catch (\Throwable $e) {
            $err = ['ok' => false, 'error' => $e->getMessage(), 'routed_via' => 'none'];
            $this->logModule('scraper_analyze', 'analyze', $err);

            return $err;
        }
    }

    /** @param array<string, mixed> $result */
    private function logModule(string $moduleId, string $action, array $result): void
    {
        $this->loadOrchestraHelper();
        if (!function_exists('metro_ai_orchestra_log_module')) {
            return;
        }
        metro_ai_orchestra_log_module($moduleId, $action, [
            'routed_via' => (string) ($result['routed_via'] ?? $result['provider'] ?? 'unknown'),
            'ok' => !empty($result['ok']),
            'model' => (string) ($result['model'] ?? ''),
            'error' => empty($result['ok']) ? mb_substr((string) ($result['error'] ?? ''), 0, 120) : '',
        ]);
    }

    private function loadOrchestraHelper(): void
    {
        $path = dirname(__DIR__, 2) . '/system/metro_ai_orchestra.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
}
