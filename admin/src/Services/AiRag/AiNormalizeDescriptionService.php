<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

/**
 * Modul 2 — normalizare descrieri CSV → JSON strict (preview, fără auto-apply).
 */
final class AiNormalizeDescriptionService
{
    public function __construct(
        private readonly string $projectRoot = '',
    ) {
    }

    private function root(): string
    {
        return $this->projectRoot !== ''
            ? $this->projectRoot
            : (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    /**
     * @param array<string, mixed> $input raw_name, raw_description, brand, oem, sku
     * @param array<string, mixed> $options user_id
     * @return array<string, mixed>
     */
    public function normalizePreview(array $input, array $options = []): array
    {
        $config = (new AiRagConfigService($this->root()))->moduleRuntime('mod_02_normalize_desc');
        if ($config === null || empty($config['enabled'])) {
            return ['ok' => false, 'error' => 'Modul 2 dezactivat — activează în AI & RAG → Module AI'];
        }

        $rawName = trim((string) ($input['raw_name'] ?? $input['name'] ?? ''));
        $rawDesc = trim((string) ($input['raw_description'] ?? $input['description'] ?? ''));
        if ($rawName === '' && $rawDesc === '') {
            return ['ok' => false, 'error' => 'Nume/descriere lipsă'];
        }

        $system = <<<'PROMPT'
Normalizezi produse piese auto din CSV furnizor. Răspunde DOAR JSON valid:
{
  "nume_normalizat": "string",
  "cod_oe": "string sau gol",
  "categorie": "string",
  "atribute": {"brand":"","sku":""},
  "confidence": 0.0-1.0,
  "verdict": "ok|review"
}
Nu inventa coduri OEM — doar din input.
PROMPT;

        $user = json_encode([
            'raw_name' => $rawName,
            'raw_description' => mb_substr($rawDesc, 0, 2000),
            'brand' => (string) ($input['brand'] ?? ''),
            'oem' => (string) ($input['oem'] ?? ''),
            'sku' => (string) ($input['sku'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);

        $gov = (new AiGovernanceService($this->root()))->propose(
            'mod_02_normalize_desc',
            $system,
            (string) $user,
            $input,
            [
                'user_id' => $options['user_id'] ?? null,
                'action' => 'normalize_preview',
                'timeout' => 60,
                'model' => (string) ($config['model'] ?? 'qwen2.5:7b'),
            ]
        );

        $structured = is_array($gov['structured'] ?? null) ? $gov['structured'] : null;

        return [
            'ok' => !empty($gov['ok']),
            'requires_approval' => true,
            'auto_apply' => false,
            'before' => ['name' => $rawName, 'description' => $rawDesc],
            'after' => $structured,
            'raw' => (string) ($gov['raw'] ?? ''),
            'error' => (string) ($gov['error'] ?? ''),
            'log_id' => $gov['log_id'] ?? null,
            'schema_errors' => $gov['schema_errors'] ?? [],
        ];
    }
}
