<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use InvalidArgumentException;

/**
 * Construiește context pentru orchestrator — DOAR agregate / rezultate search, fără evenimente brute.
 */
final class AiOrchestratorContextBuilder
{
    /** @var list<string> */
    private const FORBIDDEN_KEYS = [
        'raw_events',
        'events',
        'event_stream',
        'ai_intel_events',
        'click_stream',
        'session_events',
    ];

    public function __construct(
        private readonly ProductSignalService $signals,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function build(string $taskType, array $payload): array
    {
        AiTaskRegistry::validate($taskType);
        $this->rejectForbiddenKeys($payload);

        $context = [
            'task_type' => $taskType,
            'input' => $this->sanitizePayload($payload),
        ];

        $productIds = $this->extractProductIds($payload);
        if ($productIds !== []) {
            $context['product_signals'] = $this->signals->signalsForProducts(
                $productIds,
                (int) ($payload['signal_days'] ?? 7)
            );
        }

        if (isset($payload['search_hits']) && is_array($payload['search_hits'])) {
            $context['search_summary'] = $this->summarizeSearchHits($payload['search_hits']);
        }

        if (isset($payload['aggregates']) && is_array($payload['aggregates'])) {
            $context['aggregates'] = $this->sanitizeAggregates($payload['aggregates']);
        }

        return $context;
    }

    /** @param array<string, mixed> $payload */
    private function rejectForbiddenKeys(array $payload): void
    {
        foreach (self::FORBIDDEN_KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                throw new InvalidArgumentException(
                    'Payload interzis: cheia «' . $key . '» — folosește doar agregate (product_signals, aggregates, search_hits).'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        unset(
            $payload['raw_events'],
            $payload['events'],
            $payload['event_stream'],
            $payload['ai_intel_events'],
        );

        $out = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (in_array($key, self::FORBIDDEN_KEYS, true)) {
                continue;
            }
            $out[$key] = $this->sanitizeValue($value, 0);
        }

        return $out;
    }

    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if ($depth > 4) {
            return '[truncated]';
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 8000);
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_bool($value) || $value === null) {
            return $value;
        }
        if (is_array($value)) {
            $arr = [];
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i >= 50) {
                    break;
                }
                $arr[is_int($k) ? $i : (string) $k] = $this->sanitizeValue($v, $depth + 1);
                ++$i;
            }

            return $arr;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function extractProductIds(array $payload): array
    {
        $ids = [];
        if (isset($payload['product_id'])) {
            $ids[] = (string) $payload['product_id'];
        }
        if (isset($payload['product_ids']) && is_array($payload['product_ids'])) {
            foreach ($payload['product_ids'] as $id) {
                $ids[] = (string) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (string $id): bool => $id !== '')));
    }

    /**
     * @param array<int, mixed> $hits
     * @return list<array<string, mixed>>
     */
    private function summarizeSearchHits(array $hits): array
    {
        $summary = [];
        foreach (array_slice($hits, 0, 15) as $hit) {
            if (!is_array($hit)) {
                continue;
            }
            $summary[] = [
                'product_id' => (string) ($hit['product_id'] ?? ''),
                'name' => mb_substr((string) ($hit['name'] ?? ''), 0, 120),
                'scores' => is_array($hit['scores'] ?? null) ? $hit['scores'] : [],
                'signals' => is_array($hit['signals'] ?? null) ? $hit['signals'] : null,
            ];
        }

        return $summary;
    }

    /**
     * @param array<int, mixed> $aggregates
     * @return list<array<string, mixed>>
     */
    private function sanitizeAggregates(array $aggregates): array
    {
        $out = [];
        foreach (array_slice($aggregates, 0, 30) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'entity_type' => (string) ($row['entity_type'] ?? 'product'),
                'entity_id' => (string) ($row['entity_id'] ?? ''),
                'views' => (int) ($row['views'] ?? 0),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'purchases' => (int) ($row['purchases'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
            ];
        }

        return $out;
    }
}
