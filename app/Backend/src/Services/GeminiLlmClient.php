<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Google Gemini — tier gratuit, context mare, fallback între modele Flash.
 */
final class GeminiLlmClient
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

        return \ScraperLlmConfig::geminiModel();
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int,tried_models?:list<string>}
     */
    public function chat(array $messages, string $systemPrompt = '', float $temperature = 0.7, int $timeoutSec = 90): array
    {
        $blocked = LlmClientSupport::guardBlockMessage($this->projectRoot, 'Gemini', 'gemini');
        if ($blocked !== null) {
            return ['ok' => false, 'error' => $blocked];
        }

        $key = $this->apiKey();
        if ($key === '') {
            return ['ok' => false, 'error' => 'GEMINI_KEY lipsește în admin/.env'];
        }

        require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperLlmConfig.php';
        $candidates = \ScraperLlmConfig::geminiModelCandidates();
        $errors = [];
        $tried = [];

        foreach ($candidates as $model) {
            $tried[] = $model;
            $res = $this->chatWithModel($key, $model, $messages, $systemPrompt, $temperature, $timeoutSec);
            if (!empty($res['ok'])) {
                $res['tried_models'] = $tried;

                return $res;
            }
            $err = (string) ($res['error'] ?? 'eșec');
            $errors[] = $model . ': ' . $err;
            if (!LlmClientSupport::isRetryableCloudLlmError($err)) {
                break;
            }
        }

        $last = $errors !== [] ? end($errors) : 'Gemini eșuat.';
        $msg = is_string($last) ? $last : 'Gemini eșuat.';
        if (str_contains($msg, ': ')) {
            $msg = substr($msg, (int) strpos($msg, ': ') + 2);
        }

        return [
            'ok' => false,
            'error' => LlmClientSupport::shortenLlmError($msg, 'Gemini'),
            'tried_models' => $tried,
        ];
    }

    /** @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int} */
    public function complete(string $systemPrompt, string $userPrompt, float $temperature = 0.7, int $timeoutSec = 90): array
    {
        return $this->chat(
            [['role' => 'user', 'content' => $userPrompt]],
            $systemPrompt,
            $temperature,
            $timeoutSec
        );
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int}
     */
    private function chatWithModel(
        string $key,
        string $model,
        array $messages,
        string $systemPrompt,
        float $temperature,
        int $timeoutSec,
    ): array {
        $parts = [];
        if (trim($systemPrompt) !== '') {
            $parts[] = ['text' => 'System: ' . $systemPrompt];
        }
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $role = (string) ($msg['role'] ?? 'user');
            $prefix = $role === 'assistant' ? 'Assistant' : 'User';
            $parts[] = ['text' => $prefix . ': ' . $content];
        }
        if ($parts === []) {
            return ['ok' => false, 'error' => 'Mesaje goale pentru Gemini.'];
        }

        $payload = json_encode([
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'temperature' => max(0.0, min(1.5, $temperature)),
                'maxOutputTokens' => 256,
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return ['ok' => false, 'error' => 'Payload Gemini invalid.'];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key=' . rawurlencode($key);

        $http = LlmClientSupport::postJson(
            $url,
            ['Content-Type: application/json'],
            $payload,
            $timeoutSec
        );

        if (empty($http['ok'])) {
            return $this->parseHttpError($http, 'Gemini');
        }

        $json = json_decode((string) ($http['body'] ?? ''), true);
        $content = trim((string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($content === '') {
            $block = (string) ($json['promptFeedback']['blockReason'] ?? '');

            return ['ok' => false, 'error' => $block !== '' ? ('Gemini blocat: ' . $block) : 'Răspuns gol de la Gemini.'];
        }

        $tokens = null;
        if (isset($json['usageMetadata']['totalTokenCount'])) {
            $tokens = max(1, (int) $json['usageMetadata']['totalTokenCount']);
        }
        LlmClientSupport::logUsage($this->projectRoot, 'gemini', $model, 'llm-router', $tokens);

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'gemini',
            'model' => $model,
            'usage_tokens' => $tokens ?? 1,
        ];
    }

    private function apiKey(): string
    {
        require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperLlmConfig.php';

        return \ScraperLlmConfig::geminiKey();
    }

    /** @param array<string, mixed> $http */
    private function parseHttpError(array $http, string $label): array
    {
        $body = (string) ($http['body'] ?? '');
        $httpCode = (int) ($http['http'] ?? 0);
        $json = json_decode($body, true);
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';

        return ['ok' => false, 'error' => $msg !== '' ? $msg : ($label . ' HTTP ' . $httpCode)];
    }
}
