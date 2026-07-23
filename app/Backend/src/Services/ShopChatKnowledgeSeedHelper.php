<?php

declare(strict_types=1);

namespace Besoiu\Services;

/** Helper comun pentru fișiere seed RAG chat. */
final class ShopChatKnowledgeSeedHelper
{
    /**
     * @param list<string> $examples
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function entry(
        string $key,
        string $type,
        string $title,
        string $meaning,
        string $action,
        string $intent,
        string $response,
        array $examples = [],
        array $meta = [],
        int $priority = 70,
        string $channel = 'external',
    ): array {
        $tags = array_values(array_filter(array_unique(array_merge(
            explode('_', $key),
            [$meta['zone'] ?? '', $meta['element'] ?? '', $channel]
        ))));

        return [
            'entry_type' => $type,
            'channel' => $channel,
            'title' => $title,
            'question_examples' => $examples,
            'expected_meaning' => $meaning,
            'expected_action' => $action,
            'expected_intent' => $intent,
            'expected_response' => $response,
            'metadata_json' => array_merge(['seed_key' => $key, 'source' => 'extended_seed_v1'], $meta),
            'tags_json' => $tags,
            'priority' => $priority,
        ];
    }
}
