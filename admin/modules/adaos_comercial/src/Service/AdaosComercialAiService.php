<?php
declare(strict_types=1);

namespace Besoiu\Modules\AdaosComercial\Service;

use Besoiu\Services\ModuleOllamaSupport;

/**
 * Exemplu serviciu AI pentru modul — folosește MetroAiOrchestrator / Ollama unificat.
 */
final class AdaosComercialAiService
{
    private ModuleOllamaSupport $ollama;

    public function __construct(?ModuleOllamaSupport $ollama = null)
    {
        $this->ollama = $ollama ?? ModuleOllamaSupport::create();
    }

    /** @return array<string, mixed> */
    public function ollamaStatus(): array
    {
        return $this->ollama->readiness();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function complete(array $payload): array
    {
        $prompt = trim((string) ($payload['prompt'] ?? ''));
        if ($prompt === '') {
            return ['ok' => false, 'error' => 'Lipsește prompt.'];
        }

        $system = trim((string) ($payload['system'] ?? ''));
        $task = trim((string) ($payload['task'] ?? 'adaos_comercial'));

        $res = $this->ollama->complete('adaos_comercial', $prompt, $system, $task);

        return [
            'ok' => !empty($res['ok']) || !empty($res['content']),
            'content' => (string) ($res['content'] ?? $res['text'] ?? ''),
            'model' => (string) ($res['model'] ?? ''),
            'provider' => (string) ($res['provider'] ?? 'ollama'),
            'raw' => $res,
        ];
    }
}
