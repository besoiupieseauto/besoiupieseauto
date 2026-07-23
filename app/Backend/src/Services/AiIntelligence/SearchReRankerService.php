<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

/**
 * Re-ranker — combină scor hybrid cu semnale din ai_intel_aggregates (Pas 5).
 */
final class SearchReRankerService
{
    public function __construct(
        private readonly ProductSignalService $signals,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $results
     * @return list<array<string, mixed>>
     */
    public function reRank(array $results, string $query, float $popularityWeight = 0.15, int $signalDays = 7): array
    {
        if ($results === []) {
            return [];
        }

        $popularityWeight = max(0.0, min(0.5, $popularityWeight));
        $lookupIds = [];
        foreach ($results as $row) {
            $rid = trim((string) ($row['randomn_id'] ?? ''));
            $pid = trim((string) ($row['product_id'] ?? ''));
            if ($rid !== '') {
                $lookupIds[$rid] = true;
            }
            if ($pid !== '') {
                $lookupIds[$pid] = true;
            }
        }
        $signalMap = $this->signals->signalsForProducts(array_keys($lookupIds), $signalDays);

        foreach ($results as &$row) {
            $rid = trim((string) ($row['randomn_id'] ?? ''));
            $pid = trim((string) ($row['product_id'] ?? ''));
            $signal = $signalMap[$rid] ?? $signalMap[$pid] ?? null;
            $hybrid = (float) ($row['scores']['hybrid'] ?? 0);
            $popularity = (float) ($signal['score'] ?? 0.0);
            $row['scores']['popularity'] = round($popularity, 4);
            $row['scores']['final'] = round(
                ($hybrid * (1.0 - $popularityWeight)) + ($popularity * $popularityWeight),
                4
            );
            $row['signals'] = $signal ?? [
                'views' => 0,
                'clicks' => 0,
                'purchases' => 0,
                'ctr' => 0.0,
                'score' => 0.0,
            ];
        }
        unset($row);

        usort($results, static fn (array $a, array $b): int => ($b['scores']['final'] ?? 0) <=> ($a['scores']['final'] ?? 0));

        return $results;
    }
}
