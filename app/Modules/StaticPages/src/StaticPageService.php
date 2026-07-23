<?php

declare(strict_types=1);

namespace Storefront\Modules\StaticPages;

use Storefront\Core\Repository\SiteContentRepository;
use Storefront\Core\Template\PageView;

final class StaticPageService
{
    public function __construct(
        private readonly SiteContentRepository $content = new SiteContentRepository(),
    ) {
    }

    public function buildPageView(string $slug, string $path): PageView
    {
        $root = defined('BESOIU_ROOT') ? BESOIU_ROOT : dirname(__DIR__, 3);
        require_once BESOIU_LEGACY . '/site-defaults.php';

        $row = $this->content->findActiveBySlug($slug);
        $defaults = site_defaults_page_meta($slug);

        return new PageView(
            templatePath: dirname(__DIR__) . '/templates/static.php',
            data: [
                'meta' => [
                    'title' => (string) ($row['title'] ?? $defaults['title'] ?? $slug),
                    'description' => (string) ($row['meta_description'] ?? $defaults['description'] ?? ''),
                    'canonical' => besoiu_absolute_url($path),
                ],
                'slug' => $slug,
                'blocks' => $row ? $this->content->decodeSections($row['sections_json'] ?? null) : [],
            ],
            assetProfile: 'minimal',
            styles: ['assets/css/static-pages.css'],
            cmsPage: $slug,
        );
    }
}
