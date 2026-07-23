<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Settings;

/**
 * @deprecated Mutat în Besoiu\Services\SettingsService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Settings\SettingsService', false)) {
    class_alias(\Besoiu\Services\SettingsService::class, 'Besoiu\Controllers\Settings\SettingsService');
}
