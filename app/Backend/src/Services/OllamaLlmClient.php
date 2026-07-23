<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Throwable;

/**
 * LLM local prin Ollama (localhost) — 0 tokeni cloud.
 */
final class OllamaLlmClient
{
    /** @var array<string, mixed> */
    private array $callContext = [];

    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function pushCallContext(array $context): void
    {
        $this->callContext = array_merge($this->callContext, $context);
    }

    public function clearCallContext(): void
    {
        $this->callContext = [];
    }

    public function isEnabled(): bool
    {
        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        if (!is_file($helper)) {
            return false;
        }
        require_once $helper;

        return besoiu_ollama_enabled();
    }

    public function baseUrl(): string
    {
        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        if (is_file($helper)) {
            require_once $helper;

            return besoiu_ollama_base_url();
        }

        return 'http://127.0.0.1:11434';
    }

    public function model(): string
    {
        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        if (is_file($helper)) {
            require_once $helper;

            return besoiu_ollama_model();
        }

        return 'qwen2.5:7b';
    }

    public function visionModel(): string
    {
        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        if (is_file($helper)) {
            require_once $helper;

            return besoiu_ollama_vision_model();
        }

        return 'llava:7b';
    }

    public function modelForAgent(string $agentSlug): string
    {
        try {
            $besoiu = new BesoiuOllamaModelService($this->projectRoot);
            $custom = $besoiu->resolveAgentModel($agentSlug);
            if ($custom !== null) {
                return $custom;
            }
        } catch (Throwable) {
            // fallback env
        }

        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        if (is_file($helper)) {
            require_once $helper;

            return besoiu_ollama_agent_model($agentSlug);
        }

        return $this->model();
    }

    /** @return list<string> */
    public function listInstalledModels(): array
    {
        $tags = $this->fetchTags();
        if ($tags === null) {
            return [];
        }

        $models = is_array($tags['models'] ?? null) ? $tags['models'] : [];
        $out = [];
        foreach ($models as $row) {
            if (is_array($row) && isset($row['name'])) {
                $out[] = (string) $row['name'];
            }
        }

        return $out;
    }

    public function isModelInstalled(string $model): bool
    {
        $tags = $this->fetchTags();
        if ($tags === null) {
            return false;
        }

        return $this->modelIsInstalled($tags, $model);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled();
    }

    /** @return array{enabled:bool,reachable:bool,model:string,model_installed:bool,ready:bool,base_url:string} */
    public function readiness(): array
    {
        $enabled = $this->isEnabled();
        $reachable = false;
        $modelInstalled = false;
        if ($enabled) {
            $tags = $this->fetchTags();
            $reachable = $tags !== null;
            if ($reachable) {
                $modelInstalled = $this->modelIsInstalled($tags, $this->model());
            }
        }

        return [
            'enabled' => $enabled,
            'reachable' => $reachable,
            'model' => $this->model(),
            'model_installed' => $modelInstalled,
            'ready' => $enabled && $reachable && $modelInstalled,
            'base_url' => $this->baseUrl(),
        ];
    }

