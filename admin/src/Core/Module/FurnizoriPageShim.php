<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Fallback Templates legacy → pagini din modules/furnizori/pages/.
 */
final class FurnizoriPageShim
{
    public static function render(string $page): void
    {
        OptionalModulePageShim::render('furnizori', $page);
    }

    public static function renderPartial(string $relativePath): void
    {
        if (!OptionalModuleBridge::furnizoriAvailable()) {
            return;
        }

        $path = ModulesPaths::modulesRoot() . '/furnizori/pages/' . ltrim($relativePath, '/');
        if (is_file($path)) {
            require $path;
        }
    }

    private static function renderDisabled(): void
    {
        echo '<div class="admin-panel p-6"><p class="text-slate-600">Modulul <strong>Furnizori B2B</strong> este dezactivat. Activează-l din <a href="/admin/settings">Setări → Module</a>.</p></div>';
    }

    private static function renderMissingModule(): void
    {
        echo '<div class="admin-panel p-6"><p class="text-slate-600">Pachetul <code>modules/furnizori/</code> lipsește. Reinstalează modulul sau dezactivează-l din Setări → Module.</p></div>';
    }

    private static function renderMissingPage(string $page): void
    {
        echo '<div class="admin-panel p-6"><p class="text-slate-600">Pagina modulului <code>' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '</code> nu a fost găsită.</p></div>';
    }
}
