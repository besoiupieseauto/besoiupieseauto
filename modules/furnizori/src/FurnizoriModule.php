<?php
declare(strict_types=1);



namespace Besoiu\Modules\Furnizori;



use Besoiu\Core\Module\AbstractModule;

use Besoiu\Core\Module\ModuleContext;



final class FurnizoriModule extends AbstractModule

{

    public function boot(ModuleContext $context): void

    {

        if (!$context->isCoreAvailable()) {

            return;

        }



        FurnizoriModuleHooks::register();

    }

}
