<?php

declare(strict_types=1);

namespace Storefront\Modules\Account;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\AbstractPageController;
use Storefront\Core\Template\ThemeRenderer;

final class AccountController extends AbstractPageController
{
    private const PATH_FILE = [
        '/cont' => 'cont.php',
        '/autentificare' => 'autentificare.php',
        '/inregistrare' => 'inregistrare.php',
    ];

    public function __construct(ThemeRenderer $theme)
    {
        parent::__construct($theme);
    }

    public function handle(StorefrontRequest $request): void
    {
        $file = self::PATH_FILE[$request->path] ?? null;
        if ($file === null) {
            http_response_code(404);
            echo 'Pagina nu există.';
            return;
        }
        $this->renderLegacy($this->viewsPath($file));
    }
}
