<?php

declare(strict_types=1);

namespace Besoiu\Services\Seo;

use Besoiu\Services\AiRag\AiRagCorpusService;
use Throwable;

/**
 * Generează fișier HTML SEO per produs (L1 › L2 › produs) în app/Storage/seo/produse/
 * și indexează conținutul în corpus RAG.
 */
final class ProductSeoHtmlGenerator
{
    private string $root;
    private string $seoDir;
    private string $productsDir;
    private string $manifestPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->seoDir = $this->root . '/app/Storage/seo';
        $this->productsDir = $this->seoDir . '/produse';
        $this->manifestPath = $this->productsDir . '/manifest.json';
    }

    /**
     * @param array<string, mixed>|null $productCtx
     * @param array<string, mixed> $seoPackage
     * @return array<string, mixed>
     */
    public function generateFromSeoPackage(?array $productCtx, array $seoPackage): array
    {
        $productId = trim((string) ($productCtx['id'] ?? ''));
        if ($productId === '') {
            return ['ok' => false, 'error' => 'Lipsește ID produs pentru generare HTML.'];
        }

        $categorii = is_array($seoPackage['categorii'] ?? null)
            ? $seoPackage['categorii']
            : $this->resolveCategoryHierarchy($productCtx, $seoPackage);

        $title = trim((string) ($seoPackage['implementare_campuri']['pName'] ?? $seoPackage['meta_tags']['title'] ?? $productCtx['name'] ?? 'Produs'));
        $shortDesc = trim((string) ($seoPackage['descrieri']['scurta'] ?? ''));
        $longDesc = trim((string) ($seoPackage['descrieri']['lunga'] ?? ''));
        $metaDesc = trim((string) ($seoPackage['meta_tags']['description'] ?? $shortDesc));
        $metaTags = is_array($seoPackage['meta_tags'] ?? null) ? $seoPackage['meta_tags'] : [];
        $schemaProduct = is_array($seoPackage['schema_json_ld'] ?? null) ? $seoPackage['schema_json_ld'] : [];
        $keywords = is_array($seoPackage['keywords_seo']['fraze_long_tail'] ?? null)
            ? $seoPackage['keywords_seo']['fraze_long_tail']
            : [];
        $coduri = is_array($seoPackage['coduri'] ?? null) ? $seoPackage['coduri'] : [];

        if (!is_dir($this->productsDir)) {
            mkdir($this->productsDir, 0775, true);
        }

        $baseUrl = $this->baseUrl();
        $livePath = '/produs?id=' . rawurlencode($productId);
        $staticRel = '/seo/produse/' . $productId . '.html';
        $staticUrl = $baseUrl . $staticRel;
        $liveUrl = $baseUrl . $livePath;
        $filePath = $this->productsDir . '/' . $productId . '.html';

        $breadcrumbSchema = $this->buildBreadcrumbSchema($categorii, $liveUrl, $baseUrl);
        $html = $this->buildHtml(
            $title,
            $metaDesc,
            $metaTags,
            $categorii,
            $shortDesc,
            $longDesc,
            $keywords,
            $coduri,
            $schemaProduct,
            $breadcrumbSchema,
            $liveUrl,
            $staticUrl,
        );

        $written = file_put_contents($filePath, $html);
        if ($written === false) {
            return ['ok' => false, 'error' => 'Nu s-a putut scrie fișierul HTML SEO.'];
        }

        $generatedAt = date('c');
        $this->updateManifest([
            'product_id' => $productId,
            'title' => $title,
            'nivel_1' => (string) ($categorii['nivel_1'] ?? ''),
            'nivel_2' => (string) ($categorii['nivel_2'] ?? ''),
            'url_static' => $staticRel,
            'url_live' => $livePath,
            'file' => 'app/Storage/seo/produse/' . $productId . '.html',
            'generated_at' => $generatedAt,
        ]);
        $this->refreshSeoProductsSitemap($baseUrl);
        $indexed = $this->indexInCorpus($productId, $title, $categorii, $shortDesc, $longDesc, $keywords, $coduri, $staticUrl, $metaDesc);

        return [
            'ok' => true,
            'file' => 'app/Storage/seo/produse/' . $productId . '.html',
            'url_static' => $staticUrl,
            'url_live' => $liveUrl,
            'categorii' => $categorii,
            'indexed_rag' => $indexed,
            'generated_at' => $generatedAt,
        ];
    }

    /**
     * @param array<string, mixed>|null $productCtx
     * @param array<string, mixed> $seoPackage
     * @return array{nivel_1:string,nivel_2:string,produs:string,breadcrumb:list<string>,breadcrumb_html:string}
     */
    public function resolveCategoryHierarchy(?array $productCtx, array $seoPackage): array
    {
        $impl = is_array($seoPackage['implementare_campuri'] ?? null) ? $seoPackage['implementare_campuri'] : [];
        $nivel1 = trim((string) ($impl['pCategory'] ?? $productCtx['category'] ?? ''));
        $nivel2 = trim((string) ($impl['pSubcategory'] ?? $productCtx['subcategory'] ?? ''));
        $produs = trim((string) ($impl['pName'] ?? $seoPackage['meta_tags']['title'] ?? $productCtx['name'] ?? ''));
        $breadcrumb = array_values(array_filter([$nivel1, $nivel2, $produs], static fn (string $v): bool => $v !== ''));

        return [
            'nivel_1' => $nivel1,
            'nivel_2' => $nivel2,
            'produs' => $produs,
            'breadcrumb' => $breadcrumb,
            'breadcrumb_html' => implode(' › ', $breadcrumb),
        ];
    }

    /**
     * @param array{nivel_1:string,nivel_2:string,produs:string,breadcrumb:list<string>} $categorii
     * @param list<string> $keywords
     * @param array<string, mixed> $coduri
     * @param array<string, mixed> $schemaProduct
     * @param list<array<string, mixed>> $breadcrumbSchema
     */
    private function buildHtml(
        string $title,
        string $metaDesc,
        array $metaTags,
        array $categorii,
        string $shortDesc,
        string $longDesc,
        array $keywords,
        array $coduri,
        array $schemaProduct,
        array $breadcrumbSchema,
        string $liveUrl,
        string $staticUrl,
    ): string {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $nivel1 = (string) ($categorii['nivel_1'] ?? '');
        $nivel2 = (string) ($categorii['nivel_2'] ?? '');
        $produs = (string) ($categorii['produs'] ?? $title);

        $breadcrumbHtml = '<nav class="breadcrumb" aria-label="Categorii produs"><ol>';
        $breadcrumbHtml .= '<li><a href="/">Acasă</a></li>';
        if ($nivel1 !== '') {
            $breadcrumbHtml .= '<li><span>' . $esc($nivel1) . '</span></li>';
        }
        if ($nivel2 !== '') {
            $breadcrumbHtml .= '<li><span>' . $esc($nivel2) . '</span></li>';
        }
        if ($produs !== '') {
            $breadcrumbHtml .= '<li aria-current="page"><strong>' . $esc($produs) . '</strong></li>';
        }
        $breadcrumbHtml .= '</ol></nav>';

        $longHtml = $this->textToHtmlParagraphs($longDesc !== '' ? $longDesc : $shortDesc);
        $keywordsHtml = $keywords !== []
            ? '<ul class="keywords">' . implode('', array_map(
                static fn (string $kw): string => '<li>' . htmlspecialchars($kw, ENT_QUOTES, 'UTF-8') . '</li>',
                $keywords,
            )) . '</ul>'
            : '';

        $codesHtml = '';
        if (!empty($coduri['oem']) || !empty($coduri['cod_articol'])) {
            $codesHtml = '<dl class="codes">';
            if (!empty($coduri['oem'])) {
                $codesHtml .= '<dt>Cod OEM (MPN)</dt><dd><code>' . $esc((string) $coduri['oem']) . '</code></dd>';
            }
            if (!empty($coduri['cod_articol'])) {
                $codesHtml .= '<dt>Cod articol (SKU)</dt><dd><code>' . $esc((string) $coduri['cod_articol']) . '</code></dd>';
            }
            $codesHtml .= '</dl>';
        }

        $metaKeywords = trim((string) ($metaTags['keywords'] ?? ''));
        $metaOem = trim((string) ($metaTags['product:oem'] ?? ''));
        $metaSku = trim((string) ($metaTags['product:sku'] ?? ''));
        $metaBrand = trim((string) ($metaTags['product:brand'] ?? ''));

        $jsonLd = [
            $schemaProduct,
            ['@context' => 'https://schema.org', '@graph' => $breadcrumbSchema],
        ];
        $jsonLdStr = json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $categoryLine = trim($nivel1 . ($nivel2 !== '' ? ' › ' . $nivel2 : ''));

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$esc($title)} — Besoiu Piese Auto</title>
<meta name="description" content="{$esc($metaDesc)}">
<meta name="robots" content="index,follow,max-image-preview:large">
<link rel="canonical" href="{$esc($liveUrl)}">
<meta name="keywords" content="{$esc($metaKeywords)}">
<meta name="product:oem" content="{$esc($metaOem)}">
<meta name="product:sku" content="{$esc($metaSku)}">
<meta name="product:brand" content="{$esc($metaBrand)}">
<meta property="og:type" content="product">
<meta property="og:title" content="{$esc($title)}">
<meta property="og:description" content="{$esc($metaDesc)}">
<meta property="og:url" content="{$esc($liveUrl)}">
<script type="application/ld+json">{$jsonLdStr}</script>
<style>
body{font-family:system-ui,sans-serif;line-height:1.55;color:#0f172a;max-width:860px;margin:0 auto;padding:24px}
.breadcrumb ol{list-style:none;padding:0;display:flex;flex-wrap:wrap;gap:6px;font-size:14px;color:#475569}
.breadcrumb li+li::before{content:"›";margin-right:6px;color:#94a3b8}
h1{font-size:1.6rem;margin:16px 0 8px}
.lead{color:#334155;font-size:1.05rem}
.category-path{font-size:13px;color:#64748b;margin-bottom:12px}
.content{margin:20px 0}
.codes{display:grid;grid-template-columns:auto 1fr;gap:4px 12px;font-size:14px;background:#f8fafc;padding:12px;border-radius:8px}
.keywords{font-size:13px;color:#047857}
.meta-links{margin-top:28px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:13px}
</style>
</head>
<body>
<header>
{$breadcrumbHtml}
<p class="category-path"><strong>Categorie:</strong> {$esc($categoryLine)}</p>
<h1>{$esc($title)}</h1>
<p class="lead">{$esc($shortDesc)}</p>
</header>
<article class="content">
{$longHtml}
{$codesHtml}
{$keywordsHtml}
</article>
<p class="meta-links">
<a href="{$esc($liveUrl)}">Vezi produsul live în magazin</a>
 · <span>Pagină SEO statică: {$esc($staticUrl)}</span>
</p>
</body>
</html>
HTML;
    }

    /** @return list<array<string, mixed>> */
    private function buildBreadcrumbSchema(array $categorii, string $liveUrl, string $baseUrl): array
    {
        $items = [];
        $pos = 1;
        $items[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => 'Acasă',
            'item' => $baseUrl . '/',
        ];
        foreach (['nivel_1', 'nivel_2', 'produs'] as $key) {
            $name = trim((string) ($categorii[$key] ?? ''));
            if ($name === '') {
                continue;
            }
            $entry = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $name,
            ];
            if ($key === 'produs') {
                $entry['item'] = $liveUrl;
            }
            $items[] = $entry;
        }

        return [[
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]];
    }

    private function textToHtmlParagraphs(string $text): string
    {
        $parts = preg_split('/\n{2,}/', trim($text)) ?: [trim($text)];
        $html = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $html .= '<p>' . nl2br(htmlspecialchars($part, ENT_QUOTES, 'UTF-8'), false) . '</p>';
        }

        return $html !== '' ? $html : '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    /** @param array<string, mixed> $entry */
    private function updateManifest(array $entry): void
    {
        $byId = $this->loadManifest();
        $id = (string) ($entry['product_id'] ?? '');
        if ($id === '') {
            return;
        }
        $byId[$id] = $entry;
        file_put_contents(
            $this->manifestPath,
            json_encode(array_values($byId), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function loadManifest(): array
    {
        $out = [];
        foreach ($this->loadManifestList() as $row) {
            $id = (string) ($row['product_id'] ?? '');
            if ($id !== '') {
                $out[$id] = $row;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function loadManifestList(): array
    {
        if (!is_file($this->manifestPath)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($this->manifestPath), true);

        return is_array($json) ? $json : [];
    }

    private function refreshSeoProductsSitemap(string $baseUrl): void
    {
        $entries = [];
        foreach ($this->loadManifestList() as $row) {
            $staticRel = (string) ($row['url_static'] ?? '');
            if ($staticRel === '') {
                continue;
            }
            $entries[] = [
                'loc' => rtrim($baseUrl, '/') . $staticRel,
                'lastmod' => (string) ($row['generated_at'] ?? date('c')),
                'changefreq' => 'weekly',
                'priority' => '0.65',
            ];
        }

        $path = $this->seoDir . '/sitemap-seo-products.xml';
        file_put_contents($path, $this->buildUrlsetXml($entries));
        $this->patchSitemapIndex($baseUrl);
    }

    /** @param list<array{loc:string,lastmod?:string,changefreq?:string,priority?:string}> $entries */
    private function buildUrlsetXml(array $entries): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . htmlspecialchars((string) $entry['lastmod'], ENT_XML1) . "</lastmod>\n";
            }
            if (!empty($entry['changefreq'])) {
                $xml .= '    <changefreq>' . htmlspecialchars((string) $entry['changefreq'], ENT_XML1) . "</changefreq>\n";
            }
            if (!empty($entry['priority'])) {
                $xml .= '    <priority>' . htmlspecialchars((string) $entry['priority'], ENT_XML1) . "</priority>\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return $xml;
    }

    private function patchSitemapIndex(string $baseUrl): void
    {
        $now = date('c');
        $base = rtrim($baseUrl, '/');
        $sitemaps = [
            ['loc' => $base . '/sitemap-pages.xml', 'lastmod' => $now],
            ['loc' => $base . '/sitemap-products.xml', 'lastmod' => $now],
            ['loc' => $base . '/sitemap-seo-products.xml', 'lastmod' => $now],
        ];

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($sitemaps as $item) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>' . htmlspecialchars($item['loc'], ENT_XML1) . "</loc>\n";
            $xml .= '    <lastmod>' . htmlspecialchars($item['lastmod'], ENT_XML1) . "</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }
        $xml .= "</sitemapindex>\n";

        file_put_contents($this->seoDir . '/sitemap.xml', $xml);
        file_put_contents($this->root . '/sitemap.xml', $xml);
    }

    /**
     * @param array{nivel_1:string,nivel_2:string,produs:string} $categorii
     * @param list<string> $keywords
     * @param array<string, mixed> $coduri
     */
    private function indexInCorpus(
        string $productId,
        string $title,
        array $categorii,
        string $shortDesc,
        string $longDesc,
        array $keywords,
        array $coduri,
        string $staticUrl,
        string $metaDesc,
    ): bool {
        try {
            $textParts = array_filter([
                ($categorii['nivel_1'] ?? '') . ' > ' . ($categorii['nivel_2'] ?? '') . ' > ' . $title,
                $shortDesc,
                $longDesc,
                $keywords !== [] ? 'Keywords: ' . implode(', ', $keywords) : '',
                !empty($coduri['oem']) ? 'OEM: ' . $coduri['oem'] : '',
                !empty($coduri['cod_articol']) ? 'SKU: ' . $coduri['cod_articol'] : '',
            ]);
            $corpus = new AiRagCorpusService($this->root);
            $entry = $corpus->append([
                'source_type' => 'seo_html',
                'source_id' => $productId,
                'title' => $title,
                'text' => implode("\n\n", $textParts),
                'url' => $staticUrl,
                'tags' => array_values(array_filter(['seo', 'produs', 'generated', $categorii['nivel_1'] ?? '', $categorii['nivel_2'] ?? ''])),
                'keywords' => $keywords,
                'seo' => [
                    'title' => $title,
                    'description' => $metaDesc,
                    'h1' => $title,
                ],
            ]);

            return $entry !== null;
        } catch (Throwable) {
            return false;
        }
    }

    private function baseUrl(): string
    {
        $url = trim((string) (getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '')));
        if ($url === '' && is_file($this->root . '/app/Legacy/url.php')) {
            require_once $this->root . '/app/Legacy/url.php';
            if (function_exists('besoiu_site_base_url')) {
                $url = (string) besoiu_site_base_url();
            }
        }
        if ($url === '') {
            $url = 'http://besoiupieseauto.ro.test';
        }

        return rtrim($url, '/');
    }
}
