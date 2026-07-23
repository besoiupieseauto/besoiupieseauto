<?php

declare(strict_types=1);

namespace Storefront\Modules\StaticPages;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\AbstractPageController;
use Storefront\Core\Repository\SiteContentRepository;
use Storefront\Core\Template\ThemeRenderer;

final class StaticPageController extends AbstractPageController
{
    private const PATH_MAP = [
        '/contact' => ['file' => 'contact.php', 'slug' => 'contact'],
        '/despre' => ['file' => 'about.php', 'slug' => 'about'],
        '/cum-comand' => ['file' => 'cum-comand.php', 'slug' => 'cum-comand'],
        '/livrare-plata' => ['file' => 'livrare-plata.php', 'slug' => 'livrare-plata'],
        '/retur-garantie' => ['file' => 'retur-garantie.php', 'slug' => 'retur-garantie'],
        '/intrebari-frecvente' => ['file' => 'intrebari-frecvente.php', 'slug' => 'intrebari-frecvente'],
        '/termeni-conditii' => ['file' => 'termeni-conditii.php', 'slug' => 'termeni-conditii'],
        '/politica-confidentialitate' => ['file' => 'politica-confidentialitate.php', 'slug' => 'politica-confidentialitate'],
        '/politica-cookies' => ['file' => 'politica-cookies.php', 'slug' => 'politica-cookies'],
        '/cariere' => ['file' => 'cariere.php', 'slug' => 'cariere'],
    ];

    private readonly StaticPageService $service;

    public function __construct(ThemeRenderer $theme)
    {
        parent::__construct($theme);
        $this->service = new StaticPageService();
    }

    public function handle(StorefrontRequest $request): void
    {
        $map = self::PATH_MAP[$request->path] ?? null;
        if ($map === null) {
            http_response_code(404);
            echo 'Pagina nu există.';
            return;
        }

        $legacy = $this->viewsPath($map['file']);
        if (is_file($legacy)) {
            $this->renderLegacy($legacy);
            return;
        }

        $this->render($this->service->buildPageView($map['slug'], $request->path));
    }
}
