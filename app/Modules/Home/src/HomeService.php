<?php

declare(strict_types=1);

namespace Storefront\Modules\Home;

use Storefront\Core\Repository\SiteContentRepository;
use Storefront\Core\Template\PageView;

final class HomeService
{
    public function __construct(
        private readonly SiteContentRepository $content = new SiteContentRepository(),
    ) {
    }

    public function buildPageView(): PageView
    {
        $root = defined('BESOIU_ROOT') ? BESOIU_ROOT : dirname(__DIR__, 3);
        require_once BESOIU_LEGACY . '/site-defaults.php';

        $row = $this->content->findActiveBySlug('home');
        $blocks = $row
            ? array_merge(site_defaults_blocks('home'), $this->content->decodeSections($row['sections_json'] ?? null))
            : site_defaults_blocks('home');

        $meta = [
            'title' => (string) ($row['title'] ?? site_defaults_page_meta('home')['title'] ?? 'Besoiu Piese Auto'),
            'description' => (string) ($row['meta_description'] ?? site_defaults_page_meta('home')['description'] ?? ''),
            'canonical' => besoiu_absolute_url('/'),
        ];

        return new PageView(
            templatePath: dirname(__DIR__) . '/templates/home.php',
            data: ['meta' => $meta, 'blocks' => $blocks],
            assetProfile: 'home',
            scripts: ['assets/js/home-tecdoc.js'],
            cmsPage: 'home',
        );
    }
}
