<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

/**
 * Registry celor 10 module AI — metadata + defaults (principiul: AI propune, omul aprobă).
 */
final class AiRagModuleRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $modules = [
            self::entry('mod_01_semantic_match', 1, 'Matching semantic produse-furnizor', 'Embedding + similarity search în catalog', 2, true, ['exact' => 0.92, 'probable_min' => 0.75], 'qwen2.5:7b'),
            self::entry('mod_02_normalize_desc', 2, 'Normalizare descrieri produse', 'Schema JSON strictă din text CSV furnizor', 2, true, ['schema_valid' => 1.0], 'qwen2.5:7b'),
            self::entry('mod_03_fill_attributes', 3, 'Auto-completare atribute (RAG)', 'Sugestii din catalog + documente tehnice', 3, true, ['min_confidence' => 0.7], 'qwen2.5:7b'),
            self::entry('mod_04_fitment_conflicts', 4, 'Conflicte compatibilitate auto', 'Verificare fitment + RAG compatibilități', 3, true, ['critical_block' => 0.85], 'qwen2.5:7b'),
            self::entry('mod_05_showcase_classify', 5, 'Clasificare vitrină vs standard', 'Few-shot din istoric etichetat', 4, true, ['vitrina_min' => 0.65], 'qwen2.5:7b'),
            self::entry('mod_06_log_summary', 6, 'Rezumate log/cron', 'Sumarizare informativă rulări cron + erori', 1, false, [], 'qwen2.5:7b', true),
            self::entry('mod_07_admin_chat', 7, 'Chatbot intern suport admin', 'RAG peste log-uri + reguli business (read-only)', 5, false, [], 'qwen2.5:7b'),
            self::entry('mod_08_file_anomaly', 8, 'Anomalii fișiere furnizori', 'Comparație structură/volum vs istoric', 3, true, ['volume_deviation_pct' => 25], 'qwen2.5:7b'),
            self::entry('mod_09_seo_descriptions', 9, 'Generare descrieri SEO', 'Draft nepublicat — review obligatoriu', 4, true, ['min_quality' => 0.6], 'qwen2.5:7b'),
            self::entry('mod_10_missing_image', 10, 'Asistent produse fără imagine', 'Sugestie imagine similară — aprobare manuală', 4, true, ['similarity_min' => 0.8], 'llava:7b'),
        ];

        $out = [];
        foreach ($modules as $row) {
            $out[(string) $row['id']] = $row;
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function get(string $moduleId): ?array
    {
        return self::all()[$moduleId] ?? null;
    }

    /** @return list<string> */
    public static function phase1EnabledIds(): array
    {
        return ['mod_06_log_summary'];
    }

    /** @return list<string> */
    public static function phase2EnabledIds(): array
    {
        return ['mod_01_semantic_match', 'mod_02_normalize_desc', 'mod_06_log_summary'];
    }

    public static function maxAvailablePhase(): int
    {
        return 2;
    }

    /**
     * @param array<string, float|int> $thresholds
     * @return array<string, mixed>
     */
    private static function entry(
        string $id,
        int $number,
        string $name,
        string $description,
        int $phase,
        bool $requiresApproval,
        array $thresholds,
        string $defaultModel,
        bool $defaultEnabled = false,
    ): array {
        return [
            'id' => $id,
            'number' => $number,
            'name' => $name,
            'description' => $description,
            'phase' => $phase,
            'requires_approval' => $requiresApproval,
            'auto_apply' => false,
            'default_enabled' => $defaultEnabled,
            'default_model' => $defaultModel,
            'default_thresholds' => $thresholds,
            'output_schema' => self::schemaForModule($number),
        ];
    }

    /** @return array<string, mixed> */
    private static function schemaForModule(int $number): array
    {
        return match ($number) {
            6 => [
                'type' => 'object',
                'required' => ['summary_ro', 'highlights', 'stats'],
                'properties' => [
                    'summary_ro' => ['type' => 'string'],
                    'highlights' => ['type' => 'array'],
                    'stats' => ['type' => 'object'],
                    'recommendations' => ['type' => 'array'],
                ],
            ],
            5 => [
                'type' => 'object',
                'required' => ['clasificare', 'scor_incredere'],
                'properties' => [
                    'clasificare' => ['type' => 'string', 'enum' => ['vitrina', 'standard']],
                    'scor_incredere' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
            ],
            default => [
                'type' => 'object',
                'required' => ['verdict', 'confidence'],
                'properties' => [
                    'verdict' => ['type' => 'string'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
            ],
        };
    }
}
