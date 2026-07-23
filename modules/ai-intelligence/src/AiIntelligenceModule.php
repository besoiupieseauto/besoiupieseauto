<?php

declare(strict_types=1);

namespace Besoiu\Modules\AiIntelligence;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Modul AI Intelligence — migrări tracking + boot servicii (Pas 2+).
 */
final class AiIntelligenceModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        // Serviciile sunt în Besoiu\Services\AiIntelligence\ — instanțiate la cerere.
    }
}
