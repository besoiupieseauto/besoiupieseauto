<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\OllamaLlmClient;
use Throwable;

/**
 * Interogare bibliotecă research — RAG + date structurate + citare sursă.
 */
final class AiMarketResearchQueryService
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
     * @return array<string, mixed>
     */
    public function ask(string $question, int $limit = 8): array
    {
        $question = trim($question);
        if ($question === '') {
            return ['ok' => false, 'error' => 'Întrebare goală'];
        }

        $store = new AiMarketResearchStore(null, $this->root());
        $corpus = new AiRagCorpusService($this->root());

        $tipFilter = $this->guessTipFilter($question);
        $dbHits = $store->search($question, $limit, $tipFilter);
        $corpusHits = $corpus->search($question, $limit);

        $sources = [];
        foreach ($dbHits as $row) {
            $sources[] = [
                'type' => 'market_entry',
                'url' => (string) ($row['url_sursa'] ?? ''),
                'scraped_at' => (string) ($row['data_scraping'] ?? ''),
                'tip_sursa' => (string) ($row['tip_sursa'] ?? ''),
                'processed' => $row['continut_procesat'] ?? null,
                'uuid' => (string) ($row['entry_uuid'] ?? ''),
                'confidence' => (float) ($row['confidence_extractie'] ?? 0),
            ];
        }
        foreach ($corpusHits as $hit) {
            if (($hit['source_type'] ?? '') !== 'market_research') {
                continue;
            }
            $sources[] = [
                'type' => 'corpus',
                'url' => (string) ($hit['url'] ?? ''),
                'scraped_at' => (string) ($hit['at'] ?? ''),
                'title' => (string) ($hit['title'] ?? ''),
                'snippet' => mb_substr((string) ($hit['text'] ?? ''), 0, 400),
            ];
        }

        $context = $this->buildContext($sources, $dbHits);
        $answer = $this->synthesize($question, $context);

        return [
            'ok' => true,
            'question' => $question,
            'answer' => $answer['text'],
            'sources' => array_slice($sources, 0, 10),
            'hits_count' => count($dbHits) + count($corpusHits),
            'ollama_ok' => $answer['ok'],
        ];
    }

    private function guessTipFilter(string $question): ?string
    {
        $q = mb_strtolower($question);
        if (preg_match('/\b(pret|preț|cost|concuren)/u', $q)) {
            return 'pret_concurenta';
        }
        if (preg_match('/\b(seo|cuvant|cuvânt|keyword|titlu)/u', $q)) {
            return 'seo_keyword';
        }
        if (preg_match('/\b(specifica|tehnic|oe|cod)/u', $q)) {
            return 'specificatie_tehnica';
        }
        if (preg_match('/\b(tendin|forum|cerere)/u', $q)) {
            return 'tendinta_piata';
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @param list<array<string, mixed>> $dbHits
     */
    private function buildContext(array $sources, array $dbHits): string
    {
        $lines = [];
        foreach ($dbHits as $i => $row) {
            $proc = $row['continut_procesat'] ?? [];
            if (!is_array($proc)) {
                $proc = [];
            }
            $lines[] = '[Sursa ' . ($i + 1) . '] ' . ($row['url_sursa'] ?? '')
                . ' | ' . ($row['data_scraping'] ?? '')
                . ' | ' . json_encode($proc, JSON_UNESCAPED_UNICODE);
        }
        if ($lines === []) {
            foreach ($sources as $i => $s) {
                $lines[] = '[Sursa ' . ($i + 1) . '] ' . ($s['url'] ?? '') . ' — ' . ($s['snippet'] ?? json_encode($s['processed'] ?? []));
            }
        }

        return implode("\n", $lines);
    }

    /** @return array{ok:bool,text:string} */
    private function synthesize(string $question, string $context): array
    {
        if ($context === '') {
            return ['ok' => true, 'text' => 'Nu am găsit date în biblioteca research. Rulează un job scraping pilot și validează extragerile.'];
        }

        $client = new OllamaLlmClient($this->root() . '/app');
        if (!$client->isEnabled()) {
            return ['ok' => false, 'text' => "Date găsite (fără sinteză Ollama):\n\n" . mb_substr($context, 0, 3000)];
        }

        $system = <<<'PROMPT'
Răspunzi la întrebări despre research piață piese auto (preț concurență, SEO, tendințe).
Folosește DOAR contextul furnizat. Citează sursele (URL + dată) în răspuns.
Dacă datele lipsesc, spune clar. NU inventa prețuri sau cuvinte cheie.
Răspuns în română, concis (max 8 propoziții).
PROMPT;

        try {
            $res = $client->complete($system, "Întrebare: {$question}\n\nContext:\n{$context}", 0.2, 60, 'qwen2.5:7b');
            $text = trim((string) ($res['content'] ?? ''));

            return ['ok' => !empty($res['ok']), 'text' => $text !== '' ? $text : 'Nu am putut sintetiza răspunsul.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'text' => 'Eroare Ollama: ' . $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $sourceSvc = new AiMarketResearchSourceService($this->root());
        $store = new AiMarketResearchStore(null, $this->root());
        $job = new AiMarketResearchJobService($this->root());

        $statsPath = $this->root() . '/admin/storage/ai_rag/market_source_stats.json';
        $stats = [];
        if (is_file($statsPath)) {
            $decoded = json_decode((string) file_get_contents($statsPath), true);
            $stats = is_array($decoded) ? $decoded : [];
        }

        $cfg = $sourceSvc->all();
        $audit = [];
        foreach ($cfg['sources'] ?? [] as $src) {
            if (!is_array($src)) {
                continue;
            }
            $id = (string) ($src['id'] ?? '');
            $st = $stats[$id] ?? ['success' => 0, 'fail' => 0, 'last_ok' => ''];
            $total = (int) ($st['success'] ?? 0) + (int) ($st['fail'] ?? 0);
            $rate = $total > 0 ? round(100 * (int) $st['success'] / $total, 1) : null;
            $alert = $total >= 3 && (int) ($st['fail'] ?? 0) >= 3 && (int) ($st['success'] ?? 0) === 0;
            $audit[] = array_merge($src, [
                'stats' => $st,
                'success_rate_pct' => $rate,
                'alert_blocked' => $alert,
            ]);
        }

        return [
            'sources' => $audit,
            'defaults' => $cfg['defaults'] ?? [],
            'store' => $store->auditSummary(),
            'progress' => $job->progress(),
            'stealth_available' => $this->stealthAvailable(),
        ];
    }

    private function stealthAvailable(): bool
    {
        $bootstrap = $this->root() . '/app/Import/Scraper/lib/bootstrap.php';
        if (is_file($bootstrap)) {
            require_once $bootstrap;
        }

        return class_exists('StealthBrowserClient') && \StealthBrowserClient::isAvailable();
    }
}
