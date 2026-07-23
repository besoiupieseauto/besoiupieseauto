<?php

declare(strict_types=1);

namespace Storefront\Modules\Product;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\AbstractPageController;
use Storefront\Core\Template\ThemeRenderer;

final class ProductController extends AbstractPageController
{
    private readonly ProductService $service;

    public function __construct(ThemeRenderer $theme)
    {
        parent::__construct($theme);
        $this->service = new ProductService();
    }

    public function handle(StorefrontRequest $request): void
    {
        $legacy = $this->viewsPath('product.php');
        if (is_file($legacy)) {
            $this->renderLegacy($legacy);
            return;
        }

        $this->render($this->service->buildPageView($request));
    }
}
