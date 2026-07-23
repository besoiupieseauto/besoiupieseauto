<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Module/AbstractPageController.php
 * ============================================================================
 * Scop: Clasă de bază abstractă pentru controllerii de pagină storefront.
 *       Oferă metode helper pentru randare (render, renderLegacy) și căi utilitare.
 *
 * Include/require: Niciun fișier direct; folosește ThemeRenderer injectat.
 *
 * Bază de date: Subclasele concrete accesează DB via Connection sau repository.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Module;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Template\PageView;
use Storefront\Core\Template\ThemeRenderer;

/**
 * Controller abstract pagină — implementează PageControllerInterface parțial.
 * Subclasele definesc handle(); această clasă oferă utilitare comune.
 */
abstract class AbstractPageController implements PageControllerInterface
{
    /**
     * @param ThemeRenderer $theme Renderer injectat de ModuleRegistry la instanțiere
     */
    public function __construct(
        protected readonly ThemeRenderer $theme,
    ) {
    }

    /** Fiecare pagină implementează propria logică de request */
    abstract public function handle(StorefrontRequest $request): void;

    /**
     * Delegă randarea către ThemeRenderer cu un PageView pregătit.
     *
     * @param PageView $view DTO cu template, date și assets
     */
    protected function render(PageView $view): void
    {
        $this->theme->render($view);
    }

    /**
     * Include direct un fișier PHP legacy (fără wrapper temă).
     *
     * @param string $absolutePath Cale absolută către pagina legacy
     */
    protected function renderLegacy(string $absolutePath): void
    {
        $this->theme->renderLegacy($absolutePath);
    }

    /** @return string Rădăcina proiectului (BESOIU_ROOT sau calculată) */
    protected function root(): string
    {
        return defined('BESOIU_ROOT') ? BESOIU_ROOT : dirname(__DIR__, 3);
    }

    /**
     * Construiește cale absolută către un fișier din app/Views/.
     *
     * @param string $file Cale relativă (ex. shop/product.php)
     */
    protected function viewsPath(string $file): string
    {
        return (defined('BESOIU_VIEWS') ? BESOIU_VIEWS : $this->root() . '/app/Views') . '/' . ltrim($file, '/');
    }
}
