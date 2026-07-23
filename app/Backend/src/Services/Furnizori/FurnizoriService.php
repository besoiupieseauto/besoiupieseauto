<?php

declare(strict_types=1);

namespace Besoiu\Services\Furnizori;

use Besoiu\Core\Module\DelegatesToOptionalFurnizoriModule;

/** Bridge CORE → modules/furnizori/ (proxy, fără extends). */
class FurnizoriService
{
    use DelegatesToOptionalFurnizoriModule;

    protected static function furnizoriModuleRelativeClass(): string
    {
        return 'Service\\FurnizoriService';
    }
}