    /** @return array{enabled:bool,reachable:bool,model:string,model_installed:bool,ready:bool,base_url:string,error?:string} */
    public function visionReadiness(): array
    {
        $enabled = $this->isEnabled();
        $reachable = false;
        $modelInstalled = false;
        $model = $this->visionModel();
        if ($enabled) {
            $tags = $this->fetchTags();
            $reachable = $tags !== null;
            if ($reachable) {
                $modelInstalled = $this->modelIsInstalled($tags, $model);
            }
        }

        $ready = $enabled && $reachable && $modelInstalled;
        $out = [
            'enabled' => $enabled,
            'reachable' => $reachable,
            'model' => $model,
            'model_installed' => $modelInstalled,
            'ready' => $ready,
            'base_url' => $this->baseUrl(),
        ];
        if (!$ready) {
            if (!$enabled) {
                $out['error'] = 'OLLAMA_ENABLED=0';
            } elseif (!$reachable) {
                $out['error'] = 'Ollama indisponibil la ' . $this->baseUrl();
            } elseif (!$modelInstalled) {
                $out['error'] = 'Model vision lipsă — rulează: ollama pull ' . $model;
            }
        }

        return $out;
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int}
     */
    public function chat(array $messages, string $systemPrompt = '', float $temperature = 0.4, int $timeoutSec = 90, ?string $model = null): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'OLLAMA_ENABLED=0 — LLM local dezactivat.'];
        }

        $ollamaMessages = [];
        if (trim($systemPrompt) !== '') {
            $ollamaMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $role = (string) ($msg['role'] ?? 'user');
            if (!in_array($role, ['system', 'user', 'assistant'], true)) {
                $role = 'user';
            }
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $ollamaMessages[] = ['role' => $role, 'content' => $content];
        }

        if ($ollamaMessages === []) {
            return ['ok' => false, 'error' => 'Mesaje goale pentru Ollama.'];
        }

        $payload = [
            'model' => $model ?? $this->model(),
            'messages' => $ollamaMessages,
            'stream' => false,
            'options' => [
                'temperature' => max(0.0, min(1.5, $temperature)),
            ],
        ];

        $source = (string) ($this->callContext['source'] ?? 'erp.chat');
        $inputExcerpt = $this->lastUserExcerpt($messages);

        $started = hrtime(true);
        $response = $this->postJson('/api/chat', $payload, $timeoutSec);
        $latencyMs = (int) round((hrtime(true) - $started) / 1_000_000);
        if ($response === null) {
            $this->logCall($source, false, 'Ollama indisponibil', $model ?? $this->model(), $latencyMs, [
                'input_excerpt' => $inputExcerpt,
            ]);

            return [
                'ok' => false,
                'error' => 'Ollama indisponibil la ' . $this->baseUrl() . ' — rulează: ollama serve',
            ];
        }

        if (!empty($response['error'])) {
            $err = (string) $response['error'];
            $this->logCall($source, false, $err, $model ?? $this->model(), $latencyMs, [
                'input_excerpt' => $inputExcerpt,
            ]);

            return [
                'ok' => false,
                'error' => $err,
            ];
        }

        $content = trim((string) ($response['message']['content'] ?? ''));
        if ($content === '') {
            $this->logCall($source, false, 'Răspuns gol', $model ?? $this->model(), $latencyMs, [
                'input_excerpt' => $inputExcerpt,
            ]);

            return ['ok' => false, 'error' => 'Răspuns gol de la Ollama.'];
        }

        $evalCount = (int) ($response['eval_count'] ?? 0);
        $promptCount = (int) ($response['prompt_eval_count'] ?? 0);
        $usedModel = (string) ($response['model'] ?? ($model ?? $this->model()));
        $this->logCall($source, true, '', $usedModel, $latencyMs, [
            'input_excerpt' => $inputExcerpt,
            'output_excerpt' => mb_substr($content, 0, 240),
        ]);

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'ollama',
            'model' => $usedModel,
            'usage_tokens' => max(1, $evalCount + $promptCount),
        ];
    }

    /**
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int}
     */
    public function complete(string $systemPrompt, string $userPrompt, float $temperature = 0.4, int $timeoutSec = 90, ?string $model = null): array
    {
        return $this->chat(
            [['role' => 'user', 'content' => $userPrompt]],
            $systemPrompt,
            $temperature,
            $timeoutSec,
            $model
        );
    }

    /**
     * Chat vision Ollama (llava / moondream) — imagini base64 raw.
     *
     * @param list<string> $imagesBase64 fără prefix data:image
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int}
     */
    public function visionChat(string $prompt, array $imagesBase64, string $systemPrompt = '', ?string $model = null, float $temperature = 0.2, int $timeoutSec = 120): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'OLLAMA_ENABLED=0 — LLM local dezactivat.'];
        }

        $images = [];
        foreach ($imagesBase64 as $img) {
            $img = trim((string) $img);
            if ($img === '') {
                continue;
            }
            if (str_starts_with($img, 'data:')) {
                $parts = explode(',', $img, 2);
                $img = $parts[1] ?? $img;
            }
            $images[] = $img;
        }

        if ($images === []) {
            return ['ok' => false, 'error' => 'Nicio imagine pentru Ollama vision.'];
        }

        $userContent = trim($prompt);
        if ($systemPrompt !== '') {
            $userContent = trim($systemPrompt) . "\n\n" . $userContent;
        }

        $payload = [
            'model' => $model ?? $this->visionModel(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userContent,
                    'images' => $images,
                ],
            ],
            'stream' => false,
            'options' => [
                'temperature' => max(0.0, min(1.0, $temperature)),
            ],
        ];

        $source = (string) ($this->callContext['source'] ?? 'erp.vision');
        $inputExcerpt = mb_substr(trim($prompt), 0, 180);
        if ($images !== []) {
            $inputExcerpt .= ($inputExcerpt !== '' ? ' · ' : '') . count($images) . ' imagini';
        }

        $started = hrtime(true);
        $response = $this->postJson('/api/chat', $payload, $timeoutSec);
        $latencyMs = (int) round((hrtime(true) - $started) / 1_000_000);
        $visionModel = $model ?? $this->visionModel();
        if ($response === null) {
            $this->logCall($source, false, 'Ollama vision indisponibil', $visionModel, $latencyMs, [
                'input_excerpt' => $inputExcerpt,
            ]);

            return [
                'ok' => false,
                'error' => 'Ollama vision indisponibil — rulează: ollama pull ' . $visionModel,
            ];
        }

        if (!empty($response['error'])) {
            $err = (string) $response['error'];
            $this->logCall($source, false, $err, $visionModel, $latencyMs, [
                'input_excerpt' => $inputExcerpt,
            ]);

            return ['ok' => false, 'error' => $err];
        }

        $content = trim((string) ($response['message']['content'] ?? ''));
        if ($content === '') {
            $this->logCall($source, false, 'Răspuns gol', $visionModel, $latencyMs, [
                'input_excerpt' => $inputExcerpt,
            ]);

            return ['ok' => false, 'error' => 'Răspuns gol de la Ollama vision.'];
        }

        $evalCount = (int) ($response['eval_count'] ?? 0);
        $promptCount = (int) ($response['prompt_eval_count'] ?? 0);
        $usedModel = (string) ($response['model'] ?? $visionModel);
        $this->logCall($source, true, '', $usedModel, $latencyMs, [
            'input_excerpt' => $inputExcerpt,
            'output_excerpt' => mb_substr($content, 0, 240),
        ]);

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'ollama',
            'model' => $usedModel,
            'usage_tokens' => max(1, $evalCount + $promptCount),
        ];
    }

    /** @return array<string, mixed>|null */
    private function fetchTags(): ?array
    {
        $url = $this->baseUrl() . '/api/tags';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $tags */
    private function modelIsInstalled(array $tags, string $model): bool
    {
        $models = is_array($tags['models'] ?? null) ? $tags['models'] : [];
        $needle = strtolower($model);
        foreach ($models as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = strtolower((string) ($row['name'] ?? ''));
            if ($name === $needle || str_starts_with($name, $needle . ':')) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|null */
    private function postJson(string $path, array $payload, int $timeoutSec): ?array
    {
        $url = $this->baseUrl() . $path;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['error' => 'Payload JSON invalid.'];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => max(5, $timeoutSec),
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['error' => 'Răspuns Ollama invalid.'];
        }

        return $decoded;
    }

    /** @param list<array{role:string,content:string}> $messages */
    private function lastUserExcerpt(array $messages, int $max = 220): string
    {
        for ($i = count($messages) - 1; $i >= 0; --$i) {
            if (!is_array($messages[$i])) {
                continue;
            }
            if ((string) ($messages[$i]['role'] ?? '') !== 'user') {
                continue;
            }
            $text = trim((string) ($messages[$i]['content'] ?? ''));

            return $text === '' ? '' : mb_substr($text, 0, $max);
        }

        return '';
    }

    private function requestPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '') {
            return '';
        }
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) ? $path : $uri;
    }

    /** @param array<string, mixed> $extra */
    private function logCall(string $source, bool $ok, string $error, string $model, int $latencyMs, array $extra = []): void
    {
        $helper = dirname(__DIR__, 2) . '/system/ollama_error_log.php';
        if (!is_file($helper)) {
            return;
        }
        require_once $helper;

        ollama_telemetry_record([
            'module' => 'erp',
            'url' => $this->baseUrl(),
            'model' => $model,
            'vision_model' => $this->visionModel(),
            'ok' => $ok,
            'latency_ms' => $latencyMs,
            'error' => $error,
        ], $this->projectRoot);

        $section = (string) ($this->callContext['section'] ?? '');
        if ($section === '') {
            $section = str_contains($source, '.') ? explode('.', $source, 2)[0] : 'erp';
        }
        $taskLabel = (string) ($this->callContext['task_label'] ?? '');
        $inputExcerpt = (string) ($extra['input_excerpt'] ?? $this->callContext['input_excerpt'] ?? '');
        $outputExcerpt = (string) ($extra['output_excerpt'] ?? '');
        $path = (string) ($this->callContext['path'] ?? $this->requestPath());
        $agent = (string) ($this->callContext['agent'] ?? '');

        if ($ok && $inputExcerpt !== '') {
            $summary = $inputExcerpt;
            if ($outputExcerpt !== '') {
                $summary .= ' → ' . mb_substr($outputExcerpt, 0, 160);
            }
        } elseif ($ok) {
            $summary = ($taskLabel !== '' ? $taskLabel . ' · ' : '') . 'OK · ' . $model;
        } else {
            $summary = 'Eșec Ollama: ' . mb_substr($error, 0, 180);
        }

        ollama_work_log_append([
            'source' => $source,
            'section' => $section,
            'action' => $taskLabel !== '' ? $taskLabel : $source,
            'ok' => $ok,
            'error' => $error,
            'model' => $model,
            'url' => $this->baseUrl(),
            'latency_ms' => $latencyMs,
            'ollama_actuated' => true,
            'at' => date('c'),
            'summary' => $summary,
            'status' => $ok ? 'ok' : 'fail',
            'meta' => [
                'path' => $path,
                'input_excerpt' => $inputExcerpt,
                'output_excerpt' => $outputExcerpt,
                'task_label' => $taskLabel,
                'agent' => $agent,
            ],
        ], $this->projectRoot);

        if (!$ok && $error !== '') {
            ollama_error_log_append([
                'source' => $source,
                'ok' => false,
                'error' => $error,
                'model' => $model,
                'url' => $this->baseUrl(),
                'latency_ms' => $latencyMs,
            ], $this->projectRoot);
        }
    }
}
