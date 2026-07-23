<?php

declare(strict_types=1);

namespace Storefront\Modules\Catalog;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\AbstractPageController;
use Storefront\Core\Template\ThemeRenderer;

final class CatalogController extends AbstractPageController
{
    private readonly CatalogService $service;

    public function __construct(ThemeRenderer $theme)
    {
        parent::__construct($theme);
        $this->service = new CatalogService();
    }

    public function handle(StorefrontRequest $request): void
    {
        $legacy = $this->viewsPath('catalog.php');
        if (is_file($legacy)) {
            $this->renderLegacy($legacy);
            return;
        }

        $this->render($this->service->buildPageView($request));
    }
}
