<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Template/ThemeRenderer.php
 * ============================================================================
 * Scop: Randează paginile HTML ale vitrinei: head (meta, CSS, JS config),
 *       header/footer legacy, template-ul paginii și scripturile deferred.
 *
 * Include/require:
 *   - app/Legacy/page-init.php, site-content.php, site-live-cms.php
 *   - app/Legacy/site-builder.php, besoiu-assets.php, storefront-config.php
 *   - app/Legacy/header.php, footer.php — structura comună a paginii
 *
 * Bază de date: Nu direct; fișierele legacy incluse pot accesa DB.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Template;

/**
 * Renderer temă storefront — asamblează HTML complet din PageView + legacy partials.
 */
final class ThemeRenderer
{
    /**
     * Randează o pagină completă din obiectul PageView (template + date + assets).
     *
     * @param PageView $view DTO cu templatePath, data, assetProfile, scripts, styles
     * Nu returnează valoare; trimite HTML direct via echo/include.
     */
    public function render(PageView $view): void
    {
        // Calea către fișierele legacy partajate vitrină + admin
        $legacy = defined('BESOIU_LEGACY') ? BESOIU_LEGACY : (BESOIU_ROOT . '/app/Legacy');

        // Încarcă utilitare legacy necesare randării (CMS, assets, config JS)
        require_once $legacy . '/page-init.php';
        require_once $legacy . '/site-content.php';
        require_once $legacy . '/site-live-cms.php';
        require_once $legacy . '/site-builder.php';
        require_once $legacy . '/besoiu-assets.php';
        require_once $legacy . '/storefront-config.php';

        // Pagină CMS dinamică — transmite slug-ul către legacy via GLOBALS
        if ($view->cmsPage !== '') {
            $GLOBALS['bpaCmsPage'] = $view->cmsPage;
        }

        // Extrage variabilele din $view->data pentru template (variabile locale în scope)
        $data = $view->data;
        extract($data, EXTR_SKIP);

        // Meta SEO din datele paginii
        $meta = $data['meta'] ?? [];
        $title = (string) ($meta['title'] ?? 'Besoiu Piese Auto');
        $description = (string) ($meta['description'] ?? '');

        // --- Construire HTML head ---
        echo '<!DOCTYPE html>', "\n";
        echo '<html lang="ro">', "\n";
        echo '<head>', "\n";
        echo '<meta charset="utf-8">', "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">', "\n";
        echo '<title>', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), '</title>', "\n";
        if ($description !== '') {
            echo '<meta name="description" content="', htmlspecialchars($description, ENT_QUOTES, 'UTF-8'), '">', "\n";
        }
        if (!empty($meta['canonical'])) {
            echo '<link rel="canonical" href="', htmlspecialchars((string) $meta['canonical'], ENT_QUOTES, 'UTF-8'), '">', "\n";
        }
        // Fonturi, CSS și config JS storefront (profil: home, shop, etc.)
        besoiu_render_fonts($view->assetProfile === 'home');
        besoiu_render_styles($view->assetProfile, $view->styles);
        besoiu_render_storefront_config_script();
        echo '</head>', "\n";
        echo '<body>', "\n";
        echo '<div class="page">', "\n";

        // Header comun (navigare, logo)
        include $legacy . '/header.php';

        // Template specific paginii sau mesaj de eroare dacă lipsește fișierul
        if (!is_file($view->templatePath)) {
            echo '<main class="container"><p>Template lipsă.</p></main>';
        } else {
            include $view->templatePath;
        }

        // Footer comun
        include $legacy . '/footer.php';
        echo '</div>', "\n";
        besoiu_render_scripts($view->assetProfile, $view->scripts);
        besoiu_render_widget_deferred();
        echo '</body></html>';
    }

    /**
     * Randează o pagină legacy standalone (fără wrapper ThemeRenderer).
     *
     * @param string $legacyPagePath Cale absolută către fișierul PHP legacy
     */
    public function renderLegacy(string $legacyPagePath): void
    {
        if (!is_file($legacyPagePath)) {
            http_response_code(404);
            echo 'Pagina nu există.';
            return;
        }

        require $legacyPagePath;
    }
}
