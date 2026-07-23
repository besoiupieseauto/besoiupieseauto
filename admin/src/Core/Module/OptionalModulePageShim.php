<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Renderează pagini PHP din modules/{id}/pages/ când modulul e activ.
 */
final class OptionalModulePageShim
{
    public static function render(string $moduleId, string $page): void
    {
        if (!ModuleGate::enabled($moduleId)) {
            self::renderDisabled($moduleId);

            return;
        }

        if (!OptionalModuleBridge::isInstalled($moduleId)) {
            self::renderMissingModule($moduleId);

            return;
        }

        $folder = OptionalModuleBridge::folderFor($moduleId);
        if ($folder === '') {
            self::renderMissingModule($moduleId);

            return;
        }

        $path = ModulesPaths::modulesRoot() . '/' . $folder . '/pages/' . ltrim($page, '/');
        if (!is_file($path)) {
            self::renderMissingPage($page);

            return;
        }

        require $path;
    }

    private static function renderDisabled(string $moduleId): void
    {
        echo '<div class="admin-panel p-6"><p class="text-slate-600">Modulul <strong>'
            . htmlspecialchars($moduleId, ENT_QUOTES, 'UTF-8')
            . '</strong> este dezactivat. Activează-l din <a href="/admin/settings">Setări → Module</a>.</p></div>';
    }

    private static function renderMissingModule(string $moduleId): void
    {
        $folder = OptionalModuleBridge::folderFor($moduleId) ?: $moduleId;
        echo '<div class="admin-panel p-6"><p class="text-slate-600">Pachetul <code>modules/'
            . htmlspecialchars($folder, ENT_QUOTES, 'UTF-8')
            . '/</code> lipsește.</p></div>';
    }

    private static function renderMissingPage(string $page): void
    {
        echo '<div class="admin-panel p-6"><p class="text-slate-600">Pagina modulului <code>'
            . htmlspecialchars($page, ENT_QUOTES, 'UTF-8')
            . '</code> nu a fost găsită.</p></div>';
    }
}
