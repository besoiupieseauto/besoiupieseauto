<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Throwable;

/**
 * Vector store — Faza 1: metadata + status (embeddings = Faza 2).
 */
final class AiVectorStoreService
{
    private string $root;
    private string $metaPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_rag';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->metaPath = $dir . '/vector_index.meta.json';
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $keywordIndex = $this->root . '/app/Import/MatchingPro/config/rag/showcase_epiesa_rag.json';
        $keywordDocs = 0;
        if (is_file($keywordIndex)) {
            $json = json_decode((string) file_get_contents($keywordIndex), true);
            if (is_array($json) && isset($json['entries']) && is_array($json['entries'])) {
                $keywordDocs = count($json['entries']);
            }
        }

        try {
            $emb = (new AiProductEmbeddingStore(null, $this->root))->status();
            $emb['keyword_index_docs'] = $keywordDocs;
            $emb['phase'] = 2;
            $emb['corpus'] = (new AiRagCorpusService($this->root))->status();

            return $emb;
        } catch (Throwable) {
            $meta = $this->readMeta();

            return [
                'backend' => (string) ($meta['backend'] ?? 'keyword_sql'),
                'phase' => 2,
                'embeddings_enabled' => false,
                'documents_indexed' => (int) ($meta['documents_indexed'] ?? $keywordDocs),
                'keyword_index_docs' => $keywordDocs,
                'last_index_at' => (string) ($meta['last_index_at'] ?? ''),
                'embedding_model' => 'nomic-embed-text',
                'note' => 'Embeddings indisponibile — verifică BD.',
            ];
        }
    }

    /** @return array<string, mixed> */
    public function testSearch(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ok' => false, 'error' => 'Query gol', 'hits' => []];
        }

        $hits = [];
        $ragFile = $this->root . '/app/Import/MatchingPro/config/rag/showcase_epiesa_rag.json';
        if (is_file($ragFile)) {
            $json = json_decode((string) file_get_contents($ragFile), true);
            $entries = is_array($json['entries'] ?? null) ? $json['entries'] : [];
            $q = mb_strtolower($query, 'UTF-8');
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $text = mb_strtolower((string) ($entry['text'] ?? $entry['label'] ?? ''), 'UTF-8');
                if ($text !== '' && str_contains($text, $q)) {
                    $hits[] = ['source' => 'keyword_rag', 'score' => 0.5, 'snippet' => mb_substr($text, 0, 120)];
                }
                if (count($hits) >= $limit) {
                    break;
                }
            }
        }

        try {
            $client = new OllamaEmbeddingsClient($this->root);
            $emb = $client->embed($query);
            if (!empty($emb['ok'])) {
                $semantic = (new AiProductEmbeddingStore(null, $this->root))->searchSimilar($emb['vector'], $limit);
                foreach ($semantic as $row) {
                    $hits[] = [
                        'source' => 'embedding',
                        'score' => (float) ($row['score'] ?? 0),
                        'snippet' => (string) ($row['label'] ?? ''),
                        'source_id' => (string) ($row['source_id'] ?? ''),
                    ];
                }
                usort($hits, static fn (array $a, array $b): int => ($b['score'] <=> $a['score']));

                return [
                    'ok' => true,
                    'query' => $query,
                    'hits' => array_slice($hits, 0, $limit),
                    'backend' => 'keyword+embedding',
                ];
            }
        } catch (Throwable) {
            // fallback keyword only
        }

        return ['ok' => true, 'query' => $query, 'hits' => $hits, 'backend' => 'keyword'];
    }

    /** @return array<string, mixed> */
    private function readMeta(): array
    {
        if (!is_file($this->metaPath)) {
            return ['backend' => 'keyword_sql', 'documents_indexed' => 0];
        }
        $json = json_decode((string) file_get_contents($this->metaPath), true);

        return is_array($json) ? $json : [];
    }
}
