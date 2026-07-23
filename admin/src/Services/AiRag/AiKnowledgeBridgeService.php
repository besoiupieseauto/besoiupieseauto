<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\AiAgentContextLibraryService;
use Besoiu\Services\OllamaLlmClient;
use Besoiu\Services\Products\ProduseService;
use Besoiu\Services\SectionAssistantSupplierQueries;
use Besoiu\Services\Seo\ProductSeoHtmlGenerator;
use Throwable;

/**
 * Punte BD internă ↔ cunoștințe online — bibliotecă unificată, dicționar import.
 */
final class AiKnowledgeBridgeService
{
    /** @var list<string> */
    private const INTERNAL_TYPES = ['produse', 'category', 'supplier', 'learned', 'furnizor', 'mysql'];

    /** @var list<string> */
    private const EXTERNAL_TYPES = ['epiesa', 'scrape', 'market_research', 'seo_keyword', 'pret_concurenta'];

    /** @var list<string> */
    private const KEYWORD_NOISE = [
        'cookie', 'cookies', 'salut', 'cont', 'cauta', 'browser', 'activeaza', 'dezactivate',
        'listafavorite', 'cosul', 'tau0', 'toate', 'categoriile', 'lei', 'urile', 'sunt',
        'gradina', 'scule', 'unelte', 'bercuit', 'extractie', 'iluminat', 'becuri', 'camion',
        'motocicleta', 'faruri', 'led', 'sorii', 'exterior', 'interior', 'antifurturi',
        'cauta', 'salut', 'cont', 'lista', 'favorite', 'cos', 'tau', 'toate', 'categoriile',
    ];

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

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $corpus = new AiRagCorpusService($this->root());
        $status = $corpus->status();
        $entries = $corpus->list(5000);

        $internal = 0;
        $external = 0;
        $withSeo = 0;
        $scraped = 0;
        $withUrl = 0;
        $byOrigin = ['intern' => [], 'extern' => []];

        foreach ($entries as $e) {
            $origin = $this->originOf($e);
            if ($origin === 'intern') {
                ++$internal;
            } else {
                ++$external;
            }
            $st = (string) ($e['source_type'] ?? 'other');
            $byOrigin[$origin][$st] = ($byOrigin[$origin][$st] ?? 0) + 1;

            if ($this->hasSeo($e)) {
                ++$withSeo;
            }
            if (in_array($st, ['scrape', 'market_research', 'pret_concurenta'], true)) {
                ++$scraped;
            }
            if (trim((string) ($e['url'] ?? '')) !== '') {
                ++$withUrl;
            }
        }

        $marketStore = new AiMarketResearchStore(null, $this->root());
        $marketAudit = $marketStore->auditSummary();

        $agentTotal = 0;
        try {
            $library = new AiAgentContextLibraryService($this->root());
            foreach (['agent-produse', 'agent-statistici', 'context-master'] as $slug) {
                $agentTotal += (int) ($library->summary($slug)['total'] ?? 0);
            }
        } catch (Throwable) {
            $agentTotal = 0;
        }

        $embeddings = 0;
        try {
            $emb = new AiProductEmbeddingStore(null, $this->root());
            $embStatus = $emb->status();
            $embeddings = (int) ($embStatus['total'] ?? 0);
        } catch (Throwable) {
            // optional
        }

