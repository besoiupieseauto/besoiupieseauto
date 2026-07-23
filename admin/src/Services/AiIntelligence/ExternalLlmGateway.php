<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Besoiu\Services\LlmClientSupport;
use RuntimeException;

/**
 * Gateway API extern — DOAR pentru task-uri complexe (Pas 6).
 *
 * Necesită EXTERNAL_LLM_API_KEY explicit configurat.
 */
final class ExternalLlmGateway
{
    private string $root;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        LlmClientSupport::loadEnv($this->root);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /** @return array<string, mixed> */
    public function readiness(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'provider' => $this->provider(),
            'model' => $this->model(),
            'base_url' => $this->baseUrl(),
            'note' => $this->isConfigured()
                ? 'Task-uri complexe disponibile.'
                : 'Setează EXTERNAL_LLM_API_KEY în .env pentru seo_description / price_competition / scraped_synthesis.',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string}
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'error' => 'EXTERNAL_LLM_API_KEY lipsește — task complex indisponibil. Configurează cheia în .env.',
                'provider' => 'none',
            ];
        }

        $provider = $this->provider();
        $model = (string) ($options['model'] ?? $this->model());
        $temperature = (float) ($options['temperature'] ?? 0.4);
        $timeout = (int) ($options['timeout_sec'] ?? 120);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => max(0.0, min(1.2, $temperature)),
            'max_tokens' => (int) ($options['max_tokens'] ?? 1800),
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return ['ok' => false, 'error' => 'Payload extern invalid.', 'provider' => $provider];
        }

        [$url, $headers] = $this->requestConfig($provider);
        $http = LlmClientSupport::postJson($url, $headers, $payload, $timeout);
        if (empty($http['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($http['error'] ?? 'Eroare API extern'),
                'provider' => $provider,
            ];
        }

        $json = json_decode((string) ($http['body'] ?? ''), true);
        $content = '';
        if (is_array($json)) {
            $content = (string) ($json['choices'][0]['message']['content'] ?? $json['output'] ?? '');
        }
        if ($content === '') {
            return ['ok' => false, 'error' => 'Răspuns gol de la API extern.', 'provider' => $provider];
        }

        return [
            'ok' => true,
            'content' => $content,
            'provider' => $provider,
            'model' => $model,
        ];
    }

    private function apiKey(): string
    {
        return trim((string) ($_ENV['EXTERNAL_LLM_API_KEY'] ?? getenv('EXTERNAL_LLM_API_KEY') ?: ''));
    }

    private function provider(): string
    {
        $p = strtolower(trim((string) ($_ENV['EXTERNAL_LLM_PROVIDER'] ?? getenv('EXTERNAL_LLM_PROVIDER') ?: 'openrouter')));

        return in_array($p, ['openrouter', 'groq', 'openai', 'grok'], true) ? $p : 'openrouter';
    }

    private function model(): string
    {
        $model = trim((string) ($_ENV['EXTERNAL_LLM_MODEL'] ?? getenv('EXTERNAL_LLM_MODEL') ?: ''));
        if ($model !== '') {
            return $model;
        }

        return match ($this->provider()) {
            'groq' => trim((string) ($_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?: 'llama-3.3-70b-versatile')),
            'openai' => trim((string) ($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: 'gpt-4o-mini')),
            'grok' => trim((string) ($_ENV['GROK_MODEL'] ?? getenv('GROK_MODEL') ?: 'grok-2-latest')),
            default => trim((string) ($_ENV['OPENROUTER_MODEL'] ?? getenv('OPENROUTER_MODEL') ?: 'google/gemma-2-9b-it:free')),
        };
    }

    private function baseUrl(): string
    {
        $custom = trim((string) ($_ENV['EXTERNAL_LLM_BASE_URL'] ?? getenv('EXTERNAL_LLM_BASE_URL') ?: ''));
        if ($custom !== '') {
            return rtrim($custom, '/');
        }

        return match ($this->provider()) {
            'groq' => 'https://api.groq.com/openai/v1',
            'openai' => 'https://api.openai.com/v1',
            'grok' => 'https://api.x.ai/v1',
            default => 'https://openrouter.ai/api/v1',
        };
    }

    /** @return array{0:string,1:list<string>} */
    private function requestConfig(string $provider): array
    {
        $key = $this->apiKey();
        $base = $this->baseUrl();

        return match ($provider) {
            'groq' => [
                $base . '/chat/completions',
                ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            ],
            'openai' => [
                $base . '/chat/completions',
                ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            ],
            'grok' => [
                $base . '/chat/completions',
                ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            ],
            default => [
                $base . '/chat/completions',
                [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                    'HTTP-Referer: https://besoiupieseauto.ro',
                    'X-Title: Besoiu AI Intelligence',
                ],
            ],
        };
    }
}
