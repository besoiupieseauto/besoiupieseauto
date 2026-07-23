<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\OllamaLlmClient;
use Throwable;

/**
 * Orchestrator AI — propune, loghează, validează; nicio scriere DB producție fără aprobare.
 */
final class AiGovernanceService
{
    public function __construct(
        private readonly string $projectRoot = '',
        private readonly ?AiRagConfigService $config = null,
        private readonly ?AiInteractionLogService $log = null,
        private readonly ?AiStructuredOutputValidator $validator = null,
        private readonly ?OllamaLlmClient $ollama = null,
    ) {
    }

    private function root(): string
    {
        return $this->projectRoot !== ''
            ? $this->projectRoot
            : (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    /**
     * @param array<string, mixed> $inputPayload
     * @param array<string, mixed> $options format=json, timeout, user_id
     * @return array<string, mixed>
     */
    public function propose(
        string $moduleId,
        string $systemPrompt,
        string $userMessage,
        array $inputPayload = [],
        array $options = [],
    ): array {
        $started = hrtime(true);
        $configSvc = $this->config ?? new AiRagConfigService($this->root());
        $runtime = $configSvc->moduleRuntime($moduleId);
        $meta = AiRagModuleRegistry::get($moduleId);

        if ($meta === null) {
            return ['ok' => false, 'error' => 'Modul necunoscut'];
        }
        if ($runtime === null || empty($runtime['enabled'])) {
            return ['ok' => false, 'error' => 'Modul dezactivat în panou'];
        }

        $ollama = $this->ollama ?? new OllamaLlmClient($this->root() . '/app');
        if (!$ollama->isEnabled()) {
            return ['ok' => false, 'error' => 'Ollama dezactivat (OLLAMA_ENABLED=0)'];
        }

        $model = (string) ($options['model'] ?? $runtime['model']);
        $timeout = max(30, (int) ($options['timeout'] ?? 90));
        $temperature = (float) ($options['temperature'] ?? 0.1);

        try {
            $result = $ollama->complete($systemPrompt, $userMessage, $temperature, $timeout, $model);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'error' => $e->getMessage(), 'content' => ''];
        }

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        $raw = (string) ($result['content'] ?? '');
        $parsed = ['ok' => false, 'data' => [], 'raw' => $raw, 'error' => ''];
        $schemaValid = ['ok' => true, 'errors' => []];
        $confidence = null;

        if (!empty($result['ok']) && $raw !== '') {
            $validator = $this->validator ?? new AiStructuredOutputValidator();
            $parsed = $validator->parseJsonFromLlm($raw);
            if ($parsed['ok']) {
                $schemaValid = $validator->validate($parsed['data'], (array) ($meta['output_schema'] ?? []));
                $confidence = isset($parsed['data']['confidence'])
                    ? (float) $parsed['data']['confidence']
                    : (isset($parsed['data']['scor_incredere']) ? (float) $parsed['data']['scor_incredere'] : null);
            }
        }

        $status = 'failed';
        if (!empty($result['ok'])) {
            $status = ($parsed['ok'] && $schemaValid['ok']) ? 'proposed' : 'schema_invalid';
        }

        $logSvc = $this->log ?? new AiInteractionLogService(null, $this->root());
        $logId = $logSvc->append([
            'module_id' => $moduleId,
            'action' => (string) ($options['action'] ?? 'propose'),
            'input_summary' => mb_substr($userMessage, 0, 200),
            'input_json' => $inputPayload,
            'prompt_text' => $systemPrompt,
            'output_raw' => $raw,
            'output_json' => $parsed['ok'] ? $parsed['data'] : null,
            'model' => $model,
            'confidence' => $confidence,
            'latency_ms' => $latency,
            'status' => $status,
            'user_id' => isset($options['user_id']) ? (int) $options['user_id'] : null,
        ]);

        return [
            'ok' => !empty($result['ok']) && $parsed['ok'] && $schemaValid['ok'],
            'log_id' => $logId,
            'module_id' => $moduleId,
            'requires_approval' => (bool) ($meta['requires_approval'] ?? true),
            'auto_apply' => false,
            'provider' => (string) ($result['provider'] ?? 'ollama'),
            'model' => $model,
            'latency_ms' => $latency,
            'confidence' => $confidence,
            'structured' => $parsed['ok'] ? $parsed['data'] : null,
            'raw' => $raw,
            'schema_errors' => $schemaValid['errors'] ?? [],
            'error' => (string) ($result['error'] ?? $parsed['error'] ?? ''),
            'status' => $status,
        ];
    }
}
