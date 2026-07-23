<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * OpenRouter — modele :free cu fallback automat când un slug nu mai e disponibil.
 */
final class OpenRouterLlmClient
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

        return \ScraperLlmConfig::openrouterModel();
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool,content?:string,error?:string,provider?:string,model?:string,usage_tokens?:int,tried_models?:list<string>}
     */
    public function chat(array $messages, string $systemPrompt = '', float $temperature = 0.7, int $timeoutSec = 90): array
    {
        $blocked = LlmClientSupport::guardBlockMessage($this->projectRoot, 'OpenRouter', 'openrouter');
        if ($blocked !== null) {
            return ['ok' => false, 'error' => $blocked];
        }

        $key = $this->apiKey();
        if ($key === '') {
            return ['ok' => false, 'error' => 'OPENROUTER_API_KEY lipsește în admin/.env'];
        }

        require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperLlmConfig.php';
        $candidates = \ScraperLlmConfig::openrouterModelCandidates();
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

        $last = $errors !== [] ? end($errors) : 'OpenRouter eșuat.';
        $msg = is_string($last) ? $last : 'OpenRouter eșuat.';
        if (str_contains($msg, ': ')) {
            $msg = substr($msg, (int) strpos($msg, ': ') + 2);
        }

        return [
            'ok' => false,
            'error' => LlmClientSupport::shortenLlmError($msg, 'OpenRouter'),
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
        $payload = json_encode([
            'model' => $model,
            'messages' => LlmClientSupport::messagesWithSystem($messages, $systemPrompt),
            'temperature' => max(0.0, min(1.5, $temperature)),
            'max_tokens' => 256,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return ['ok' => false, 'error' => 'Payload OpenRouter invalid.'];
        }

        $http = LlmClientSupport::postJson(
            'https://openrouter.ai/api/v1/chat/completions',
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
                'HTTP-Referer: https://besoiupieseauto.ro',
                'X-Title: Besoiu Metro LLM',
            ],
            $payload,
            $timeoutSec
        );

        if (empty($http['ok'])) {
            return $this->parseHttpError($http, 'OpenRouter');
        }

        $json = json_decode((string) ($http['body'] ?? ''), true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Răspuns JSON invalid de la OpenRouter.'];
        }

        $apiErr = trim((string) ($json['error']['message'] ?? ''));
        if ($apiErr !== '') {
            $meta = (string) ($json['error']['metadata']['raw'] ?? '');

            return ['ok' => false, 'error' => $meta !== '' ? $meta : $apiErr];
        }

        $content = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            return ['ok' => false, 'error' => 'Răspuns gol de la OpenRouter.'];
        }

        $tokens = isset($json['usage']['total_tokens']) ? max(1, (int) $json['usage']['total_tokens']) : null;
        LlmClientSupport::logUsage($this->projectRoot, 'openai', $model, 'openrouter', $tokens);

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'openrouter',
            'model' => $model,
            'usage_tokens' => $tokens ?? 1,
        ];
    }

    private function apiKey(): string
    {
        require_once dirname(__DIR__, 3) . '/lib/Scraper/ScraperLlmConfig.php';

        return \ScraperLlmConfig::openrouterKey();
    }

    /** @param array<string, mixed> $http */
    private function parseHttpError(array $http, string $label): array
    {
        $body = (string) ($http['body'] ?? '');
        $httpCode = (int) ($http['http'] ?? 0);
        $json = json_decode($body, true);
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? $json['message'] ?? '') : '';
        if ($msg !== '' && is_array($json) && !empty($json['error']['metadata']['raw'])) {
            $msg = (string) $json['error']['metadata']['raw'];
        }

        return ['ok' => false, 'error' => $msg !== '' ? $msg : ($label . ' HTTP ' . $httpCode)];
    }
}
