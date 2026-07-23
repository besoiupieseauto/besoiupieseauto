<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Scraper;

/**
 * @deprecated Mutat în Besoiu\Services\EpiesaScraperService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Scraper\EpiesaScraperService', false)) {
    class_alias(\Besoiu\Services\EpiesaScraperService::class, 'Besoiu\Controllers\Scraper\EpiesaScraperService');
}
