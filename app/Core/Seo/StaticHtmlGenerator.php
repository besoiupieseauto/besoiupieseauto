<?php

declare(strict_types=1);

namespace Storefront\Core\Seo;

use Storefront\Core\Module\ModuleRegistry;
use Storefront\Core\Repository\SiteContentRepository;

/**
 * Generează HTML static pentru indexare SEO — canonical către rutele dinamice (root).
 */
final class StaticHtmlGenerator
{
    public function __construct(
        private readonly string $root,
        private readonly string $outputDir,
        private readonly string $baseUrl,
    ) {
    }

    public function generateAll(): int
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0775, true);
        }

        $count = 0;
        $registry = new ModuleRegistry($this->root . '/app/Modules', new \Storefront\Core\Template\ThemeRenderer());

        foreach ($registry->ids() as $moduleId) {
            $manifest = $registry->manifest($moduleId);
            if (!is_array($manifest) || empty($manifest['seo'])) {
                continue;
            }
            $seo = $manifest['seo'];
            $path = (string) ($seo['path'] ?? '/');
            $title = (string) ($seo['title'] ?? 'Besoiu Piese Auto');
            $this->writeHtml($path, $title, (string) ($seo['description'] ?? ''), $this->introForModule($moduleId));
            $count++;
        }

        $content = new SiteContentRepository();
        foreach ($content->listActiveSlugs() as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '' || in_array($slug, ['home', 'catalog', 'global'], true)) {
                continue;
            }
            $livePath = $this->pathForCmsSlug($slug);
            $this->writeHtml(
                $livePath,
                (string) ($row['title'] ?? $slug),
                (string) ($row['meta_description'] ?? ''),
                'Pagină informativă Besoiu Piese Auto.',
                'cms-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($slug)) . '.html'
            );
            $count++;
        }

        $this->writeSitemap();

        return $count;
    }

    private function introForModule(string $moduleId): string
    {
        return match ($moduleId) {
            'home' => 'Magazin online de piese auto — catalog, căutare TecDoc, livrare în România.',
            'catalog' => 'Răsfoiește catalogul complet de piese auto după categorie, marcă sau cod OEM.',
            'product' => 'Detalii produs, compatibilitate și adăugare în coș.',
            'cart' => 'Finalizează comanda de piese auto.',
            'blog' => 'Articole și ghiduri despre piese auto.',
            default => 'Besoiu Piese Auto — piese auto de calitate.',
        };
    }

    private function pathForCmsSlug(string $slug): string
    {
        $static = [
            'contact' => '/contact',
            'about' => '/despre',
            'cum-comand' => '/cum-comand',
        ];

        return $static[$slug] ?? '/p/' . $slug;
    }

    private function writeHtml(string $livePath, string $title, string $description, string $bodyIntro, ?string $filename = null): void
    {
        $canonical = rtrim($this->baseUrl, '/') . ($livePath === '/' ? '/' : $livePath);
        $file = $filename ?? trim(str_replace('/', '-', $livePath), '-') . '.html';
        if ($file === '.html' || $file === 'html') {
            $file = 'index.html';
        }

        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$esc($title)} — Besoiu Piese Auto</title>
<meta name="description" content="{$esc($description)}">
<link rel="canonical" href="{$esc($canonical)}">
<meta http-equiv="refresh" content="0;url={$esc($canonical)}">
</head>
<body>
<main>
<h1>{$esc($title)}</h1>
<p>{$esc($bodyIntro)}</p>
<p><a href="{$esc($canonical)}">Deschide pagina live: {$esc($livePath)}</a></p>
</main>
</body>
</html>
HTML;

        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . $file, $html);
    }

    private function writeSitemap(): void
    {
        $generator = new SitemapGenerator($this->root, rtrim($this->baseUrl, '/'));
        $generator->generateAll();
    }
}
