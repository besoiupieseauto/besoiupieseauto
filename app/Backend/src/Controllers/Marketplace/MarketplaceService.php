<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Marketplace;

/**
 * @deprecated Mutat în Besoiu\Services\Marketplace\MarketplaceService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Marketplace\MarketplaceService', false)) {
    class_alias(\Besoiu\Services\Marketplace\MarketplaceService::class, 'Besoiu\Controllers\Marketplace\MarketplaceService');
}
