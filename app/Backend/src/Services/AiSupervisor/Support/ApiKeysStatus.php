<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Support;

/**
 * Stare chei API din Setări — separat de consumul LLM din jurnal.
 */
final class ApiKeysStatus
{
    /** @return array<string, mixed> */
    public static function snapshot(): array
    {
        $helper = dirname(__DIR__, 4) . '/system/env_settings.php';
        if (!is_file($helper)) {
            return ['items' => [], 'configured_count' => 0, 'llm_count' => 0, 'pipeline_count' => 0, 'primary_llm_ok' => false];
        }
        require_once $helper;

        $pipelineKeys = ['RAPIDAPI_AUTOPARTS_KEY', 'SCRAPE_DO_TOKEN'];
        $items = [];
        $configured = 0;
        $llmCount = 0;
        $pipelineCount = 0;

        foreach (besoiu_env_editable_keys() as $envKey => $meta) {
            $isPipeline = in_array($envKey, $pipelineKeys, true);
            $has = besoiu_env_has($envKey);
            if ($has) {
                ++$configured;
                if ($isPipeline) {
                    ++$pipelineCount;
                } else {
                    ++$llmCount;
                }
            }
            $model = is_array($meta['model'] ?? null) ? $meta['model'] : [];
            $items[] = [
                'key' => $envKey,
                'label' => (string) ($meta['label'] ?? $envKey),
                'group' => $isPipeline ? 'pipeline' : 'llm',
                'configured' => $has,
                'model' => trim((string) ($model['value'] ?? '')),
            ];
        }

        $primaryLlmOk = false;
        $aiHelper = dirname(__DIR__, 5) . '/system/ai_api_errors.php';
        if (is_file($aiHelper)) {
            require_once $aiHelper;
            if (function_exists('ai_api_key_configured')) {
                $primaryLlmOk = ai_api_key_configured();
            }
        }

        return [
            'items' => $items,
            'configured_count' => $configured,
            'llm_count' => $llmCount,
            'pipeline_count' => $pipelineCount,
            'primary_llm_ok' => $primaryLlmOk,
            'settings_url' => '/admin/settings',
        ];
    }
}
