<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

use Besoiu\Core\Bootstrap\ApiBootstrap;
use RuntimeException;

/**
 * Dispatch API din pachetul modulului (modules/{Folder}/api/).
 */
final class ModuleApiDispatcher
{
    public static function dispatch(string $moduleId, string $script): void
    {
        $moduleId = strtolower(trim($moduleId));
        $script = basename(trim($script));

        if ($moduleId === '' || $script === '' || !preg_match('/^[a-zA-Z0-9_.-]+\.php$/', $script)) {
            throw new RuntimeException('Parametri API modul invalizi.');
        }

        if (!ModuleGate::enabled($moduleId)) {
            ApiBootstrap::json(['success' => false, 'message' => 'Modul dezactivat: ' . $moduleId], 403);
        }

        $meta = ModuleGate::optionalMap()[$moduleId] ?? null;
        if (!is_array($meta)) {
            throw new RuntimeException('Modul necunoscut: ' . $moduleId);
        }

        $allowed = $meta['api_scripts'] ?? [];
        if (!is_array($allowed) || !in_array($script, $allowed, true)) {
            ApiBootstrap::json([
                'success' => false,
                'message' => 'Script API neînregistrat pentru modulul „' . $moduleId . '”.',
            ], 403);
        }

        $folder = (string) ($meta['folder'] ?? '');
        if ($folder === '') {
            $folder = self::studly($moduleId);
        }

        $modulesRoot = ModulesPaths::modulesRoot();
        $path = $modulesRoot . DIRECTORY_SEPARATOR . $folder
            . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . $script;
        $realModules = realpath($modulesRoot);
        $realFile = realpath($path);

        if ($realModules === false || $realFile === false || !str_starts_with($realFile, $realModules)) {
            ApiBootstrap::json(['success' => false, 'message' => 'Endpoint modul inexistent.'], 404);
        }

        // Feature key din permissions[0] — doar dacă există sesiune (proxy deja a autentificat)
        if (!empty($_SESSION['user_id'])) {
            $registry = ModuleRegistry::instance(ModulesPaths::adminRoot());
            $registry->discover();
            $manifest = $registry->allManifests()[$moduleId] ?? null;
            if ($manifest !== null) {
                $perms = $manifest->permissions();
                if ($perms !== []) {
                    ApiBootstrap::requireAdminFeature($perms[0]);
                }
            }
        }

        require $realFile;
        exit;
    }

    private static function studly(string $id): string
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', $id) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $out .= ucfirst(strtolower($part));
            }
        }

        return $out !== '' ? $out : 'Module';
    }
}

