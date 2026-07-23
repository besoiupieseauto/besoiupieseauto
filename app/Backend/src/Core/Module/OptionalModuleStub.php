<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Entry generic pentru module opționale care nu au logică boot custom
 * (totul vine din module.json + optional_modules.php).
 */
final class OptionalModuleStub extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
    }
}
