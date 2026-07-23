<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

/**
 * Registry task-uri AI Intelligence — rutare local (Ollama) vs extern (complex).
 */
final class AiTaskRegistry
{
    /** @var list<string> */
    public const LOCAL_TASKS = [
        'classify_category',
        'embedding',
        'image_check',
        'hybrid_search',
    ];

    /** @var list<string> */
    public const COMPLEX_TASKS = [
        'seo_description',
        'price_competition_analysis',
        'scraped_data_synthesis',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(self::LOCAL_TASKS, self::COMPLEX_TASKS);
    }

    public static function isLocal(string $taskType): bool
    {
        return in_array($taskType, self::LOCAL_TASKS, true);
    }

    public static function isComplex(string $taskType): bool
    {
        return in_array($taskType, self::COMPLEX_TASKS, true);
    }

    public static function validate(string $taskType): void
    {
        if (!in_array($taskType, self::all(), true)) {
            throw new \InvalidArgumentException(
                'task_type necunoscut: ' . $taskType . '. Permise: ' . implode(', ', self::all())
            );
        }
    }

    /** @return list<array<string, mixed>> */
    public static function describeForUi(): array
    {
        $out = [];
        foreach (self::LOCAL_TASKS as $task) {
            $out[] = [
                'task_type' => $task,
                'route' => 'local',
                'engine' => 'ollama',
                'description' => self::description($task),
            ];
        }
        foreach (self::COMPLEX_TASKS as $task) {
            $out[] = [
                'task_type' => $task,
                'route' => 'external',
                'engine' => 'external_llm',
                'description' => self::description($task),
            ];
        }

        return $out;
    }

    private static function description(string $task): string
    {
        return match ($task) {
            'classify_category' => 'Potrivire categorie/subcategorie din taxonomia BD',
            'embedding' => 'Vector semantic pentru indexare / search',
            'image_check' => 'Verificare imagine produs (vision local)',
            'hybrid_search' => 'Căutare keyword + embedding + re-rank agregate',
            'seo_description' => 'Generare descriere SEO (necesită API extern)',
            'price_competition_analysis' => 'Analiză preț vs concurență (API extern)',
            'scraped_data_synthesis' => 'Sinteză date scrapuite (API extern)',
            default => $task,
        };
    }
}
