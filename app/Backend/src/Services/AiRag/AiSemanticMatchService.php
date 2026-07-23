<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

/**
 * Modul 1 — matching semantic produse-furnizor (embeddings + top-5 candidați).
 */
final class AiSemanticMatchService
{
    public function __construct(
        private readonly string $projectRoot = '',
    ) {
    }

    private function root(): string
    {
        return $this->projectRoot !== ''
            ? $this->projectRoot
            : (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    /**
     * @param array<string, mixed> $input name, sku, brand, supplier, oem
     * @param array<string, mixed> $options user_id, auto_index
     * @return array<string, mixed>
     */
    public function suggest(array $input, array $options = []): array
    {
        $config = (new AiRagConfigService($this->root()))->moduleRuntime('mod_01_semantic_match');
        if ($config === null || empty($config['enabled'])) {
            return ['ok' => false, 'error' => 'Modul 1 dezactivat — activează în AI & RAG → Module AI'];
        }

        $name = trim((string) ($input['name'] ?? ''));
        $sku = trim((string) ($input['sku'] ?? $input['sku_supplier'] ?? ''));
        $brand = trim((string) ($input['brand'] ?? ''));
        $oem = trim((string) ($input['oem'] ?? ''));
        $queryText = trim(implode(' ', array_filter([$name, $brand, $sku, $oem !== '' ? 'OEM:' . $oem : ''])));
        if ($queryText === '') {
            return ['ok' => false, 'error' => 'Input gol pentru matching semantic'];
        }

        $embedClient = new OllamaEmbeddingsClient($this->root());
        $store = new AiProductEmbeddingStore(null, $this->root());
        $status = $store->status();
        if (($status['documents_indexed'] ?? 0) < 10 && !empty($options['auto_index'])) {
            $store->indexFromProduse(300, $embedClient);
        }

        $emb = $embedClient->embed($queryText);
        if (empty($emb['ok'])) {
            return ['ok' => false, 'error' => 'Embedding eșuat: ' . ($emb['error'] ?? ''), 'hint' => 'Verifică nomic-embed-text în Ollama'];
        }

        $candidates = $store->searchSimilar($emb['vector'], 5);
        $thresholds = (array) ($config['thresholds'] ?? []);
        $exactMin = (float) ($thresholds['exact'] ?? 0.92);
        $probableMin = (float) ($thresholds['probable_min'] ?? 0.75);

        $classified = [];
        foreach ($candidates as $c) {
            $score = (float) ($c['score'] ?? 0);
            $verdict = 'no_match';
            if ($score >= $exactMin) {
                $verdict = 'exact';
            } elseif ($score >= $probableMin) {
                $verdict = 'probable';
            }
            $classified[] = array_merge($c, ['verdict' => $verdict]);
        }

        $governance = new AiGovernanceService($this->root());
        $gov = $governance->propose(
            'mod_01_semantic_match',
            'Ești motor matching semantic Besoiu. Răspunde DOAR JSON: {"verdict":"exact|probable|no_match","confidence":0.0-1.0,"candidates":[{"label":"","score":0,"source_id":""}],"note_ro":""}',
            'Query: ' . $queryText . "\nCandidați:\n" . json_encode($classified, JSON_UNESCAPED_UNICODE),
            ['query' => $input, 'candidates' => $classified],
            ['user_id' => $options['user_id'] ?? null, 'action' => 'semantic_suggest', 'timeout' => 45]
        );

        return [
            'ok' => true,
            'requires_approval' => true,
            'auto_apply' => false,
            'query' => $queryText,
            'candidates' => $classified,
            'thresholds' => ['exact' => $exactMin, 'probable_min' => $probableMin],
            'embedding_model' => $emb['model'],
            'governance' => $gov,
            'log_id' => $gov['log_id'] ?? null,
        ];
    }
}
