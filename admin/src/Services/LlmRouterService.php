<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Metro LLM — Ollama → Groq → Gemini → OpenRouter (free).
 */
final class LlmRouterService
{
    private OllamaLlmClient $ollama;
    private GroqLlmClient $groq;
    private GeminiLlmClient $gemini;
    private OpenRouterLlmClient $openrouter;

    public function __construct(
        private readonly string $projectRoot,
        ?OllamaLlmClient $ollama = null,
        ?GroqLlmClient $groq = null,
        ?GeminiLlmClient $gemini = null,
        ?OpenRouterLlmClient $openrouter = null,
    ) {
        $this->ollama = $ollama ?? new OllamaLlmClient($projectRoot);
        $this->groq = $groq ?? new GroqLlmClient($projectRoot);
        $this->gemini = $gemini ?? new GeminiLlmClient($projectRoot);
        $this->openrouter = $openrouter ?? new OpenRouterLlmClient($projectRoot);
    }

    public static function create(string $projectRoot): self
    {
        return new self($projectRoot);
    }

    public function isConfigured(): bool
    {
        return $this->ollama->readiness()['ready']
            || $this->groq->isConfigured()
            || $this->gemini->isConfigured()
            || $this->openrouter->isConfigured();
    }

    /** @return array<string, mixed> */
    public function readiness(): array
    {
        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        $mode = 'ollama_first';
        if (is_file($helper)) {
            require_once $helper;
            $mode = besoiu_llm_router_mode();
        }

        return [
            'router_mode' => $mode,
            'ollama' => $this->ollama->readiness(),
            'groq' => $this->groq->readiness(),
            'gemini' => $this->gemini->readiness(),
            'openrouter' => $this->openrouter->readiness(),
            'ready' => $this->isConfigured(),
            'primary' => $this->primaryProvider(),
            'fallback_chain' => ['ollama', 'groq', 'gemini', 'openrouter'],
        ];
    }

    public function primaryProvider(): string
    {
        $ollama = $this->ollama->readiness();
        if ($ollama['ready']) {
            return 'ollama';
        }
        if ($this->groq->isConfigured()) {
            return 'groq';
        }
        if ($this->gemini->isConfigured()) {
            return 'gemini';
        }
        if ($this->openrouter->isConfigured()) {
            return 'openrouter';
        }

        return 'none';
    }

    public function model(): string
    {
        $ready = $this->ollama->readiness();
        if (!empty($ready['ready'])) {
            return $this->ollama->model();
        }
        if ($this->groq->isConfigured()) {
            return $this->groq->model();
        }
        if ($this->gemini->isConfigured()) {
            return $this->gemini->model();
        }
        if ($this->openrouter->isConfigured()) {
            return $this->openrouter->model();
        }

        return '';
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,routed_via?:string,usage_tokens?:int}
     */
    public function chat(
        array $messages,
        string $systemPrompt = '',
        float $temperature = 0.7,
        int $timeoutSec = 120,
        string $taskContext = 'default',
    ): array {
        return $this->routeChain(
            $taskContext,
            'chat',
            $messages,
            $systemPrompt,
            null,
            $temperature,
            $timeoutSec
        );
    }

