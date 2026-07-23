<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

/**
 * Pipeline complet: hybrid search + re-rank pe agregate.
 */
final class IntelligenceSearchService
{
    public function __construct(
        private readonly HybridSearchService $hybrid,
        private readonly SearchReRankerService $reRanker,
    ) {
    }

    public static function create(?string $projectRoot = null): self
    {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $store = new EventTrackingStore(null, $root);

        return new self(
            HybridSearchService::create($root),
            new SearchReRankerService(new ProductSignalService($store, null, $root)),
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function search(string $query, int $limit = 20, array $options = []): array
    {
        $hybrid = $this->hybrid->hybridSearch($query, $limit, $options);
        if (empty($hybrid['ok'])) {
            return $hybrid;
        }

        $popularityWeight = (float) ($options['popularity_weight'] ?? 0.15);
        $signalDays = (int) ($options['signal_days'] ?? 7);
        $hits = $this->reRanker->reRank(
            is_array($hybrid['hits'] ?? null) ? $hybrid['hits'] : [],
            $query,
            $popularityWeight,
            $signalDays
        );

        $meta = is_array($hybrid['meta'] ?? null) ? $hybrid['meta'] : [];
        $meta['popularity_weight'] = $popularityWeight;
        $meta['signal_days'] = $signalDays;
        $meta['pipeline'] = 'hybrid+rerank';

        return [
            'ok' => true,
            'query' => (string) ($hybrid['query'] ?? $query),
            'hits' => $hits,
            'meta' => $meta,
        ];
    }
}
