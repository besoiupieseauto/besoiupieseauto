<?php

declare(strict_types=1);

namespace Storefront\Modules\Cms;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\AbstractPageController;
use Storefront\Core\Template\ThemeRenderer;

final class CmsPageController extends AbstractPageController
{
    public function __construct(ThemeRenderer $theme)
    {
        parent::__construct($theme);
    }

    public function handle(StorefrontRequest $request): void
    {
        if (!str_starts_with($request->path, '/p/')) {
            http_response_code(404);
            echo 'Pagina CMS nu există.';
            return;
        }

        $_GET['slug'] = substr($request->path, 3);
        $this->renderLegacy($this->viewsPath('cms-page.php'));
    }
}
