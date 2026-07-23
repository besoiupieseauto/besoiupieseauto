<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Căi centralizate pentru module admin la rădăcina proiectului (lângă admin/).
 */
final class ModulesPaths
{
    public static function projectRoot(): string
    {
        if (defined('BESOIU_ROOT')) {
            return (string) BESOIU_ROOT;
        }

        return dirname(__DIR__, 4);
    }

    public static function modulesRoot(): string
    {
        if (defined('BESOIU_ADMIN_MODULES')) {
            return (string) BESOIU_ADMIN_MODULES;
        }

        return self::projectRoot() . DIRECTORY_SEPARATOR . 'modules';
    }

    public static function adminRoot(): string
    {
        if (defined('BESOIU_ADMIN')) {
            return (string) BESOIU_ADMIN;
        }

        return self::projectRoot() . DIRECTORY_SEPARATOR . 'admin';
    }

    public static function backendRoot(): string
    {
        if (defined('BESOIU_BACKEND')) {
            return (string) BESOIU_BACKEND;
        }

        return self::projectRoot() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Backend';
    }

    public static function stateFile(): string
    {
        return self::adminRoot()
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'state.json';
    }

    /** Cale relativă document root → pages modul (ex. /modules/users/pages/) */
    public static function modulePagesWebPath(string $folder): string
    {
        return '/modules/' . trim(str_replace('\\', '/', $folder), '/') . '/pages/';
    }
}
