<?php

declare(strict_types=1);

namespace Storefront\Modules\Blog;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\AbstractPageController;
use Storefront\Core\Template\ThemeRenderer;

final class BlogController extends AbstractPageController
{
    private const PATH_FILE = [
        '/blog' => 'blog.php',
        '/articol' => 'blog-articol.php',
    ];

    public function __construct(ThemeRenderer $theme)
    {
        parent::__construct($theme);
    }

    public function handle(StorefrontRequest $request): void
    {
        $file = self::PATH_FILE[$request->path] ?? 'blog.php';
        $this->renderLegacy($this->viewsPath($file));
    }
}