    /**
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,routed_via?:string,usage_tokens?:int}
     */
    public function complete(
        string $systemPrompt,
        string $userPrompt,
        float $temperature = 0.7,
        int $timeoutSec = 120,
        string $taskContext = 'default',
    ): array {
        return $this->routeChain(
            $taskContext,
            'complete',
            [],
            $systemPrompt,
            $userPrompt,
            $temperature,
            $timeoutSec
        );
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array<string, mixed>
     */
    private function routeChain(
        string $taskContext,
        string $usageSource,
        array $messages,
        string $systemPrompt,
        ?string $userPrompt,
        float $temperature,
        int $timeoutSec,
    ): array {
        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        $mode = 'ollama_first';
        $profile = 'hybrid';
        if (is_file($helper)) {
            require_once $helper;
            $mode = besoiu_llm_router_mode();
            $profile = besoiu_llm_task_profile($taskContext);
        }

        $ollamaReady = $this->ollama->readiness()['ready'];
        $tryOllama = $ollamaReady && $profile !== 'cloud';
        $tryFreeCloud = $mode !== 'ollama_only' && $profile !== 'local';

        $errors = [];
        $localTemp = min($temperature, 0.8);
        $localTimeout = min($timeoutSec, 120);

        if ($tryOllama) {
            $res = $userPrompt !== null
                ? $this->ollama->complete($systemPrompt, $userPrompt, $localTemp, $localTimeout)
                : $this->ollama->chat($messages, $systemPrompt, $localTemp, $localTimeout);
            if (!empty($res['ok'])) {
                return $this->finalizeRoute($taskContext, $usageSource, $res, 'ollama', true);
            }
            $errors[] = 'Ollama: ' . (string) ($res['error'] ?? 'eșec');
            if ($mode === 'ollama_only' || $profile === 'local') {
                return $this->finalizeRoute($taskContext, $usageSource, $res, 'ollama_failed', false);
            }
        } elseif ($profile !== 'cloud' && $mode !== 'ollama_only' && $profile !== 'local') {
            $errors[] = 'Ollama: indisponibil';
        }

        if ($tryFreeCloud) {
            $cloudProviders = [
                'groq' => $this->groq,
                'gemini' => $this->gemini,
                'openrouter' => $this->openrouter,
            ];
            foreach ($cloudProviders as $via => $client) {
                if (!$client->isConfigured()) {
                    continue;
                }
                $cloudTimeout = min($timeoutSec, $via === 'groq' ? 45 : 90);
                $res = $userPrompt !== null
                    ? $client->complete($systemPrompt, $userPrompt, $temperature, $cloudTimeout)
                    : $client->chat($messages, $systemPrompt, $temperature, $cloudTimeout);
                if (!empty($res['ok'])) {
                    return $this->finalizeRoute($taskContext, $usageSource, $res, $via, false);
                }
                $errors[] = ucfirst($via) . ': ' . (string) ($res['error'] ?? 'eșec');
            }
        }

        if ($tryOllama && !$ollamaReady) {
            return [
                'ok' => false,
                'error' => 'Ollama nu e gata — verifică ollama serve și ollama pull ' . $this->ollama->model(),
                'routed_via' => 'none',
            ];
        }

        return [
            'ok' => false,
            'error' => $errors !== []
                ? implode(' | ', $errors)
                : ('Niciun provider LLM disponibil pentru ' . $taskContext . '.'),
            'routed_via' => 'none',
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function finalizeRoute(string $taskContext, string $usageSource, array $result, string $via, bool $logOllama): array
    {
        $result = array_merge($result, ['routed_via' => $via]);
        if ($logOllama) {
            $this->logOllamaUsage($usageSource, $result);
        }
        $this->logRoute($taskContext, $usageSource, $result);

        return $result;
    }

    /** @param array<string, mixed> $result */
    private function logOllamaUsage(string $source, array $result): void
    {
        $budgetFile = rtrim($this->projectRoot, '/\\') . '/admin/system/api_token_budget.php';
        if (!is_file($budgetFile)) {
            return;
        }
        require_once $budgetFile;
        if (!function_exists('api_token_budget_log_llm_call')) {
            return;
        }
        try {
            $tokens = max(1, (int) ($result['usage_tokens'] ?? 1));
            $model = (string) ($result['model'] ?? $this->ollama->model());
            api_token_budget_log_llm_call('ollama', $model, $source, $tokens);
        } catch (\Throwable) {
            // jurnal opțional
        }
    }

    /** @param array<string, mixed> $result */
    private function logRoute(string $taskContext, string $usageSource, array $result): void
    {
        $helper = dirname(__DIR__, 2) . '/system/metro_llm_hub.php';
        if (!is_file($helper)) {
            return;
        }
        require_once $helper;
        if (!function_exists('metro_llm_route_log_append')) {
            return;
        }
        metro_llm_route_log_append([
            'task' => $taskContext,
            'action' => $usageSource,
            'routed_via' => (string) ($result['routed_via'] ?? ($result['provider'] ?? 'unknown')),
            'ok' => !empty($result['ok']),
            'model' => (string) ($result['model'] ?? ''),
            'error' => empty($result['ok']) ? mb_substr((string) ($result['error'] ?? ''), 0, 120) : '',
            'source' => 'router',
        ]);
    }
}
