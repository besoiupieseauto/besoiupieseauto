<?php

declare(strict_types=1);

namespace Storefront\Modules\Cart;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\AbstractPageController;
use Storefront\Core\Template\ThemeRenderer;

final class CartController extends AbstractPageController
{
    public function __construct(ThemeRenderer $theme)
    {
        parent::__construct($theme);
    }

    public function handle(StorefrontRequest $request): void
    {
        $this->renderLegacy($this->viewsPath('cart.php'));
    }
}
