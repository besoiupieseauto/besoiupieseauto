<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\OllamaLlmClient;
use Throwable;

/**
 * Job orchestrator — scraping pilot + procesare + bibliotecă + corpus RAG.
 */
final class AiMarketResearchJobService
{
    private string $root;
    private string $progressPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_rag';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->progressPath = $dir . '/market_job_progress.json';
    }

    /** @return array<string, mixed> */
    public function progress(): array
    {
        if (!is_file($this->progressPath)) {
            return ['status' => 'idle', 'log' => [], 'pages' => []];
        }
        $json = json_decode((string) file_get_contents($this->progressPath), true);

        return is_array($json) ? $json : ['status' => 'idle'];
    }

    /**
     * @param array<string, mixed> $options test_mode, source_id, page_limit
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        $sourceSvc = new AiMarketResearchSourceService($this->root);
        $scrapeSvc = new AiMarketResearchScrapeService($this->root);
        $processSvc = new AiMarketResearchProcessService($this->root);
        $store = new AiMarketResearchStore(null, $this->root);
        $corpus = new AiRagCorpusService($this->root);

        $cfg = $sourceSvc->all();
        $pageLimit = max(1, min(20, (int) ($options['page_limit'] ?? ($cfg['defaults']['test_page_limit'] ?? 5))));
        $testMode = !empty($options['test_mode']);
        $filterId = trim((string) ($options['source_id'] ?? ''));

        $sources = $sourceSvc->activeSources();
        if ($filterId !== '') {
            $one = $sourceSvc->get($filterId);
            $sources = $one !== null ? [$one] : [];
        }

        if ($sources === []) {
            return ['ok' => false, 'error' => 'Nicio sursă activă — activează o sursă pilot din panou'];
        }

        $progress = [
            'status' => 'running',
            'started_at' => date('c'),
            'test_mode' => $testMode,
            'page_limit' => $pageLimit,
            'pages_done' => 0,
            'pages_total' => min($pageLimit, count($sources)),
            'log' => [],
            'entries' => [],
        ];
        $this->writeProgress($progress);

        $added = 0;
        $failed = 0;

        foreach (array_slice($sources, 0, $pageLimit) as $idx => $source) {
            $sourceId = (string) ($source['id'] ?? '');
            $tip = (string) ($source['tip_sursa'] ?? 'tendinta_piata');
            $progress['current'] = 'Pagină ' . ($idx + 1) . '/' . $progress['pages_total'] . ' — ' . ($source['name'] ?? $sourceId);
            $this->writeProgress($progress);

            if ($idx > 0) {
                $sourceSvc->humanDelay();
            }

            $fetch = $scrapeSvc->scrapeSourcePage($source);
            if (empty($fetch['ok'])) {
                ++$failed;
                $progress['log'][] = [
                    'at' => date('c'),
                    'source_id' => $sourceId,
                    'ok' => false,
                    'message' => (string) ($fetch['error'] ?? 'Eșec'),
                ];
                $this->recordSourceStats($sourceId, false);
                $progress['pages_done'] = $idx + 1;
                $this->writeProgress($progress);
                continue;
            }

            $extract = $processSvc->extract($tip, (string) ($fetch['continut_brut'] ?? ''), (string) ($fetch['url'] ?? ''));
            if (empty($extract['ok'])) {
                ++$failed;
                $progress['log'][] = [
                    'at' => date('c'),
                    'source_id' => $sourceId,
                    'ok' => false,
                    'message' => 'Procesare Ollama: ' . ($extract['error'] ?? ''),
                ];
                $this->recordSourceStats($sourceId, false);
                $progress['pages_done'] = $idx + 1;
                $this->writeProgress($progress);
                continue;
            }

            $uuid = $store->insert([
                'tip_sursa' => $tip,
                'source_id' => $sourceId,
                'url_sursa' => (string) ($fetch['url'] ?? ''),
                'data_scraping' => date('Y-m-d H:i:s'),
                'continut_brut' => (string) ($fetch['continut_brut'] ?? ''),
                'continut_procesat' => $extract['continut_procesat'],
                'confidence_extractie' => $extract['confidence'],
                'status_validare' => 'auto',
                'seo' => $fetch['seo'] ?? [],
            ]);

            $summaryText = $this->entrySummary($tip, $extract['continut_procesat'], (string) ($fetch['url'] ?? ''));
            $corpus->append([
                'source_type' => 'market_research',
                'source_id' => $sourceId,
                'title' => (string) (($fetch['seo']['title'] ?? '') ?: ($source['name'] ?? 'Research piață')),
                'text' => $summaryText,
                'url' => (string) ($fetch['url'] ?? ''),
                'keywords' => $this->keywordsFromProcessed($tip, $extract['continut_procesat']),
                'meta' => [
                    'entry_uuid' => $uuid,
                    'tip_sursa' => $tip,
                    'confidence' => $extract['confidence'],
                ],
            ]);

            ++$added;
            $this->recordSourceStats($sourceId, true);
            $progress['entries'][] = ['uuid' => $uuid, 'source_id' => $sourceId, 'url' => $fetch['url'] ?? ''];
            $progress['log'][] = [
                'at' => date('c'),
                'source_id' => $sourceId,
                'ok' => true,
                'message' => 'Salvat ' . $uuid,
                'via' => $fetch['via'] ?? '',
            ];
            $progress['pages_done'] = $idx + 1;
            $this->writeProgress($progress);
        }

        $progress['status'] = 'done';
        $progress['finished_at'] = date('c');
        $progress['added'] = $added;
        $progress['failed'] = $failed;
        unset($progress['current']);
        $this->writeProgress($progress);

        return [
            'ok' => $added > 0,
            'added' => $added,
            'failed' => $failed,
            'progress' => $progress,
            'audit' => $store->auditSummary(),
        ];
    }

    /** @param array<string, mixed> $processed */
    private function entrySummary(string $tip, array $processed, string $url): string
    {
        $parts = ["Research piață ({$tip})", "URL: {$url}"];
        if ($tip === 'pret_concurenta') {
            $parts[] = 'Produs: ' . ($processed['nume_produs'] ?? '—');
            $parts[] = 'Preț: ' . ($processed['pret'] ?? '—') . ' ' . ($processed['moneda'] ?? 'RON');
        } elseif ($tip === 'seo_keyword') {
            $parts[] = 'Cuvinte: ' . implode(', ', (array) ($processed['cuvinte_cheie_frecvente'] ?? []));
            $parts[] = (string) ($processed['observatii'] ?? '');
        } else {
            $parts[] = (string) ($processed['rezumat'] ?? json_encode($processed, JSON_UNESCAPED_UNICODE));
        }

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $processed @return list<string> */
    private function keywordsFromProcessed(string $tip, array $processed): array
    {
        if ($tip === 'seo_keyword') {
            return array_slice(array_map('strval', (array) ($processed['cuvinte_cheie_frecvente'] ?? [])), 0, 12);
        }
        if ($tip === 'pret_concurenta' && !empty($processed['nume_produs'])) {
            return [(string) $processed['nume_produs']];
        }

        return [];
    }

    private function recordSourceStats(string $sourceId, bool $success): void
    {
        $path = $this->root . '/admin/storage/ai_rag/market_source_stats.json';
        $stats = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $stats = is_array($decoded) ? $decoded : [];
        }
        if (!isset($stats[$sourceId])) {
            $stats[$sourceId] = ['success' => 0, 'fail' => 0, 'last_at' => '', 'last_ok' => ''];
        }
        if ($success) {
            ++$stats[$sourceId]['success'];
            $stats[$sourceId]['last_ok'] = date('c');
        } else {
            ++$stats[$sourceId]['fail'];
        }
        $stats[$sourceId]['last_at'] = date('c');
        @file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $progress */
    private function writeProgress(array $progress): void
    {
        @file_put_contents($this->progressPath, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
