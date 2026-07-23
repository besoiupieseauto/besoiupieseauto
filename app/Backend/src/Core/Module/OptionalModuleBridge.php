<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Verifică dacă un modul opțional din modules/{id}/ este instalat și activ.
 * Fără referințe hardcodate la clase din modul la compile-time.
 */
final class OptionalModuleBridge
{
    public static function isInstalled(string $moduleId): bool
    {
        $folder = self::folderFor($moduleId);
        if ($folder === '') {
            return false;
        }

        return is_file(ModulesPaths::modulesRoot() . '/' . $folder . '/module.json');
    }

    public static function isOperational(string $moduleId): bool
    {
        return ModuleGate::enabled($moduleId) && self::isInstalled($moduleId);
    }

    public static function furnizoriAvailable(): bool
    {
        return self::isOperational('furnizori');
    }

    /**
     * @return class-string|null
     */
    public static function resolveClass(string $moduleId, string $relativeClass): ?string
    {
        if (!self::isOperational($moduleId)) {
            return null;
        }

        $studly = self::studlyModuleId($moduleId);
        $fqcn = 'Besoiu\\Modules\\' . $studly . '\\' . ltrim(str_replace('/', '\\', $relativeClass), '\\');

        return class_exists($fqcn) ? $fqcn : null;
    }

    public static function folderFor(string $moduleId): string
    {
        try {
            foreach (ModuleGate::optionalMap() as $id => $meta) {
                if ((string) $id === $moduleId && !empty($meta['folder'])) {
                    return (string) $meta['folder'];
                }
            }
        } catch (\Throwable) {
            // optionalMap indisponibil
        }

        return match ($moduleId) {
            'furnizori' => 'furnizori',
            'users' => 'users',
            'clienti' => 'clienti',
            'supplier_search' => 'supplier_search',
            'scraper' => 'scraper',
            'scraper_web' => 'scraper_web',
            default => '',
        };
    }

    private static function studlyModuleId(string $moduleId): string
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', $moduleId) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $out .= ucfirst(strtolower($part));
            }
        }

        return $out !== '' ? $out : 'Module';
    }
}
