<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Template/PageView.php
 * ============================================================================
 * Scop: DTO (Data Transfer Object) care transportă toate datele necesare
 *       randării unei pagini: template, variabile, profil assets, scripturi.
 *
 * Include/require: Niciun fișier extern.
 * Bază de date: Nu are legătură directă.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Template;

/**
 * DTO view — date + template + profil assets.
 * Obiect imutabil (readonly) transmis de controller la ThemeRenderer.
 */
final class PageView
{
    /**
     * @param string $templatePath  Cale absolută către fișierul .php al template-ului
     * @param array<string, mixed> $data Variabile disponibile în template via extract()
     * @param string $assetProfile  Profil CSS/JS (ex. 'home', 'shop', 'blog')
     * @param list<string> $scripts Fișiere JS suplimentare de încărcat
     * @param list<string> $styles  Fișiere CSS suplimentare de încărcat
     * @param string $cmsPage       Slug pagină CMS pentru rute /p/{slug}
     */
    public function __construct(
        public readonly string $templatePath,
        public readonly array $data = [],
        public readonly string $assetProfile = 'shop',
        public readonly array $scripts = [],
        public readonly array $styles = [],
        public readonly string $cmsPage = '',
    ) {
    }
}
