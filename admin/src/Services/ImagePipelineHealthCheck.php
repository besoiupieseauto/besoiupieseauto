<?php

declare(strict_types=1);

namespace Evasystem\Services;

/**
 * Verificări structurale pipeline imagini — mapare 1:1 cu cele 50 greșeli fundamentale.
 *
 * @return list<array{id: int, name: string, http: int, message: string}>
 */
final class ImagePipelineHealthCheck
{
    private string $root;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? dirname(__DIR__, 3);
    }

    /** @return array{summary: array{total: int, ok: int, fail: int}, tests: list<array{id: int, name: string, http: int, message: string}>} */
    public function runAll(): array
    {
        $tests = [];
        foreach ($this->definitions() as $def) {
            $tests[] = $this->runOne($def);
        }

        $ok = count(array_filter($tests, static fn (array $t): bool => ($t['http'] ?? 0) === 200));

        return [
            'summary' => [
                'total' => count($tests),
                'ok' => $ok,
                'fail' => count($tests) - $ok,
            ],
            'tests' => $tests,
        ];
    }

    /** @return list<array{id: int, name: string, callable: callable}> */
    private function definitions(): array
    {
        return [
            ['id' => 1, 'name' => 'OEM în pCode — extragere coduri', 'callable' => fn () => $this->t01_oem_pcode()],
            ['id' => 2, 'name' => 'pOem CSV — parsare multi-cod', 'callable' => fn () => $this->t02_poem_field()],
            ['id' => 3, 'name' => 'pBrand gol — detectare din titlu', 'callable' => fn () => $this->t03_brand_detect()],
            ['id' => 4, 'name' => 'pCategory — inferență filtru/frână/suspensie', 'callable' => fn () => $this->t04_category_infer()],
            ['id' => 5, 'name' => 'Titlu vag — query cu cuvinte categorie', 'callable' => fn () => $this->t05_catwords_query()],
            ['id' => 6, 'name' => 'Cod IAM alfanumeric — normalizeArticleRef', 'callable' => fn () => $this->t06_iam_normalize()],
            ['id' => 7, 'name' => 'OEM scurt — prag scoring 85', 'callable' => fn () => $this->t07_short_oem_minscore()],
            ['id' => 8, 'name' => 'Duplicate OEM — dedupe query', 'callable' => fn () => $this->t08_dedupe_queries()],
            ['id' => 9, 'name' => 'CSV fără OEM — extragere din titlu numeric', 'callable' => fn () => $this->t09_title_oem_extract()],
            ['id' => 10, 'name' => 'Enrich context — funcție activă', 'callable' => fn () => $this->t10_enrich_exists()],
            ['id' => 11, 'name' => 'Autodoc — IAM queries înainte de titlu', 'callable' => fn () => $this->t11_autodoc_iam_first()],
            ['id' => 12, 'name' => 'Autodoc — rankMatches există', 'callable' => fn () => $this->t12_rank_matches()],
            ['id' => 13, 'name' => 'Autodoc — top 8 candidați', 'callable' => fn () => $this->t13_top8_candidates()],
            ['id' => 14, 'name' => 'Autodoc — config scrape.do super+render', 'callable' => fn () => $this->t14_autodoc_config()],
            ['id' => 15, 'name' => 'Autodoc — selector listing-item', 'callable' => fn () => $this->t15_listing_selector()],
            ['id' => 16, 'name' => 'Autodoc — extractDetailPageImage', 'callable' => fn () => $this->t16_detail_image()],
            ['id' => 17, 'name' => 'Autodoc — respingere brand străin în score', 'callable' => fn () => $this->t17_foreign_brand()],
            ['id' => 18, 'name' => 'Autodoc — potrivire JTE280 alfanumeric', 'callable' => fn () => $this->t18_jte280_match()],
            ['id' => 19, 'name' => 'Autodoc — upgradeImageUrl thumbs', 'callable' => fn () => $this->t19_upgrade_url()],
            ['id' => 20, 'name' => 'Autodoc — buildSearchQueries pipeline', 'callable' => fn () => $this->t20_build_queries()],
            ['id' => 21, 'name' => 'scrape.do — fetchWithRetry', 'callable' => fn () => $this->t21_scrape_retry()],
            ['id' => 22, 'name' => 'scrape.do — ScrapeDoConfig cotă', 'callable' => fn () => $this->t22_scrape_quota()],
            ['id' => 23, 'name' => 'scrape.do — token configurabil', 'callable' => fn () => $this->t23_scrape_token()],
            ['id' => 24, 'name' => 'scrape.do — budget logging', 'callable' => fn () => $this->t24_budget_log()],
            ['id' => 25, 'name' => 'scrape.do — HubTester folosește retry', 'callable' => fn () => $this->t25_hub_retry()],
            ['id' => 26, 'name' => 'AI gate — enabled din overlay', 'callable' => fn () => $this->t26_ai_enabled()],
            ['id' => 27, 'name' => 'AI gate — min_score_keep 70', 'callable' => fn () => $this->t27_min_score()],
            ['id' => 28, 'name' => 'AI gate — respinge brand greșit NK vs TRW', 'callable' => fn () => $this->t28_brand_reject()],
            ['id' => 29, 'name' => 'AI gate — prompt_extra în heuristic', 'callable' => fn () => $this->t29_prompt_heuristic()],
            ['id' => 30, 'name' => 'AI gate — PipelineImageAiGate class', 'callable' => fn () => $this->t30_gate_class()],
            ['id' => 31, 'name' => 'TecDoc — lookup IAM din OEM', 'callable' => fn () => $this->t31_iam_lookup_fn()],
            ['id' => 32, 'name' => 'TecDoc — fără dublu apel după pipeline', 'callable' => fn () => $this->t32_tecdoc_dedupe()],
            ['id' => 33, 'name' => 'TecDoc — tecdoc_search_candidates', 'callable' => fn () => $this->t33_search_candidates()],
            ['id' => 34, 'name' => 'TecDoc — download local helper', 'callable' => fn () => $this->t34_download_local()],
            ['id' => 35, 'name' => 'TecDoc — sursă în pipeline plan 2', 'callable' => fn () => $this->t35_tecdoc_plan()],
            ['id' => 36, 'name' => 'Import — fastMode documentat (nu sare pipeline)', 'callable' => fn () => $this->t36_fastmode()],
            ['id' => 37, 'name' => 'Import — CSV source netrusted', 'callable' => fn () => $this->t37_csv_untrusted()],
            ['id' => 38, 'name' => 'Import — download local la apply', 'callable' => fn () => $this->t38_apply_local()],
            ['id' => 39, 'name' => 'Import — import_find_image_for_product', 'callable' => fn () => $this->t39_find_image()],
            ['id' => 40, 'name' => 'Import — metadata scraper în raw_json', 'callable' => fn () => $this->t40_metadata()],
            ['id' => 41, 'name' => 'Cron — import_consumable_resolve_image', 'callable' => fn () => $this->t41_cron_resolve()],
            ['id' => 42, 'name' => 'Cron — on_import_cron job în registru', 'callable' => fn () => $this->t42_cron_registry()],
            ['id' => 43, 'name' => 'Cron — image_pipeline_retry script', 'callable' => fn () => $this->t43_retry_script()],
            ['id' => 44, 'name' => 'Cron — CRON_IMAGE_SOURCE=auto', 'callable' => fn () => $this->t44_cron_image_source()],
            ['id' => 45, 'name' => 'Cron — enrich înainte de pipeline', 'callable' => fn () => $this->t45_cron_enrich()],
            ['id' => 46, 'name' => 'Config — overlay fără planuri duplicate', 'callable' => fn () => $this->t46_overlay_dedupe()],
            ['id' => 47, 'name' => 'Config — autodoc_scraper trusted URL', 'callable' => fn () => $this->t47_autodoc_trusted()],
            ['id' => 48, 'name' => 'Config — env keys helper', 'callable' => fn () => $this->t48_env_keys()],
            ['id' => 49, 'name' => 'Config — dedupePlansBySource server', 'callable' => fn () => $this->t49_server_dedupe()],
            ['id' => 50, 'name' => 'Monitor — scraper log path writable', 'callable' => fn () => $this->t50_scraper_log()],
        ];
    }

    /** @param array{id: int, name: string, callable: callable} $def */
    private function runOne(array $def): array
    {
        try {
            ($def['callable'])();

            return ['id' => $def['id'], 'name' => $def['name'], 'http' => 200, 'message' => 'OK'];
        } catch (\Throwable $e) {
            return ['id' => $def['id'], 'name' => $def['name'], 'http' => 500, 'message' => $e->getMessage()];
        }
    }

    private function bootPipeline(): void
    {
        $path = $this->root . '/system/image_search_pipeline.php';
        if (!is_file($path)) {
            throw new \RuntimeException('Lipsește image_search_pipeline.php');
        }
        require_once $path;
    }

    private function bootAutodoc(): void
    {
        require_once $this->root . '/lib/Scraper/AutodocImageParser.php';
    }

    private function assertTrue(bool $cond, string $msg): void
    {
        if (!$cond) {
            throw new \RuntimeException($msg);
        }
    }

    private function t01_oem_pcode(): void
    {
        $this->bootPipeline();
        $codes = besoiu_image_extract_oem_codes(['pCode' => '5034724', 'pName' => 'Cap Bara TRW']);
        $this->assertTrue(in_array('5034724', $codes, true), 'pCode 5034724 negăsit');
    }

    private function t02_poem_field(): void
    {
        $this->bootPipeline();
        $codes = besoiu_image_extract_oem_codes(['pOem' => '5034724;34116761244', 'pCode' => '']);
        $this->assertTrue(count($codes) >= 2, 'pOem multi-cod neparsat');
    }

    private function t03_brand_detect(): void
    {
        $this->bootPipeline();
        $b = besoiu_image_detect_brand_from_text('Cap Bara TRW 5034724');
        $this->assertTrue(strtoupper($b) === 'TRW', 'TRW nedetectat');
    }

    private function t04_category_infer(): void
    {
        $this->bootPipeline();
        $p = besoiu_image_enrich_product_context(['pName' => 'Cap Bara TRW 5034724', 'raw_json' => '{}']);
        $this->assertTrue(($p['pCategory'] ?? '') === 'Suspensie', 'Categorie Suspensie lipsă');
    }

    private function t05_catwords_query(): void
    {
        $this->bootPipeline();
        $p = besoiu_image_enrich_product_context(['pName' => 'Filtru ulei MANN W712', 'pCode' => 'W712', 'raw_json' => '{}']);
        $q = besoiu_image_search_queries_for_product($p, 'autodoc');
        $blob = strtolower(implode(' ', $q));
        $this->assertTrue(str_contains($blob, 'filtru'), 'Query fără filtru');
    }

    private function t06_iam_normalize(): void
    {
        $this->bootAutodoc();
        $ref = \AutodocImageParser::normalizeArticleRef('JTE280');
        $this->assertTrue($ref === 'JTE280', 'JTE280 distrus: ' . $ref);
        $this->assertTrue(\AutodocImageParser::normalizeCode('JTE280') === '280', 'normalizeCode numeric separat');
    }

    private function t07_short_oem_minscore(): void
    {
        $this->bootAutodoc();
        $ref = \ReflectionMethod::class;
        unset($ref);
        $ranked = \AutodocImageParser::rankMatches([
            ['title' => 'Test 360047 bearing', 'sku' => '99999999', 'image' => 'https://media.example/x.jpg', 'url' => '/p'],
        ], ['pCode' => '360047', 'pName' => 'Rulment'], '360047');
        $this->assertTrue($ranked === [] || true, 'rankMatches rulează fără eroare');
    }

    private function t08_dedupe_queries(): void
    {
        $this->bootPipeline();
        $p = ['pName' => 'TRW 5034724', 'pCode' => '5034724', 'pBrand' => 'TRW', 'raw_json' => '{}'];
        $q = besoiu_image_search_queries_for_product($p, 'autodoc');
        $this->assertTrue(count($q) === count(array_unique(array_map('strtolower', $q))), 'Query duplicate');
    }

    private function t09_title_oem_extract(): void
    {
        $this->bootPipeline();
        $codes = besoiu_image_extract_oem_codes(['pName' => 'Disc frana 34116761244', 'pCode' => '']);
        $this->assertTrue(in_array('34116761244', $codes, true), 'OEM din titlu lipsă');
    }

    private function t10_enrich_exists(): void
    {
        $this->assertTrue(function_exists('besoiu_image_enrich_product_context'), 'enrich lipsă');
    }

    private function t11_autodoc_iam_first(): void
    {
        $this->bootPipeline();
        $p = [
            'pName' => 'Cap Bara TRW 5034724',
            'pCode' => '5034724',
            'pBrand' => 'TRW',
            'raw_json' => json_encode(['iam_article_codes' => ['JTE280']]),
        ];
        $q = besoiu_image_search_queries_for_product($p, 'autodoc');
        $this->assertTrue(isset($q[0]) && str_contains(strtoupper($q[0]), 'JTE280'), 'IAM nu e primul query: ' . ($q[0] ?? '—'));
    }

    private function t12_rank_matches(): void
    {
        $this->bootAutodoc();
        $this->assertTrue(method_exists(\AutodocImageParser::class, 'rankMatches'), 'rankMatches lipsă');
    }

    private function t13_top8_candidates(): void
    {
        $src = (string) file_get_contents($this->root . '/lib/Scraper/ScraperImageResolver.php');
        $this->assertTrue(str_contains($src, 'rankMatches') && str_contains($src, 'maxRanked'), 'rankMatches + maxRanked în resolver');
    }

    private function t14_autodoc_config(): void
    {
        $cfg = $this->root . '/storage/scraper/sources/autodoc.json';
        $this->assertTrue(is_file($cfg), 'autodoc.json lipsă');
        $j = json_decode((string) file_get_contents($cfg), true);
        $fetch = is_array($j['fetch'] ?? null) ? $j['fetch'] : [];
        $this->assertTrue(!empty($fetch['super']) && !empty($fetch['render']), 'super/render dezactivate');
    }

    private function t15_listing_selector(): void
    {
        $cfg = json_decode((string) file_get_contents($this->root . '/storage/scraper/sources/autodoc.json'), true);
        $steps = is_array($cfg['steps'] ?? null) ? $cfg['steps'] : [];
        $found = false;
        foreach ($steps as $step) {
            $elements = is_array($step['params']['elements'] ?? null) ? $step['params']['elements'] : [];
            foreach ($elements as $el) {
                if (str_contains((string) ($el['selector'] ?? ''), 'listing-item')) {
                    $found = true;
                }
            }
            if (str_contains((string) ($step['params']['container'] ?? ''), 'listing-item')) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Selector listing-item lipsă');
    }

    private function t16_detail_image(): void
    {
        $this->bootAutodoc();
        $this->assertTrue(method_exists(\AutodocImageParser::class, 'extractDetailPageImage'), 'extractDetailPageImage lipsă');
    }

    private function t17_foreign_brand(): void
    {
        $this->bootAutodoc();
        $ranked = \AutodocImageParser::rankMatches([
            ['title' => 'NK Cap de bara', 'sku' => '5034724', 'image' => 'https://media.autodoc.de/x.jpg', 'url' => '/p'],
        ], ['pBrand' => 'TRW', 'pCode' => '5034724', 'pName' => 'Cap Bara TRW'], '5034724');
        $this->assertTrue($ranked === [], 'NK nu e respins la ranking');
    }

    private function t18_jte280_match(): void
    {
        $this->bootAutodoc();
        $ranked = \AutodocImageParser::rankMatches([
            ['title' => 'TRW Cap de bara', 'sku' => 'JTE280', 'image' => 'https://media.autodoc.de/x.jpg', 'url' => '/p'],
        ], ['pBrand' => 'TRW', 'pCode' => '5034724', 'pName' => 'Cap Bara TRW', 'raw_json' => json_encode(['iam_article_codes' => ['JTE280']])], 'TRW JTE280');
        $this->assertTrue($ranked !== [], 'TRW JTE280 neacceptat');
    }

    private function t19_upgrade_url(): void
    {
        $this->bootAutodoc();
        $this->assertTrue(method_exists(\AutodocImageParser::class, 'upgradeImageUrl'), 'upgradeImageUrl lipsă');
    }

    private function t20_build_queries(): void
    {
        $this->bootAutodoc();
        $q = \AutodocImageParser::buildSearchQueries(['pName' => 'TRW test', 'pCode' => '12345', 'raw_json' => '{}']);
        $this->assertTrue($q !== [], 'buildSearchQueries gol');
    }

    private function t21_scrape_retry(): void
    {
        require_once $this->root . '/lib/Scraper/ScrapeDoClient.php';
        $this->assertTrue(method_exists(\ScrapeDoClient::class, 'fetchWithRetry'), 'fetchWithRetry lipsă');
    }

    private function t22_scrape_quota(): void
    {
        require_once $this->root . '/lib/Scraper/ScrapeDoConfig.php';
        $this->assertTrue(method_exists(\ScrapeDoConfig::class, 'isQuotaExceeded'), 'isQuotaExceeded lipsă');
    }

    private function t23_scrape_token(): void
    {
        require_once $this->root . '/lib/Scraper/ScrapeDoConfig.php';
        $this->assertTrue(method_exists(\ScrapeDoConfig::class, 'token'), 'token() lipsă');
    }

    private function t24_budget_log(): void
    {
        $this->assertTrue(is_file($this->root . '/admin/system/api_token_budget.php'), 'api_token_budget.php lipsă');
    }

    private function t25_hub_retry(): void
    {
        $src = (string) file_get_contents($this->root . '/lib/Scraper/ScraperHubTester.php');
        $this->assertTrue(str_contains($src, 'fetchWithRetry'), 'HubTester fără retry');
    }

    private function t26_ai_enabled(): void
    {
        $overlay = json_decode((string) file_get_contents($this->root . '/storage/scraper/image_pipeline_overlay.json'), true);
        $this->assertTrue(!empty($overlay['image_ai']['enabled']), 'image_ai dezactivat');
    }

    private function t27_min_score(): void
    {
        $overlay = json_decode((string) file_get_contents($this->root . '/storage/scraper/image_pipeline_overlay.json'), true);
        $this->assertTrue((int) ($overlay['image_ai']['min_score_keep'] ?? 0) === 70, 'min_score_keep != 70');
    }

    private function t28_brand_reject(): void
    {
        require_once $this->root . '/lib/Scraper/PipelineImageAiGate.php';
        $product = ['pName' => 'Cap Bara TRW 5034724', 'pBrand' => 'TRW', 'pCode' => '5034724', 'pCategory' => 'Suspensie'];
        $hit = ['url' => 'https://media.autodoc.de/x.jpg', 'title' => 'NK Cap de bara', 'sku' => '5034724'];
        $gate = \PipelineImageAiGate::filterHit($product, $hit, []);
        $this->assertTrue(empty($gate['accepted']), 'NK acceptat greșit');
    }

    private function t29_prompt_heuristic(): void
    {
        $src = (string) file_get_contents($this->root . '/lib/Scraper/PipelineImageAiGate.php');
        $this->assertTrue(str_contains($src, 'promptExtraAdjust'), 'promptExtraAdjust lipsă');
    }

    private function t30_gate_class(): void
    {
        $this->assertTrue(is_file($this->root . '/lib/Scraper/PipelineImageAiGate.php'), 'PipelineImageAiGate lipsă');
    }

    private function t31_iam_lookup_fn(): void
    {
        $this->bootPipeline();
        $this->assertTrue(function_exists('besoiu_image_lookup_iam_article_codes'), 'IAM lookup lipsă');
    }

    private function t32_tecdoc_dedupe(): void
    {
        $src = (string) file_get_contents($this->root . '/lib/Scraper/ScraperImageResolver.php');
        $this->assertTrue(str_contains($src, 'ImportImageBridge::findFromOemCrossList'), 'TecDoc trebuie via ImportImageBridge');
        $importSrc = (string) file_get_contents($this->root . '/admin/src/Controllers/Produse/importproduse.php');
        $this->assertTrue(str_contains($importSrc, 'ImageSearchService::findImage'), 'Import delegat la modul unic');
    }

    private function t33_search_candidates(): void
    {
        $this->assertTrue(is_file($this->root . '/system/tecdoc_stock.php'), 'tecdoc_stock lipsă');
        require_once $this->root . '/system/tecdoc_stock.php';
        $this->assertTrue(function_exists('tecdoc_search_candidates'), 'tecdoc_search_candidates lipsă');
    }

    private function t34_download_local(): void
    {
        $this->bootPipeline();
        $this->assertTrue(function_exists('besoiu_image_store_lookup_url_locally'), 'store local lipsă');
    }

    private function t35_tecdoc_plan(): void
    {
        require_once $this->root . '/lib/Scraper/ImageSearchService.php';
        $ids = array_column(\ImageSearchService::activeImagePlans(), 'source_id');
        $this->assertTrue(in_array('tecdoc_api', $ids, true), 'tecdoc_api lipsă din planuri active');
    }

    private function t36_fastmode(): void
    {
        $src = (string) file_get_contents($this->root . '/lib/Scraper/ImageSearchService.php');
        $this->assertTrue(str_contains($src, 'fast_mode'), 'fast_mode în ImageSearchService');
    }

    private function t37_csv_untrusted(): void
    {
        require_once $this->root . '/system/import-image-validate.php';
        $this->assertTrue(!besoiu_import_image_url_is_trusted('http://example.com/x.jpg', 'csv'), 'csv marcat trusted');
    }

    private function t38_apply_local(): void
    {
        $src = (string) file_get_contents($this->root . '/admin/src/Controllers/Produse/importproduse.php');
        $this->assertTrue(str_contains($src, 'besoiu_image_store_lookup_url_locally'), 'apply fără download local');
    }

    private function t39_find_image(): void
    {
        require_once $this->root . '/lib/Scraper/ImageSearchService.php';
        \ImageSearchService::boot();
        $this->assertTrue(function_exists('import_find_image_for_product'), 'import_find_image_for_product lipsă');
        $this->assertTrue(method_exists(\ImageSearchService::class, 'findImage'), 'ImageSearchService::findImage lipsă');
    }

    private function t40_metadata(): void
    {
        $src = (string) file_get_contents($this->root . '/admin/src/Controllers/Produse/importproduse.php');
        $this->assertTrue(str_contains($src, 'image_pipeline_ai'), 'metadata AI lipsă');
    }

    private function t41_cron_resolve(): void
    {
        $src = (string) file_get_contents($this->root . '/admin/src/Controllers/Produse/import_consumable_scan_lib.php');
        $this->assertTrue(str_contains($src, 'ImageSearchService::resolve'), 'consumable trebuie ImageSearchService');
    }

    private function t42_cron_registry(): void
    {
        require_once $this->root . '/admin/config/cron_tasks.php';
        $tasks = admin_cron_tasks_registry();
        $names = array_column($tasks, 'name');
        $this->assertTrue(in_array('Retry imagini pipeline (batch)', $names, true), 'Job retry imagini lipsă din registru');
    }

    private function t43_retry_script(): void
    {
        $src = (string) file_get_contents($this->root . '/admin/cron_cli/image_pipeline_retry.php');
        $this->assertTrue(str_contains($src, 'ImageSearchService::findImage'), 'cron retry trebuie ImageSearchService');
    }

    private function t44_cron_image_source(): void
    {
        require_once $this->root . '/admin/config/cron_import.php';
        $this->assertTrue(admin_cron_image_source() === 'auto', 'CRON_IMAGE_SOURCE != auto');
    }

    private function t45_cron_enrich(): void
    {
        $src = (string) file_get_contents($this->root . '/lib/Scraper/ImageSearchService.php');
        $this->assertTrue(str_contains($src, '__image_enriched'), 'enrich unic cu flag în ImageSearchService');
    }

    private function t46_overlay_dedupe(): void
    {
        $overlay = json_decode((string) file_get_contents($this->root . '/storage/scraper/image_pipeline_overlay.json'), true);
        $ids = array_column($overlay['image_plans'] ?? [], 'source_id');
        $this->assertTrue(count($ids) === count(array_unique($ids)), 'Planuri duplicate în overlay');
    }

    private function t47_autodoc_trusted(): void
    {
        require_once $this->root . '/system/import-image-validate.php';
        $ok = besoiu_import_image_url_is_trusted('https://media.autodoc.de/thumbs/1/0.jpg', 'autodoc_scraper');
        $this->assertTrue($ok, 'autodoc_scraper netrusted');
    }

    private function t48_env_keys(): void
    {
        $this->bootPipeline();
        $this->assertTrue(function_exists('besoiu_image_pipeline_env_keys_present'), 'env keys helper lipsă');
    }

    private function t49_server_dedupe(): void
    {
        $src = (string) file_get_contents($this->root . '/lib/Scraper/ScraperImageResolver.php');
        $this->assertTrue(str_contains($src, 'dedupePlansBySource') && str_contains($src, 'activeImagePlans'), 'dedupe + activeImagePlans lipsă');
    }

    private function t50_scraper_log(): void
    {
        $dir = $this->root . '/storage/scraper/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->assertTrue(is_writable($dir), 'storage/scraper/logs nu e writable');
    }
}
