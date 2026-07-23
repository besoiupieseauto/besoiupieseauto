<?php

declare(strict_types=1);

namespace Storefront\Core\Seo;

use Storefront\Core\Module\ModuleRegistry;
use Storefront\Core\Repository\SiteContentRepository;
use Storefront\Core\Template\ThemeRenderer;

/**
 * Generează sitemap-uri XML cu URL-uri live (nu /seo/*.html).
 */
final class SitemapGenerator
{
    /** @var list<array{loc:string,lastmod?:string,changefreq?:string,priority?:string}> */
    private array $entries = [];

    public function __construct(
        private readonly string $root,
        private readonly string $baseUrl,
    ) {
    }

    /** @return array{pages:string,products:string,index:string,count_pages:int,count_products:int} */
    public function generateAll(): array
    {
        $this->entries = [];
        $this->collectModuleRoutes();
        $this->collectCmsPages();
        $pagesEntries = $this->entries;

        $productsEntries = $this->collectProductUrls();
        $seoDir = $this->root . '/app/Storage/seo';
        if (!is_dir($seoDir)) {
            mkdir($seoDir, 0775, true);
        }

        $pagesPath = $seoDir . '/sitemap-pages.xml';
        $productsPath = $seoDir . '/sitemap-products.xml';
        $indexPath = $this->root . '/sitemap.xml';

        $this->writeUrlset($pagesPath, $pagesEntries);
        $this->writeUrlset($productsPath, $productsEntries);

        $indexXml = $this->buildIndex([
            ['loc' => rtrim($this->baseUrl, '/') . '/sitemap-pages.xml', 'lastmod' => date('c')],
            ['loc' => rtrim($this->baseUrl, '/') . '/sitemap-products.xml', 'lastmod' => date('c')],
        ]);
        file_put_contents($indexPath, $indexXml);
        file_put_contents($seoDir . '/sitemap.xml', $indexXml);

        return [
            'pages' => $pagesPath,
            'products' => $productsPath,
            'index' => $indexPath,
            'count_pages' => count($pagesEntries),
            'count_products' => count($productsEntries),
        ];
    }

    private function collectModuleRoutes(): void
    {
        $registry = new ModuleRegistry($this->root . '/app/Modules', new ThemeRenderer());
        foreach ($registry->ids() as $moduleId) {
            $manifest = $registry->manifest($moduleId);
            if (!is_array($manifest) || empty($manifest['seo'])) {
                continue;
            }
            $seo = $manifest['seo'];
            $path = (string) ($seo['path'] ?? '/');
            if (in_array($path, ['/cart', '/cont'], true)) {
                continue;
            }
            $this->addEntry(
                $path,
                null,
                (string) ($seo['changefreq'] ?? 'weekly'),
                (string) ($seo['priority'] ?? '0.5'),
            );
        }
    }

    private function collectCmsPages(): void
    {
        $content = new SiteContentRepository();
        $skip = ['home', 'catalog', 'global', 'cart', 'cont'];
        $pathMap = [
            'contact' => '/contact',
            'about' => '/despre',
            'cum-comand' => '/cum-comand',
            'blog' => '/blog',
        ];

        foreach ($content->listActiveSlugs() as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '' || in_array($slug, $skip, true)) {
                continue;
            }
            $path = $pathMap[$slug] ?? ('/p/' . $slug);
            $lastmod = $this->formatLastmod($row['updated_at'] ?? null);
            $this->addEntry($path, $lastmod, 'monthly', '0.6');
        }
    }

    /** @return list<array{loc:string,lastmod?:string,changefreq?:string,priority?:string}> */
    private function collectProductUrls(): array
    {
        $entries = [];
        try {
            require_once $this->root . '/app/bootstrap.php';
            require_once BESOIU_BACKEND . '/vendor/autoload.php';
            $dotenv = Dotenv\Dotenv::createImmutable(BESOIU_CONFIG);
            $dotenv->safeLoad();
            $config = require BESOIU_CONFIG . '/config.php';
            \Config\Database::getInstance(
                $config['db_host'],
                $config['db_name'],
                $config['db_user'],
                $config['db_pass'],
            );
            $pdo = \Config\Database::getDB();
            $stmt = $pdo->query(
                "SELECT randomn_id, updated_at FROM produse
                 WHERE status <> '0' AND randomn_id IS NOT NULL AND randomn_id <> ''
                 ORDER BY id DESC"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $id = trim((string) ($row['randomn_id'] ?? ''));
                    if ($id === '') {
                        continue;
                    }
                    $entries[] = [
                        'loc' => rtrim($this->baseUrl, '/') . '/produs?id=' . rawurlencode($id),
                        'lastmod' => $this->formatLastmod($row['updated_at'] ?? null),
                        'changefreq' => 'weekly',
                        'priority' => '0.7',
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[sitemap-products] ' . $e->getMessage());
        }

        return $entries;
    }

    private function addEntry(string $path, ?string $lastmod, string $changefreq, string $priority): void
    {
        $loc = rtrim($this->baseUrl, '/') . ($path === '/' ? '/' : $path);
        $entry = [
            'loc' => $loc,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
        if ($lastmod !== null && $lastmod !== '') {
            $entry['lastmod'] = $lastmod;
        }
        $this->entries[] = $entry;
    }

    private function formatLastmod(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string) $value);

        return $ts ? date('c', $ts) : null;
    }

    /** @param list<array{loc:string,lastmod?:string,changefreq?:string,priority?:string}> $entries */
    private function writeUrlset(string $path, array $entries): void
    {
        file_put_contents($path, $this->buildUrlset($entries));
    }

    /** @param list<array{loc:string,lastmod?:string,changefreq?:string,priority?:string}> $entries */
    private function buildUrlset(array $entries): string
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

    /** @param list<array{loc:string,lastmod?:string}> $sitemaps */
    private function buildIndex(array $sitemaps): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($sitemaps as $item) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>' . htmlspecialchars($item['loc'], ENT_XML1) . "</loc>\n";
            if (!empty($item['lastmod'])) {
                $xml .= '    <lastmod>' . htmlspecialchars((string) $item['lastmod'], ENT_XML1) . "</lastmod>\n";
            }
            $xml .= "  </sitemap>\n";
        }
        $xml .= "</sitemapindex>\n";

        return $xml;
    }
}
