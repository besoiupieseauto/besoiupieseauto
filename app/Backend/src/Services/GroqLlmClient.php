<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Groq Cloud — tier gratuit, fallback rapid după Ollama.
 */
final class GroqLlmClient
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function isConfigured(): bool
    {
        LlmClientSupport::loadEnv($this->projectRoot);

        return $this->apiKey() !== '';
    }

    /** @return array{configured:bool,model:string,ready:bool} */
    public function readiness(): array
    {
        $configured = $this->isConfigured();

        return [
            'configured' => $configured,
            'model' => $this->model(),
            'ready' => $configured,
        ];
    }

    public function model(): string
    {
        LlmClientSupport::loadEnv($this->projectRoot);
        require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperLlmConfig.php';

        return \ScraperLlmConfig::groqModel();
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int}
     */
    public function chat(array $messages, string $systemPrompt = '', float $temperature = 0.7, int $timeoutSec = 60): array
    {
        $blocked = LlmClientSupport::guardBlockMessage($this->projectRoot, 'Groq', 'groq');
        if ($blocked !== null) {
            return ['ok' => false, 'error' => $blocked];
        }

        $key = $this->apiKey();
        if ($key === '') {
            return ['ok' => false, 'error' => 'GROQ_KEY lipsește în admin/.env'];
        }

        $model = $this->model();
        $payload = json_encode([
            'model' => $model,
            'messages' => LlmClientSupport::messagesWithSystem($messages, $systemPrompt),
            'temperature' => max(0.0, min(1.5, $temperature)),
            'max_tokens' => 1024,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return ['ok' => false, 'error' => 'Payload Groq invalid.'];
        }

        $http = LlmClientSupport::postJson(
            'https://api.groq.com/openai/v1/chat/completions',
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            $payload,
            $timeoutSec
        );

        if (empty($http['ok'])) {
            return $this->parseHttpError($http, 'Groq');
        }

        $json = json_decode((string) ($http['body'] ?? ''), true);
        $content = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            return ['ok' => false, 'error' => 'Răspuns gol de la Groq.'];
        }

        $tokens = isset($json['usage']['total_tokens']) ? max(1, (int) $json['usage']['total_tokens']) : null;
        LlmClientSupport::logUsage($this->projectRoot, 'groq', $model, 'llm-router', $tokens);

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'groq',
            'model' => $model,
            'usage_tokens' => $tokens ?? 1,
        ];
    }

    /** @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int} */
    public function complete(string $systemPrompt, string $userPrompt, float $temperature = 0.7, int $timeoutSec = 60): array
    {
        return $this->chat(
            [['role' => 'user', 'content' => $userPrompt]],
            $systemPrompt,
            $temperature,
            $timeoutSec
        );
    }

    private function apiKey(): string
    {
        require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperLlmConfig.php';

        return \ScraperLlmConfig::groqKey();
    }

    /** @param array<string, mixed> $http */
    private function parseHttpError(array $http, string $label): array
    {
        $body = (string) ($http['body'] ?? '');
        $httpCode = (int) ($http['http'] ?? 0);
        $errPath = rtrim($this->projectRoot, '/\\') . '/system/ai_api_errors.php';
        if (is_file($errPath)) {
            require_once $errPath;
            $parsed = ai_api_parse_response_error($body, $httpCode);

            return ['ok' => false, 'error' => (string) ($parsed['message'] ?? ($label . ' HTTP ' . $httpCode))];
        }

        return ['ok' => false, 'error' => $label . ' HTTP ' . $httpCode];
    }
}