        return [
            'corpus' => $status,
            'totals' => [
                'all' => (int) ($status['total'] ?? 0),
                'intern' => $internal,
                'extern' => $external,
                'seo' => $withSeo,
                'scraped' => $scraped + (int) ($marketAudit['total_entries'] ?? 0),
                'with_url' => $withUrl,
                'agent_library' => $agentTotal,
                'embeddings_produse' => $embeddings,
                'market_db' => (int) ($marketAudit['total_entries'] ?? 0),
            ],
            'by_origin' => $byOrigin,
            'by_source_type' => $status['by_source_type'] ?? [],
            'last_at' => $status['last_at'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $options q, origin, source_type, has_seo, offset, limit
     * @return array<string, mixed>
     */
    public function browse(array $options = []): array
    {
        $corpus = new AiRagCorpusService($this->root());
        $q = trim((string) ($options['q'] ?? ''));
        $origin = trim((string) ($options['origin'] ?? ''));
        $sourceType = trim((string) ($options['source_type'] ?? ''));
        $hasSeo = !empty($options['has_seo']);
        $offset = max(0, (int) ($options['offset'] ?? 0));
        $limit = max(1, min(50, (int) ($options['limit'] ?? 20)));

        $pool = $q !== '' ? $corpus->search($q, 200) : $corpus->list(5000);

        $filtered = [];
        foreach ($pool as $entry) {
            if ($origin !== '' && $this->originOf($entry) !== $origin) {
                continue;
            }
            if ($sourceType !== '' && (string) ($entry['source_type'] ?? '') !== $sourceType) {
                continue;
            }
            if ($hasSeo && !$this->hasSeo($entry)) {
                continue;
            }
            $filtered[] = $this->formatEntry($entry);
        }

        $total = count($filtered);
        $page = array_slice($filtered, $offset, $limit);

        return [
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'items' => $page,
        ];
    }

    /** @return array<string, mixed>|null */
    public function getEntry(string $id): ?array
    {
        $corpus = new AiRagCorpusService($this->root());
        foreach ($corpus->list(5000) as $entry) {
            if ((string) ($entry['id'] ?? '') === $id) {
                return $this->formatEntry($entry, true);
            }
        }

        return null;
    }

    /**
     * Dicționar import — găsește structură, SEO, referințe similare pentru un produs nou.
     *
     * @param array<string, mixed> $input name, category, oem, sku, brand
     * @return array<string, mixed>
     */
    public function importLookup(array $input): array
    {
        $name = trim((string) ($input['name'] ?? $input['raw_name'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $oem = trim((string) ($input['oem'] ?? ''));
        $productId = trim((string) ($input['product_id'] ?? ''));
        $productRow = null;
        if ($productId !== '') {
            try {
                $productRow = (new ProduseService())->getIdProduses($productId);
            } catch (Throwable) {
                $productRow = null;
            }
        }
        if ($productRow !== null) {
            if ($name === '') {
                $name = trim((string) ($productRow['pName'] ?? ''));
            }
            if ($category === '') {
                $category = trim((string) ($productRow['pCategory'] ?? ''));
            }
            if ($oem === '') {
                $oem = trim((string) ($productRow['pOem'] ?? $productRow['pCode'] ?? ''));
            }
        }

        $query = trim(implode(' ', array_filter([$name, $category, $oem])));

        if ($query === '') {
            return ['ok' => false, 'error' => 'Nume produs sau categorie necesar'];
        }

        $productCtx = $this->formatProductContext($productRow);

        $longTailPack = $this->buildLongTailKeywords($productCtx, $name, $category, $oem);
        $longTailPhrases = $longTailPack['fraze'];
        $shortTerms = $longTailPack['termeni'];

        $corpus = new AiRagCorpusService($this->root());
        $hits = $this->searchCorpusForProduct($corpus, $productCtx, $name, $category, $oem, $query);

        $internal = [];
        $external = [];
        $seoHints = [];

        foreach ($hits as $hit) {
            $formatted = $this->formatEntry($hit);
            if ($formatted['origin'] === 'intern') {
                $internal[] = $formatted;
            } else {
                $external[] = $formatted;
            }
            $seo = (array) ($hit['seo'] ?? []);
            if (!empty($seo['title'])) {
                $seoHints[] = (string) $seo['title'];
            }
            if (!empty($seo['description'])) {
                $seoHints[] = (string) $seo['description'];
            }
        }

        $structure = $this->inferStructure($name, $category, array_merge($internal, $external), $productCtx);

        $suggestion = $this->suggestForImport(
            $name,
            $category,
            $oem,
            array_merge($internal, $external),
            $longTailPhrases,
            $productRow,
            $longTailPhrases,
        );

        $structured = is_array($suggestion['structured'] ?? null) ? $suggestion['structured'] : [];
        if ($structured === [] || empty($suggestion['ok'])) {
            $heuristic = $this->buildHeuristicSeoStructured(
                $productCtx,
                $name,
                $category,
                $oem,
                $longTailPhrases,
                $shortTerms,
                array_merge($internal, $external),
            );
            $structured = $this->mergeStructuredSeo($heuristic, $structured);
            if (empty($suggestion['ok'])) {
                $suggestion['fallback'] = true;
                $suggestion['text'] = trim((string) ($suggestion['text'] ?? ''))
                    ?: 'Sugestie generată local (Ollama indisponibil sau fără răspuns).';
            }
            $suggestion['structured'] = $structured;
        }

        $seoPackage = $this->buildSeoImplementation($name, $category, $oem, $structured, $productRow);
        if ($seoPackage['recomandari_imbunatatire'] === []) {
            $seoPackage['recomandari_imbunatatire'] = $structure['recomandari_imbunatatire'] ?? [];
        }
        if ($seoPackage['keywords_seo']['fraze_long_tail'] === []) {
            $seoPackage['keywords_seo']['fraze_long_tail'] = $longTailPhrases;
            $seoPackage['keywords_seo']['termeni'] = $shortTerms;
            $allKw = array_values(array_unique(array_merge($longTailPhrases, $shortTerms)));
            $seoPackage['implementare_campuri']['keywords_seo'] = implode(', ', array_slice($allKw, 0, 12));
            if (!empty($allKw)) {
                $seoPackage['meta_tags']['keywords'] = implode(', ', array_slice($allKw, 0, 12));
            }
        }

        $seoHtml = ['ok' => false, 'error' => 'Selectează un produs din grid pentru generare HTML.'];
        if ($productCtx !== null && trim((string) ($productCtx['id'] ?? '')) !== '') {
            $seoHtml = (new ProductSeoHtmlGenerator($this->root()))->generateFromSeoPackage($productCtx, $seoPackage);
            if (!empty($seoHtml['categorii'])) {
                $seoPackage['categorii'] = $seoHtml['categorii'];
            }
        }

        return [
            'ok' => true,
            'query' => $query,
            'product' => $productCtx,
            'hits' => count($hits),
            'internal' => array_slice($internal, 0, 6),
            'external' => array_slice($external, 0, 6),
            'keywords_suggested' => $longTailPhrases,
            'keywords_terms' => $shortTerms,
            'keywords_strategy' => 'long_tail',
            'seo_hints' => array_slice(array_unique($seoHints), 0, 5),
            'structure' => $structure,
            'ai_suggestion' => $suggestion,
            'seo_package' => $seoPackage,
            'seo_html' => $seoHtml,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return array<string, mixed>
     */
    public function ask(string $question, array $sources = []): array
    {
        $question = trim($question);
        if ($question === '') {
            return ['ok' => false, 'error' => 'Întrebare goală'];
        }

        $supplierQuestion = $this->looksLikeSupplierQuestion($question);
        $liveSupplierAnswer = $supplierQuestion ? $this->formatSuppliersLiveAnswer() : '';

        $browse = $this->browse([
            'q' => $question,
            'limit' => 12,
            'source_type' => $supplierQuestion ? 'supplier' : '',
        ]);
        $items = $browse['items'] ?? [];
        if ($supplierQuestion && $items === []) {
            $browse = $this->browse(['q' => $question, 'limit' => 12]);
            $items = $browse['items'] ?? [];
        }

        $marketRows = [];
        try {
            $marketRows = (new AiMarketResearchStore(null, $this->root()))->search($question, 5);
        } catch (Throwable) {
            // optional
        }

        $contextLines = [];
        if ($liveSupplierAnswer !== '') {
            $contextLines[] = '[MySQL live / furnizori interni]' . "\n" . $liveSupplierAnswer;
        }

        $cited = [];
        foreach ($items as $i => $item) {
            $contextLines[] = '[#' . ($i + 1) . ' ' . ($item['origin'] ?? '') . '/' . ($item['source_type'] ?? '') . '] '
                . ($item['title'] ?? '') . ' — ' . mb_substr((string) ($item['text_preview'] ?? ''), 0, 300)
                . ($item['url'] ? ' | ' . $item['url'] : '');
            $cited[] = [
                'type' => 'corpus',
                'origin' => $item['origin'] ?? '',
                'source_type' => $item['source_type'] ?? '',
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? '',
                'scraped_at' => $item['at'] ?? '',
                'id' => $item['id'] ?? '',
            ];
        }
        foreach ($marketRows as $row) {
            $cited[] = [
                'type' => 'market_db',
                'url' => $row['url_sursa'] ?? '',
                'scraped_at' => $row['data_scraping'] ?? '',
                'processed' => $row['continut_procesat'] ?? null,
                'uuid' => $row['entry_uuid'] ?? '',
            ];
        }

        if ($supplierQuestion && $liveSupplierAnswer !== '') {
            $cited = array_merge([[
                'type' => 'mysql_live',
                'origin' => 'intern',
                'source_type' => 'supplier',
                'title' => 'Furnizori activi (MySQL)',
                'url' => '/admin/suppliers',
                'scraped_at' => date('c'),
                'id' => 'mysql-furnizori',
            ]], $cited);
        }

        $answer = $this->synthesizeAnswer($question, implode("\n", $contextLines));
        $answerText = trim((string) ($answer['text'] ?? ''));
        if ($supplierQuestion && $liveSupplierAnswer !== ''
            && ($answerText === '' || $answerText === 'Fără răspuns.')) {
            $answerText = $liveSupplierAnswer;
        } elseif ($supplierQuestion && $liveSupplierAnswer !== '' && $answerText !== '') {
            $answerText = $liveSupplierAnswer . "\n\n---\n\n" . $answerText;
        }

        return [
            'ok' => true,
            'question' => $question,
            'answer' => $answerText,
            'sources' => $cited,
            'hits_count' => count($items) + count($marketRows) + ($liveSupplierAnswer !== '' ? 1 : 0),
            'library_total' => (new AiRagCorpusService($this->root()))->status()['total'] ?? 0,
            'live_mysql' => $supplierQuestion ? [
                'suppliers_total' => SectionAssistantSupplierQueries::countSuppliers(),
            ] : null,
        ];
    }

    /**
     * Adaugă până la 5 surse URL + categorie și opțional scrape.
     *
     * @param list<array<string, mixed>> $sites name, url, category
     * @return array<string, mixed>
     */
    public function addSitesAndScrape(array $sites, bool $runScrape = true): array
    {
        $sourceSvc = new AiMarketResearchSourceService($this->root());
        $saved = $sourceSvc->upsertUserSites($sites);

        $scraped = [];
        if ($runScrape) {
            $scraper = new AiRagIntelligentScrapeService($this->root());
            foreach ($saved as $src) {
                $url = trim((string) ($src['url_template'] ?? ''));
                if ($url === '') {
                    continue;
                }
                try {
                    $res = $scraper->scrapeToCorpus($url, [
                        'source_id' => (string) ($src['id'] ?? ''),
                        'topic' => (string) ($src['category'] ?? $src['name'] ?? ''),
                        'follow_links' => true,
                        'summarize' => true,
                        'max_follow' => 2,
                    ]);
                    $scraped[] = [
                        'source_id' => $src['id'] ?? '',
                        'url' => $url,
                        'ok' => !empty($res['ok']),
                        'fragments' => (int) ($res['fragments_added'] ?? 0),
                        'error' => $res['error'] ?? '',
                    ];
                } catch (Throwable $e) {
                    $scraped[] = ['source_id' => $src['id'] ?? '', 'url' => $url, 'ok' => false, 'error' => $e->getMessage()];
                }
                $sourceSvc->humanDelay();
            }
        }

        return [
            'ok' => true,
            'saved_sources' => $saved,
            'scraped' => $scraped,
            'overview' => $this->overview(),
        ];
    }

    /** @param array<string, mixed> $entry */
    private function originOf(array $entry): string
    {
        $st = (string) ($entry['source_type'] ?? '');
        if (in_array($st, self::INTERNAL_TYPES, true)) {
            return 'intern';
        }
        if (in_array($st, self::EXTERNAL_TYPES, true)) {
            return 'extern';
        }
        $tags = (array) ($entry['tags'] ?? []);
        if (in_array('mysql', $tags, true) || in_array('produse', $tags, true)) {
            return 'intern';
        }

        return 'extern';
    }

    /** @param array<string, mixed> $entry */
    private function hasSeo(array $entry): bool
    {
        $seo = (array) ($entry['seo'] ?? []);
        if (!empty($seo['title']) || !empty($seo['description']) || !empty($seo['h1'])) {
            return true;
        }

        return count((array) ($entry['keywords'] ?? [])) >= 3;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function formatEntry(array $entry, bool $full = false): array
    {
        $text = (string) ($entry['text'] ?? '');
        $seo = (array) ($entry['seo'] ?? []);

        $formatted = [
            'id' => (string) ($entry['id'] ?? ''),
            'at' => (string) ($entry['at'] ?? ''),
            'origin' => $this->originOf($entry),
            'source_type' => (string) ($entry['source_type'] ?? ''),
            'source_id' => (string) ($entry['source_id'] ?? ''),
            'title' => (string) ($entry['title'] ?? ''),
            'text_preview' => mb_substr($text, 0, $full ? 8000 : 220),
            'keywords' => array_slice((array) ($entry['keywords'] ?? []), 0, 12),
            'tags' => (array) ($entry['tags'] ?? []),
            'url' => (string) ($entry['url'] ?? ''),
            'image_url' => (string) ($entry['image_url'] ?? ''),
            'has_seo' => $this->hasSeo($entry),
            'seo' => [
                'title' => (string) ($seo['title'] ?? ''),
                'description' => (string) ($seo['description'] ?? ''),
                'h1' => (string) ($seo['h1'] ?? ''),
            ],
            'links_count' => count((array) ($entry['links'] ?? [])),
            'pinned' => !empty($entry['pinned']),
            'rag_score' => $entry['rag_score'] ?? null,
        ];

        if ($full) {
            $formatted['text_full'] = $text;
            $formatted['links'] = (array) ($entry['links'] ?? []);
        }

        return $formatted;
    }

    /**
     * @param list<array<string, mixed>> $hits
     * @param array<string, mixed>|null $productCtx
     * @return array<string, mixed>
     */
    private function inferStructure(string $name, string $category, array $hits, ?array $productCtx = null): array
    {
        $similarTitle = '';
        $similarCategory = $category;
        $best = $this->findBestSimilarHit($hits, $productCtx, $name);
        if ($best !== null) {
            $similarTitle = (string) ($best['title'] ?? '');
        }

        return [
            'nume_sugerat' => $name !== '' ? $name : $similarTitle,
            'categorie' => $similarCategory,
            'format_titlu' => $similarTitle !== '' ? 'Similar: ' . $similarTitle : 'Nume | Brand | Cod OEM | Categorie',
            'campuri_recomandate' => [
                'nume',
                'cod_oem',
                'cod_articol',
                'brand',
                'categorie',
                'descriere_scurta',
                'descriere_lunga',
                'keywords_seo',
                'meta_title',
                'meta_description',
                'schema_product_jsonld',
            ],
            'recomandari_imbunatatire' => [
                'Extinderea descrierii: adaugă descriere_lunga cu norme API/ACEA, intervale de schimb și vehicule compatibile specifice.',
                'Keywords SEO: folosește fraze de căutare (long-tail), nu doar cuvinte izolate — ex. «ulei original toyota 5w30 5 litri».',
                'Date structurate: generează Schema.org Product (preț, stoc, brand, SKU/OEM) pentru rich results Google.',
                'Gestionarea codurilor: distinge clar cod OEM (mpn) vs cod articol (sku) — expune-le în meta tag-uri și JSON-LD.',
            ],
            'nota' => 'Completează indirect din structura produselor similare din bibliotecă — AI propune, tu validezi.',
        ];
    }

    /** @param array<string, mixed>|null $row @return array<string, mixed>|null */
    private function formatProductContext(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $priceRaw = trim((string) ($row['pPrice'] ?? ''));
        $priceNum = preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $priceRaw));
        $stock = (int) ($row['pStock'] ?? 0);

        return [
            'id' => (string) ($row['randomn_id'] ?? $row['id'] ?? ''),
            'name' => trim((string) ($row['pName'] ?? '')),
            'code' => trim((string) ($row['pCode'] ?? '')),
            'oem' => trim((string) ($row['pOem'] ?? '')),
            'brand' => trim((string) ($row['pBrand'] ?? '')),
            'category' => trim((string) ($row['pCategory'] ?? '')),
            'subcategory' => trim((string) ($row['pSubcategory'] ?? '')),
            'price' => $priceRaw,
            'price_numeric' => is_numeric($priceNum) ? (float) $priceNum : null,
            'stock' => $stock,
            'in_stock' => $stock > 0,
        ];
    }

    /**
     * @param array<string, mixed> $structured
     * @param array<string, mixed>|null $productRow
     * @return array<string, mixed>
     */
    private function buildSeoImplementation(
        string $name,
        string $category,
        string $oem,
        array $structured,
        ?array $productRow,
    ): array {
        $ctx = $this->formatProductContext($productRow) ?? [];
        $title = trim((string) ($structured['titlu_normalizat'] ?? $name));
        $shortDesc = trim((string) ($structured['descriere_scurta'] ?? ''));
        $longDesc = trim((string) ($structured['descriere_lunga'] ?? ''));
        $metaDesc = trim((string) ($structured['meta_description'] ?? $shortDesc));
        $brand = trim((string) ($structured['brand'] ?? $ctx['brand'] ?? ''));
        $sku = trim((string) ($structured['cod_articol'] ?? $ctx['code'] ?? ''));
        $mpn = trim((string) ($structured['cod_oem'] ?? ($oem !== '' ? $oem : ($ctx['oem'] ?? ''))));
        $seoKeywords = is_array($structured['cuvinte_seo'] ?? null) ? $structured['cuvinte_seo'] : [];
        $seoLongTail = is_array($structured['cuvinte_seo_long_tail'] ?? null) ? $structured['cuvinte_seo_long_tail'] : [];
        $allKeywords = array_values(array_unique(array_filter(array_merge($seoLongTail, $seoKeywords))));

        $price = $ctx['price_numeric'] ?? null;
        $inStock = !empty($ctx['in_stock']);

        $nivel1 = trim((string) ($ctx['category'] ?? $category));
        $nivel2 = trim((string) ($structured['categorie_nivel_2'] ?? $ctx['subcategory'] ?? ''));
        if ($nivel2 === '' && !empty($structured['categorie_sugerata'])) {
            $sug = trim((string) $structured['categorie_sugerata']);
            if ($sug !== '' && $sug !== $nivel1) {
                $nivel2 = $sug;
            }
        }
        $categorii = [
            'nivel_1' => $nivel1,
            'nivel_2' => $nivel2,
            'produs' => $title,
            'breadcrumb' => array_values(array_filter([$nivel1, $nivel2, $title])),
            'breadcrumb_html' => implode(' › ', array_values(array_filter([$nivel1, $nivel2, $title]))),
        ];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $title,
            'description' => $longDesc !== '' ? $longDesc : $shortDesc,
            'category' => $nivel2 !== '' ? $nivel2 : $nivel1,
        ];
        if ($brand !== '') {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }
        if ($sku !== '') {
            $schema['sku'] = $sku;
        }
        if ($mpn !== '') {
            $schema['mpn'] = $mpn;
        }
        if ($price !== null && $price > 0) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => 'RON',
                'price' => number_format($price, 2, '.', ''),
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $ctx['id'] !== '' ? '/produs?id=' . rawurlencode((string) $ctx['id']) : '',
            ];
        }

        $metaTags = [
            'title' => $title,
            'description' => $metaDesc,
            'keywords' => implode(', ', array_slice($allKeywords, 0, 12)),
            'product:oem' => $mpn,
            'product:sku' => $sku,
            'product:brand' => $brand,
        ];

        $recomandari = is_array($structured['recomandari_imbunatatire'] ?? null)
            ? $structured['recomandari_imbunatatire']
            : [];

        return [
            'meta_tags' => array_filter($metaTags, static fn ($v): bool => trim((string) $v) !== ''),
            'schema_json_ld' => $schema,
            'coduri' => [
                'oem' => $mpn,
                'cod_articol' => $sku,
                'expunere' => 'meta product:oem, product:sku + schema.org mpn/sku în JSON-LD',
            ],
            'descrieri' => [
                'scurta' => $shortDesc,
                'lunga' => $longDesc,
            ],
            'keywords_seo' => [
                'fraze_long_tail' => $seoLongTail,
                'termeni' => $seoKeywords,
            ],
            'categorii' => $categorii,
            'recomandari_imbunatatire' => $recomandari,
            'implementare_campuri' => [
                'pName' => $title,
                'pCategory' => $nivel1,
                'pSubcategory' => $nivel2,
                'pOem' => $mpn,
                'pCode' => $sku,
                'pBrand' => $brand,
                'descriere_scurta' => $shortDesc,
                'descriere_lunga' => $longDesc,
                'keywords_seo' => implode(', ', array_slice($allKeywords, 0, 12)),
                'meta_title' => $title,
                'meta_description' => $metaDesc,
            ],
        ];
    }

    /**
     * @param list<string> $longTailPhrases
     * @param list<string> $keywords
     * @return array<string, mixed>
     */
    private function suggestForImport(
        string $name,
        string $category,
        string $oem,
        array $hits,
        array $keywords,
        ?array $productRow = null,
        array $longTailPhrases = [],
    ): array {
        $client = new OllamaLlmClient($this->root() . '/app');
        if (!$client->isEnabled()) {
            return [
                'ok' => false,
                'text' => 'Ollama indisponibil — se folosește sugestia locală long-tail.',
            ];
        }

        $ctx = [];
        foreach (array_slice($hits, 0, 6) as $h) {
            $title = trim((string) ($h['title'] ?? ''));
            if ($title === '' || str_contains(mb_strtolower($title, 'UTF-8'), 'agent-produse')) {
                continue;
            }
            $ctx[] = ($h['origin'] ?? '') . ': ' . $title . ' — ' . mb_substr((string) ($h['text_preview'] ?? ''), 0, 180);
        }

        $productCtx = $this->formatProductContext($productRow);

        $system = <<<'PROMPT'
Ești expert SEO auto e-commerce Besoiu Piese Auto — dicționar intern BD ↔ piață online.
Primești produs + fragmente RAG + fraze long-tail deja generate. Răspunde JSON strict (fără markdown):
{
  "titlu_normalizat": "string — titlu vitrină optimizat",
  "descriere_scurta": "string max 2 propoziții pentru card produs",
  "descriere_lunga": "string — specificații tehnice: norme API/ACEA, volum, intervale schimb, vehicule compatibile (doar dacă reiese din context, nu inventa)",
  "meta_description": "string max 160 caractere pentru Google",
  "cuvinte_seo": ["max 6 termeni scurți relevanți produsului"],
  "cuvinte_seo_long_tail": ["max 8 fraze de căutare reale — NU cuvinte izolate"],
  "categorie_nivel_1": "string — categorie principală (ex: Produse auto universale)",
  "categorie_nivel_2": "string — subcategorie (ex: Ulei motor)",
  "categorie_sugerata": "string — alias nivel 2",
  "cod_oem": "string — DOAR din input/bibliotecă, altfel gol",
  "cod_articol": "string — cod SKU/referință articol, DOAR din input/bibliotecă",
  "brand": "string",
  "completari_indirecte": ["deducții din structuri similare, fără coduri inventate"],
  "recomandari_imbunatatire": ["3-5 recomandări concrete SEO"],
  "incredere": 0.0-1.0
}
NU inventa cod OEM/SKU. cuvinte_seo_long_tail trebuie să fie FRAZE (ex: «ulei original toyota 5w30 5 litri»), nu liste de cuvinte din meniuri site.
NU copia cuvinte zgomot: cookie, salut, cont, browser, categorii meniu.
PROMPT;

        $user = json_encode([
            'produs' => $name,
            'categorie' => $category,
            'oem' => $oem,
            'produs_bd' => $productCtx,
            'keywords_long_tail_sugerate' => $longTailPhrases !== [] ? $longTailPhrases : $keywords,
            'context_rag' => $ctx,
        ], JSON_UNESCAPED_UNICODE);

        try {
            $res = $client->complete($system, (string) $user, 0.15, 90, 'qwen2.5:7b');
            $raw = (string) ($res['content'] ?? '');
            $parsed = null;
            if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
                $parsed = json_decode($m[0], true);
            }

            return [
                'ok' => !empty($res['ok']) && is_array($parsed),
                'structured' => is_array($parsed) ? $parsed : null,
                'raw' => $raw,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'text' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed>|null $productCtx
     * @return list<string>
     */
    private function buildImportSearchQueries(?array $productCtx, string $name, string $category, string $oem, string $fallbackQuery): array
    {
        $brand = trim((string) ($productCtx['brand'] ?? ''));
        $sub = trim((string) ($productCtx['subcategory'] ?? ''));
        $attrs = $this->parseProductAttributes($name, $brand, $sub, $category);
        $queries = [];

        if ($sub !== '' && $brand !== '') {
            $queries[] = $sub . ' ' . $brand;
        }
        if ($attrs['product_type'] !== '' && $brand !== '') {
            $queries[] = $attrs['product_type'] . ' ' . $brand;
        }
        if ($attrs['product_type'] !== '' && $attrs['viscosity'] !== '') {
            $queries[] = $attrs['product_type'] . ' ' . $attrs['viscosity'] . ' ' . $brand;
        }
        if ($name !== '') {
            $queries[] = $name;
        }
        if ($oem !== '') {
            $queries[] = $oem;
        }
        $queries[] = $fallbackQuery;

        $out = [];
        foreach ($queries as $q) {
            $q = trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
            if ($q !== '' && !in_array($q, $out, true)) {
                $out[] = $q;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $productCtx
     * @return list<array<string, mixed>>
     */
    private function searchCorpusForProduct(
        AiRagCorpusService $corpus,
        ?array $productCtx,
        string $name,
        string $category,
        string $oem,
        string $fallbackQuery,
    ): array {
        $queries = $this->buildImportSearchQueries($productCtx, $name, $category, $oem, $fallbackQuery);
        $merged = [];
        foreach ($queries as $q) {
            foreach ($corpus->search($q, 12) as $hit) {
                $id = (string) ($hit['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                if (!isset($merged[$id])) {
                    $merged[$id] = $hit;
                } else {
                    $merged[$id]['rag_score'] = max(
                        (int) ($merged[$id]['rag_score'] ?? 0),
                        (int) ($hit['rag_score'] ?? 0),
                    );
                }
            }
        }

        $hits = $this->rerankHitsForProduct(array_values($merged), $productCtx, $name);

        return array_slice($hits, 0, 15);
    }

    /**
     * @param array<string, mixed>|null $productCtx
     * @param list<array<string, mixed>> $hits
     * @return list<array<string, mixed>>
     */
    private function rerankHitsForProduct(array $hits, ?array $productCtx, string $name): array
    {
        foreach ($hits as &$hit) {
            $hit['product_relevance'] = $this->scoreHitRelevanceForProduct($hit, $productCtx, $name);
        }
        unset($hit);

        usort($hits, static function (array $a, array $b): int {
            $rel = ($b['product_relevance'] ?? 0) <=> ($a['product_relevance'] ?? 0);
            if ($rel !== 0) {
                return $rel;
            }

            return ((int) ($b['rag_score'] ?? 0)) <=> ((int) ($a['rag_score'] ?? 0));
        });

        return $hits;
    }

    /**
     * @param array<string, mixed> $hit
     * @param array<string, mixed>|null $productCtx
     */
    private function scoreHitRelevanceForProduct(array $hit, ?array $productCtx, string $name): int
    {
        $title = mb_strtolower((string) ($hit['title'] ?? ''), 'UTF-8');
        $brand = mb_strtolower(trim((string) ($productCtx['brand'] ?? '')), 'UTF-8');
        $sub = trim((string) ($productCtx['subcategory'] ?? ''));
        $category = trim((string) ($productCtx['category'] ?? ''));
        $attrs = $this->parseProductAttributes($name, (string) ($productCtx['brand'] ?? ''), $sub, $category);

        $score = (int) ($hit['rag_score'] ?? 0);

        if ($title === '' || $title === 'agent-produse') {
            return $score - 15;
        }
        if (str_contains($title, ' — epiesa.ro') && !str_contains($title, $brand) && $brand !== '') {
            $score -= 4;
        }
        if ($brand !== '' && str_contains($title, $brand)) {
            $score += 12;
        }
        if ($attrs['viscosity'] !== '' && str_contains($title, $attrs['viscosity'])) {
            $score += 10;
        }
        if ($attrs['product_type'] !== '' && str_contains($title, $attrs['product_type'])) {
            $score += 8;
        }
        if ($attrs['product_type'] === 'ulei motor' && str_contains($title, 'spray')) {
            $score -= 25;
        }
        if ($attrs['product_type'] === 'ulei motor' && str_contains($title, 'ulei motor')) {
            $score += 10;
        }
        if (str_contains($title, 'uleiuri si lubrifianti') || str_contains($title, 'toate categoriile')) {
            $score -= 8;
        }

        foreach ($this->productSearchTokens($name, (string) ($productCtx['brand'] ?? ''), $sub) as $token) {
            if (str_contains($title, $token)) {
                $score += 3;
            }
        }

        return $score;
    }

    /**
     * @param list<array<string, mixed>> $hits
     * @param array<string, mixed>|null $productCtx
     * @return array<string, mixed>|null
     */
    private function findBestSimilarHit(array $hits, ?array $productCtx, string $name): ?array
    {
        $ranked = $this->rerankHitsForProduct($hits, $productCtx, $name);
        foreach ($ranked as $hit) {
            $title = trim((string) ($hit['title'] ?? ''));
            if ($title === '' || $title === 'agent-produse') {
                continue;
            }
            if ((int) ($hit['product_relevance'] ?? 0) < 5) {
                continue;
            }

            return $hit;
        }

        return null;
    }

    /** @return list<string> */
    private function productSearchTokens(string $name, string $brand, string $subcategory): array
    {
        $skip = ['nou', 'noi', 'auto', 'produs', 'produse', 'universal', 'universale', 'fuel', 'ec'];
        $tokens = [];
        $raw = mb_strtolower($name . ' ' . $brand . ' ' . $subcategory, 'UTF-8');
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $t) {
            if (mb_strlen($t) < 2 || in_array($t, $skip, true)) {
                continue;
            }
            $tokens[] = $t;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array{product_type:string,viscosity:string,volume_litri:string,brand_norm:string}
     */
    private function parseProductAttributes(string $name, string $brand, string $subcategory, string $category): array
    {
        $nameLower = mb_strtolower($name, 'UTF-8');
        $hay = mb_strtolower($subcategory . ' ' . $category . ' ' . $name, 'UTF-8');

        $viscosity = '';
        if (preg_match('/(\d+w-?\d+)/i', $name, $m)) {
            $viscosity = mb_strtolower(str_replace('-', '', $m[1]), 'UTF-8');
        }

        $volumeLitri = '';
        if (preg_match('/(\d+)\s*l(?:itri?)?(?:\b|-|\s)/i', $name, $m)) {
            $volumeLitri = $m[1] . ' litri';
        }

        $productType = '';
        if (str_contains($hay, 'ulei motor') || (str_contains($hay, 'ulei') && !str_contains($hay, 'filtru'))) {
            $productType = 'ulei motor';
        } elseif (str_contains($hay, 'filtru ulei') || str_contains($hay, 'filtru de ulei')) {
            $productType = 'filtru ulei';
        } elseif (str_contains($hay, 'filtru')) {
            $productType = 'filtru';
        } elseif (str_contains($hay, 'anvelop')) {
            $productType = 'anvelope';
        } elseif (str_contains($hay, 'baterie')) {
            $productType = 'baterie auto';
        }

        return [
            'product_type' => $productType,
            'viscosity' => $viscosity,
            'volume_litri' => $volumeLitri,
            'brand_norm' => mb_strtolower(trim($brand), 'UTF-8'),
        ];
    }

    /**
     * @param array<string, mixed>|null $productCtx
     * @return array{fraze:list<string>,termeni:list<string>}
     */
    private function buildLongTailKeywords(?array $productCtx, string $name, string $category, string $oem): array
    {
        $brand = trim((string) ($productCtx['brand'] ?? ''));
        $sub = trim((string) ($productCtx['subcategory'] ?? ''));
        $attrs = $this->parseProductAttributes($name, $brand, $sub, $category);
        $brandNorm = $attrs['brand_norm'];
        $fraze = [];

        if ($attrs['product_type'] === 'ulei motor') {
            if ($brandNorm !== '' && $attrs['viscosity'] !== '' && $attrs['volume_litri'] !== '') {
                $fraze[] = "ulei original {$brandNorm} {$attrs['viscosity']} {$attrs['volume_litri']}";
                $fraze[] = "ulei motor {$brandNorm} {$attrs['viscosity']} {$attrs['volume_litri']}";
            }
            if ($brandNorm !== '' && $attrs['viscosity'] !== '') {
                $fraze[] = "ulei sintetic motor {$brandNorm} {$attrs['viscosity']}";
                $fraze[] = "ulei motor {$brandNorm} {$attrs['viscosity']}";
                if ($attrs['volume_litri'] !== '') {
                    $fraze[] = "ulei {$brandNorm} {$attrs['viscosity']} {$attrs['volume_litri']}";
                }
            }
            if ($brandNorm !== '') {
                $fraze[] = "ulei original {$brandNorm} motor";
                $compact = trim(preg_replace('/\s+/u', ' ', mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name) ?? $name, 'UTF-8')) ?? '');
                if ($compact !== '') {
                    $fraze[] = 'ulei motor ' . $compact;
                }
            }
        } elseif ($attrs['product_type'] !== '') {
            if ($brandNorm !== '') {
                $fraze[] = mb_strtolower($attrs['product_type'] . ' ' . $brandNorm . ' original', 'UTF-8');
                $fraze[] = mb_strtolower($attrs['product_type'] . ' ' . $brandNorm, 'UTF-8');
            }
            $fraze[] = mb_strtolower(trim($attrs['product_type'] . ' ' . $name), 'UTF-8');
        } else {
            $fraze[] = mb_strtolower(trim($name . ' ' . $brand), 'UTF-8');
            if ($category !== '') {
                $fraze[] = mb_strtolower(trim($name . ' ' . $category), 'UTF-8');
            }
        }

        if ($oem !== '' && $brandNorm !== '') {
            $fraze[] = "cod oem {$oem} {$brandNorm}";
            if ($attrs['product_type'] !== '') {
                $fraze[] = mb_strtolower("{$attrs['product_type']} {$brandNorm} {$oem}", 'UTF-8');
            }
        }
        if ($oem !== '') {
            $fraze[] = "cod {$oem}";
        }

        $fraze = array_values(array_unique(array_filter(array_map(
            static fn (string $f): string => trim(preg_replace('/\s+/u', ' ', $f) ?? $f),
            $fraze,
        ), static fn (string $f): bool => mb_strlen($f) >= 8)));

        $termeni = [];
        if ($attrs['product_type'] !== '') {
            $termeni[] = $attrs['product_type'];
        }
        if ($brandNorm !== '') {
            $termeni[] = $brandNorm;
        }
        if ($attrs['viscosity'] !== '') {
            $termeni[] = $attrs['viscosity'];
        }
        if ($attrs['volume_litri'] !== '') {
            $termeni[] = str_replace(' litri', 'l', $attrs['volume_litri']);
        }
        if ($oem !== '') {
            $termeni[] = $oem;
        }

        return [
            'fraze' => array_slice($fraze, 0, 10),
            'termeni' => array_values(array_unique(array_filter($termeni))),
        ];
    }

    /**
     * @param list<string> $longTailPhrases
     * @param list<string> $shortTerms
     * @param list<array<string, mixed>> $hits
     * @param array<string, mixed>|null $productCtx
     * @return array<string, mixed>
     */
    private function buildHeuristicSeoStructured(
        ?array $productCtx,
        string $name,
        string $category,
        string $oem,
        array $longTailPhrases,
        array $shortTerms,
        array $hits,
    ): array {
        $brand = trim((string) ($productCtx['brand'] ?? ''));
        $sub = trim((string) ($productCtx['subcategory'] ?? ''));
        $code = trim((string) ($productCtx['code'] ?? ''));
        $attrs = $this->parseProductAttributes($name, $brand, $sub, $category);
        $title = $this->normalizeProductTitle($name, $brand, $attrs);

        $shortDesc = '';
        $longDesc = '';
        if ($attrs['product_type'] === 'ulei motor') {
            $parts = array_filter([
                $brand !== '' ? 'Ulei motor original ' . $brand : 'Ulei motor',
                $attrs['viscosity'] !== '' ? $attrs['viscosity'] : null,
                $attrs['volume_litri'] !== '' ? $attrs['volume_litri'] : null,
            ]);
            $shortDesc = implode(', ', $parts) . '.';
            if ($oem !== '') {
                $shortDesc .= ' Cod OEM ' . $oem . '.';
            }
            $longDesc = $shortDesc . ' ';
            $longDesc .= 'Verificați compatibilitatea cu motorizarea dvs. și normele API/ACEA recomandate de producător.';
            if ($attrs['volume_litri'] !== '') {
                $longDesc .= ' Ambalaj ' . $attrs['volume_litri'] . '.';
            }
            $longDesc .= ' Interval de schimb conform manualului service ' . ($brand !== '' ? $brand : 'auto') . '.';
        } else {
            $shortDesc = trim($title . ($oem !== '' ? '. Cod OEM ' . $oem : '.'));
            $longDesc = $shortDesc . ' Produs din categoria ' . ($sub !== '' ? $sub : $category) . '.';
        }

        $metaDesc = mb_substr(trim($shortDesc), 0, 160);
        $similar = $this->findBestSimilarHit($hits, $productCtx, $name);
        $completari = [];
        if ($similar !== null && !empty($similar['seo']['description'])) {
            $completari[] = 'Structură meta similară: ' . mb_substr((string) $similar['seo']['description'], 0, 120);
        }
        if ($sub !== '') {
            $completari[] = 'Subcategorie BD: ' . $sub;
        }

        return [
            'titlu_normalizat' => $title,
            'descriere_scurta' => $shortDesc,
            'descriere_lunga' => $longDesc,
            'meta_description' => $metaDesc,
            'cuvinte_seo' => $shortTerms,
            'cuvinte_seo_long_tail' => $longTailPhrases,
            'categorie_nivel_1' => trim((string) ($productCtx['category'] ?? $category)),
            'categorie_nivel_2' => $sub,
            'categorie_sugerata' => $sub !== '' ? $sub : $category,
            'cod_oem' => $oem,
            'cod_articol' => $code !== '' ? $code : $oem,
            'brand' => $brand,
            'completari_indirecte' => $completari,
            'recomandari_imbunatatire' => [
                'Completați normele API/ACEA exacte de pe eticheta produsului în descrierea lungă.',
                'Păstrați keywords long-tail ca fraze (ex: «ulei original toyota 5w30 5 litri»), nu cuvinte izolate din meniuri site.',
                'Adăugați prețul în BD pentru Schema.org Offer complet în Google.',
                'Verificați distincția cod OEM (mpn) vs cod articol intern (sku) dacă diferă.',
            ],
            'incredere' => 0.55,
            'sursa' => 'heuristic_local',
        ];
    }

    /**
     * @param array{product_type:string,viscosity:string,volume_litri:string,brand_norm:string} $attrs
     */
    private function normalizeProductTitle(string $name, string $brand, array $attrs): string
    {
        $title = trim($name);
        if ($brand !== '' && !str_contains(mb_strtolower($title, 'UTF-8'), mb_strtolower($brand, 'UTF-8'))) {
            $title = $brand . ' ' . $title;
        }
        if ($attrs['product_type'] === 'ulei motor' && !str_contains(mb_strtolower($title, 'UTF-8'), 'ulei')) {
            $title = 'Ulei motor ' . $title;
        }

        return trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    private function mergeStructuredSeo(array $base, array $overlay): array
    {
        $merged = $base;
        foreach ($overlay as $key => $value) {
            if (is_array($value)) {
                $baseVal = is_array($merged[$key] ?? null) ? $merged[$key] : [];
                $filtered = array_values(array_filter($value, static fn ($v): bool => trim((string) $v) !== ''));
                if ($filtered !== []) {
                    $merged[$key] = array_values(array_unique(array_merge($filtered, $baseVal)));
                }
                continue;
            }
            if (trim((string) $value) !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function looksLikeSupplierQuestion(string $question): bool
    {
        $lower = mb_strtolower(trim($question), 'UTF-8');
        if ($lower === '') {
            return false;
        }

        return (bool) preg_match('/\b(furnizor\w*|supplier\w*)\b/u', $lower)
            && (bool) preg_match('/\b(ce|c[âa]te|care|lista|list[aă]|intern\w*|proiect|avem|sunt|activ\w*)\b/u', $lower);
    }

    private function formatSuppliersLiveAnswer(): string
    {
        $total = SectionAssistantSupplierQueries::countSuppliers();
        if ($total <= 0) {
            return 'Nu am găsit furnizori activi în MySQL. Verifică Admin → Furnizori sau indexează furnizorii în RAG.';
        }

        $suppliers = SectionAssistantSupplierQueries::listSuppliers(50);
        $lines = [
            'Furnizori interni activi în proiect (MySQL live): **' . $total . '** total.',
            '',
        ];
        foreach ($suppliers as $i => $supplier) {
            $name = trim((string) ($supplier['name'] ?? ''));
            $code = trim((string) ($supplier['code'] ?? ''));
            if ($name === '') {
                continue;
            }
            $lines[] = ($i + 1) . '. **' . $name . '**'
                . ($code !== '' && $code !== '-' ? ' — cod `' . $code . '`' : '');
        }
        if ($total > count($suppliers)) {
            $lines[] = '';
            $lines[] = '… și încă **' . ($total - count($suppliers)) . '** furnizori (vezi Admin → Lista furnizori).';
        }
        $lines[] = '';
        $lines[] = 'Sursă: tabel `furnizori` — nu depinde de produse publicate în magazin.';

        return implode("\n", $lines);
    }

    /** @return array{ok:bool,text:string} */
    private function synthesizeAnswer(string $question, string $context): array
    {
        if ($context === '') {
            return [
                'ok' => true,
                'text' => 'Biblioteca are date, dar nimic relevant pentru această întrebare. Încearcă filtre (intern/extern) sau adaugă surse URL.',
            ];
        }

        $client = new OllamaLlmClient($this->root() . '/app');
        if (!$client->isEnabled()) {
            return ['ok' => false, 'text' => "Rezultate din bibliotecă:\n\n" . mb_substr($context, 0, 3500)];
        }

        $system = <<<'PROMPT'
Răspunzi despre biblioteca de cunoștințe Besoiu (BD internă + date online scrapuite).
Folosește DOAR contextul. Citează sursa (intern/extern + titlu/URL).
Nu inventa prețuri sau coduri. Română, clar, max 10 propoziții.
PROMPT;

        try {
            $res = $client->complete($system, "Întrebare: {$question}\n\nBibliotecă:\n{$context}", 0.2, 60, 'qwen2.5:7b');

            return [
                'ok' => !empty($res['ok']),
                'text' => trim((string) ($res['content'] ?? '')) ?: 'Fără răspuns.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'text' => $e->getMessage()];
        }
    }
}
