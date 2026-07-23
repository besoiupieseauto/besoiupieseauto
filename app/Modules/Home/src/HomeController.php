<?php

declare(strict_types=1);

namespace Storefront\Modules\Home;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\AbstractPageController;
use Storefront\Core\Template\ThemeRenderer;

final class HomeController extends AbstractPageController
{
    private readonly HomeService $service;

    public function __construct(ThemeRenderer $theme)
    {
        parent::__construct($theme);
        $this->service = new HomeService();
    }

    public function handle(StorefrontRequest $request): void
    {
        $legacy = $this->viewsPath('home.php');
        if (is_file($legacy)) {
            $this->renderLegacy($legacy);
            return;
        }

        $this->render($this->service->buildPageView());
    }
}
