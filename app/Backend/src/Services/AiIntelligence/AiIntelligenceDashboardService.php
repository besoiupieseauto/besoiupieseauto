<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Besoiu\Async\JobQueue;
use Besoiu\Services\AiRag\AiProductEmbeddingStore;
use Besoiu\Services\AiRag\AiVectorStoreService;
use Config\Database;
use PDO;
use Throwable;

/**
 * Snapshot pentru tab-ul Intelligence — Centru AI (Pas 7).
 */
final class AiIntelligenceDashboardService
{
    private string $root;
    private PDO $pdo;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $config = require $this->root . '/admin/config/config.php';
        if (!Database::hasConnection()) {
            Database::getInstance(
                (string) ($config['db_host'] ?? '127.0.0.1'),
                (string) ($config['db_name'] ?? ''),
                (string) ($config['db_user'] ?? ''),
                (string) ($config['db_pass'] ?? '')
            );
        }
        $this->pdo = Database::getDB();
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $store = new EventTrackingStore(null, $this->root);
        $store->ensureTables();

        $ollama = OllamaClient::create($this->root);
        $external = new ExternalLlmGateway($this->root);
        $orchestrator = AiOrchestrator::create($this->root);

        $queue = new JobQueue();
        $vector = (new AiVectorStoreService($this->root))->status();

        return [
            'generated_at' => date('c'),
            'database' => $this->databaseName(),
            'principle' => 'Evenimente → agregate → search/re-rank/orchestrator. Fără evenimente brute în prompt.',
            'storage' => $this->storageCounts(),
            'ollama' => $ollama->readiness(),
            'external_llm' => $external->readiness(),
            'orchestrator' => $orchestrator->status(),
            'vector_store' => $vector,
            'queue' => [
                'name' => EventQueueService::QUEUE_NAME,
                'redis_active' => $queue->isRedisActive(),
                'file_fallback' => $queue->isFileFallbackActive(),
            ],
            'top_products' => $this->topProductAggregates(10),
            'recent_routes' => $this->recentRoutes(15),
            'tasks' => AiTaskRegistry::describeForUi(),
            'pipelines' => $this->pipelineMap(),
            'cron_jobs' => $this->cronHints(),
            'learn' => $this->learnCards(),
            'event_timeline' => $this->eventTimeline(7),
            'event_breakdown' => $this->eventBreakdown(),
            'pipeline_flow' => $this->pipelineFlowStatus($ollama->readiness(), $queue, $this->storageCounts()),
            'health' => $this->buildHealth($ollama->readiness(), $external->readiness(), $queue, $this->storageCounts()),
            'visitor_stats' => (new VisitorAnalyticsService($store, $this->pdo, $this->root))->visitorStats(7),
        ];
    }

    private function databaseName(): string
    {
        try {
            $stmt = $this->pdo->query('SELECT DATABASE()');
            $name = (string) ($stmt ? $stmt->fetchColumn() : '');
            $stmt?->closeCursor();

            return $name;
        } catch (Throwable) {
            return '';
        }
    }

    /** @return array<string, int> */
    private function storageCounts(): array
    {
        $tables = [
            'sessions' => EventTrackingStore::TABLE_SESSIONS,
            'events' => EventTrackingStore::TABLE_EVENTS,
            'aggregates' => EventTrackingStore::TABLE_AGGREGATES,
            'embeddings' => 'ai_product_embeddings',
        ];
        $out = [];
        foreach ($tables as $key => $table) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$table}");
                $out[$key] = (int) ($stmt ? $stmt->fetchColumn() : 0);
                $stmt?->closeCursor();
            } catch (Throwable) {
                $out[$key] = 0;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function topProductAggregates(int $limit): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT entity_id, entity_type, period_date, views, clicks, purchases, ctr
                 FROM ' . EventTrackingStore::TABLE_AGGREGATES . "
                 WHERE entity_type = 'product'
                 ORDER BY views DESC, clicks DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function recentRoutes(int $limit): array
    {
        $log = $this->root . '/admin/storage/ai_intelligence/orchestrator_routes.jsonl';
        if (!is_file($log)) {
            return [];
        }
        $raw = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = [];
        foreach (array_slice(array_reverse($raw), 0, $limit) as $line) {
            $json = json_decode($line, true);
            if (is_array($json)) {
                $lines[] = $json;
            }
        }

        return $lines;
    }

    /** @return list<array<string, string>> */
    private function pipelineMap(): array
    {
        return [
            [
                'layer' => 'Storefront',
                'component' => 'besoiu-client-tracker.js',
                'uses' => 'POST /api/events',
                'stores' => 'Coadă ai-events → ai_intel_events',
            ],
            [
                'layer' => 'Worker',
                'component' => 'event_writer_worker.php',
                'uses' => 'Redis / fișier',
                'stores' => 'MySQL ai_intel_*',
            ],
            [
                'layer' => 'Cron orar',
                'component' => 'ai_intel_aggregate_events.php',
                'uses' => 'ai_intel_events',
                'stores' => 'ai_intel_aggregates',
            ],
            [
                'layer' => 'Search',
                'component' => 'HybridSearchService',
                'uses' => 'Ollama embed + SQL produse',
                'stores' => 'ai_product_embeddings + produse',
            ],
            [
                'layer' => 'Re-rank',
                'component' => 'SearchReRankerService',
                'uses' => 'ai_intel_aggregates',
                'stores' => 'Scor final (nu persistat)',
            ],
            [
                'layer' => 'Orchestrator',
                'component' => 'AiOrchestrator',
                'uses' => 'Ollama local / EXTERNAL_LLM_API_KEY',
                'stores' => 'orchestrator_routes.jsonl',
            ],
        ];
    }

    /** @return list<array<string, string>> */
    private function cronHints(): array
    {
        return [
            ['schedule' => 'La 1 min', 'command' => 'php admin/workers/event_writer_worker.php --max=100', 'role' => 'Scrie evenimente din coadă'],
            ['schedule' => 'Orar', 'command' => 'php admin/cron_cli/ai_intel_aggregate_events.php', 'role' => 'Recalculează CTR'],
            ['schedule' => 'Nightly', 'command' => 'php admin/cron_cli/ai_intel_index_products.php --limit=400', 'role' => 'Index embeddings'            ],
        ];
    }

    /** @return list<array{day:string,label:string,total:int}> */
    private function eventTimeline(int $days): array
    {
        $days = max(1, min(14, $days));
        $since = (new \DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $counts = [];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT DATE(ts) AS day, COUNT(*) AS total
                 FROM ' . EventTrackingStore::TABLE_EVENTS . '
                 WHERE DATE(ts) >= :since
                 GROUP BY DATE(ts)
                 ORDER BY day ASC'
            );
            $stmt->execute([':since' => $since]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $day = (string) ($row['day'] ?? '');
                if ($day !== '') {
                    $counts[$day] = (int) ($row['total'] ?? 0);
                }
            }
        } catch (Throwable) {
            /* optional */
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = (new \DateTimeImmutable('today'))->modify('-' . $i . ' days');
            $key = $d->format('Y-m-d');
            $out[] = [
                'day' => $key,
                'label' => $d->format('D d.m'),
                'total' => $counts[$key] ?? 0,
            ];
        }

        return $out;
    }

    /** @return list<array{event_type:string,count:int,label:string}> */
    private function eventBreakdown(): array
    {
        $labels = [
            'page_view' => 'Pagini',
            'search_query' => 'Căutări',
            'search_result_click' => 'Click search',
            'product_view' => 'Vizite produs',
            'add_to_cart' => 'Coș',
            'purchase' => 'Comenzi',
            'recommendation_click' => 'Recomandări',
            'filter_applied' => 'Filtre',
        ];
        try {
            $stmt = $this->pdo->query(
                'SELECT event_type, COUNT(*) AS cnt
                 FROM ' . EventTrackingStore::TABLE_EVENTS . '
                 GROUP BY event_type
                 ORDER BY cnt DESC
                 LIMIT 10'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $row) {
                $type = (string) ($row['event_type'] ?? '');
                $out[] = [
                    'event_type' => $type,
                    'count' => (int) ($row['cnt'] ?? 0),
                    'label' => $labels[$type] ?? $type,
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $ollama
     * @param array<string, mixed> $queue
     * @param array<string, int> $storage
     * @return list<array<string, mixed>>
     */
    private function pipelineFlowStatus(array $ollama, JobQueue $queue, array $storage): array
    {
        $events = (int) ($storage['events'] ?? 0);
        $aggregates = (int) ($storage['aggregates'] ?? 0);
        $embeddings = (int) ($storage['embeddings'] ?? 0);
        $ollamaOk = !empty($ollama['ready']);

        $queueStatus = $queue->isRedisActive() ? 'ok' : ($queue->isFileFallbackActive() ? 'warn' : 'ok');

        return [
            [
                'step' => 1,
                'id' => 'track',
                'icon' => 'fa-solid fa-eye',
                'title' => 'Tracking',
                'subtitle' => 'Magazin → /api/events',
                'status' => $events > 0 ? 'ok' : 'idle',
                'metric' => $events . ' evenimente',
            ],
            [
                'step' => 2,
                'id' => 'queue',
                'icon' => 'fa-solid fa-inbox',
                'title' => 'Coadă',
                'subtitle' => $queue->isRedisActive() ? 'Redis Streams' : 'Fișier (dev)',
                'status' => $queueStatus,
                'metric' => EventQueueService::QUEUE_NAME,
            ],
            [
                'step' => 3,
                'id' => 'aggregate',
                'icon' => 'fa-solid fa-chart-column',
                'title' => 'Agregate',
                'subtitle' => 'CTR · views · clicks',
                'status' => $aggregates > 0 ? 'ok' : ($events > 0 ? 'warn' : 'idle'),
                'metric' => $aggregates . ' rânduri',
            ],
            [
                'step' => 4,
                'id' => 'embed',
                'icon' => 'fa-solid fa-dna',
                'title' => 'Embeddings',
                'subtitle' => 'Ollama ' . ($ollama['embed_model'] ?? 'embed'),
                'status' => $embeddings > 0 ? 'ok' : ($ollamaOk ? 'warn' : 'fail'),
                'metric' => $embeddings . ' vectori',
            ],
            [
                'step' => 5,
                'id' => 'search',
                'icon' => 'fa-solid fa-magnifying-glass',
                'title' => 'Search hybrid',
                'subtitle' => 'Keyword + semantic + CTR',
                'status' => $ollamaOk || $events > 0 ? ($embeddings > 0 ? 'ok' : 'warn') : 'fail',
                'metric' => 'POST /api/search',
            ],
            [
                'step' => 6,
                'id' => 'orchestrator',
                'icon' => 'fa-solid fa-sitemap',
                'title' => 'Orchestrator',
                'subtitle' => 'Local / API extern',
                'status' => 'ok',
                'metric' => count(AiTaskRegistry::all()) . ' task-uri',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $ollama
     * @param array<string, mixed> $external
     * @param array<string, int> $storage
     * @return array<string, mixed>
     */
    private function buildHealth(array $ollama, array $external, JobQueue $queue, array $storage): array
    {
        $checks = [
            [
                'id' => 'tracking',
                'label' => 'Evenimente în MySQL',
                'status' => ((int) ($storage['events'] ?? 0)) > 0 ? 'ok' : 'warn',
                'hint' => 'Vizitează magazinul sau rulează test events',
            ],
            [
                'id' => 'aggregates',
                'label' => 'Agregate calculate',
                'status' => ((int) ($storage['aggregates'] ?? 0)) > 0 ? 'ok' : 'warn',
                'hint' => 'Cron orar sau buton Recalculează agregate',
            ],
            [
                'id' => 'ollama',
                'label' => 'Ollama ready',
                'status' => !empty($ollama['ready']) ? 'ok' : 'fail',
                'hint' => 'ollama pull nomic-embed-text',
            ],
            [
                'id' => 'embeddings',
                'label' => 'Produse indexate',
                'status' => ((int) ($storage['embeddings'] ?? 0)) > 0 ? 'ok' : 'warn',
                'hint' => 'Index produse (batch)',
            ],
            [
                'id' => 'queue',
                'label' => 'Coadă Redis',
                'status' => $queue->isRedisActive() ? 'ok' : 'warn',
                'hint' => 'Recomandat Redis în producție',
            ],
            [
                'id' => 'external',
                'label' => 'API extern (SEO/pricing)',
                'status' => !empty($external['configured']) ? 'ok' : 'idle',
                'hint' => 'EXTERNAL_LLM_API_KEY opțional',
            ],
        ];

        $score = 0;
        $max = 0;
        foreach ($checks as $c) {
            if ($c['status'] === 'idle') {
                continue;
            }
            $max += 20;
            if ($c['status'] === 'ok') {
                $score += 20;
            } elseif ($c['status'] === 'warn') {
                $score += 10;
            }
        }
        if ($max === 0) {
            $max = 100;
        }
        $pct = (int) round(($score / $max) * 100);

        $label = match (true) {
            $pct >= 85 => 'Operațional',
            $pct >= 55 => 'Parțial',
            default => 'Necesită atenție',
        };

        return [
            'score' => $pct,
            'label' => $label,
            'checks' => $checks,
        ];
    }

    /** @return list<array<string, string>> */
    private function learnCards(): array
    {
        return [
            [
                'title' => '1. Tracking',
                'text' => 'Vizitatorul trimite evenimente la /api/events. Nu intră direct în AI — trec prin coadă și worker.',
            ],
            [
                'title' => '2. Agregate',
                'text' => 'Job-ul orar calculează views/clicks/CTR în ai_intel_aggregates. Doar aceste semnale influențează re-ranking.',
            ],
            [
                'title' => '3. Search hybrid',
                'text' => 'Keyword din tabelul produse + semantic din ai_product_embeddings. Sliderul «popularitate» controlează cât contează CTR.',
            ],
            [
                'title' => '4. Orchestrator',
                'text' => 'Task-uri rapide → Ollama. SEO/pricing/sinteză → API extern doar cu EXTERNAL_LLM_API_KEY.',
            ],
        ];
    }
}
