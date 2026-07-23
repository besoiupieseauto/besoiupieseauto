<?php
declare(strict_types=1);

namespace Besoiu\Modules\ImportPro\Support;

use Besoiu\Modules\ImportPro\Support\ImportProBridge;
use Throwable;

final class ImportProPageRenderer
{
    public static function renderAdminPage(): void
    {
        try {
            ImportProBridge::boot();
            $standalone = ImportProBridge::standaloneUrl();

            echo '<div class="import-pro-erp-wrap import-pro-inline-host" id="import-pro-module">';
            echo '<div class="import-pro-toolbar ip-toolbar-erp">';
            echo '<div class="ip-toolbar-erp__title"><span class="ip-icon ip-icon--sm">' . self::iconSvg('zap') . '</span> Import Pro</div>';
            echo '<div class="ip-toolbar-erp__actions">';
            echo '<a href="' . htmlspecialchars($standalone, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="ip-btn ip-btn--ghost ip-btn--sm">Tab nou</a>';
            echo '<button type="button" id="import-pro-reload" class="ip-btn ip-btn--ghost ip-btn--sm">Reîncarcă</button>';
            echo '</div></div>';

            if (!defined('BESOIU_IMPORT_EMBED_INLINE')) {
                define('BESOIU_IMPORT_EMBED_INLINE', true);
            }
            if (!defined('BESOIU_IMPORT_PUBLIC_WRAPPER')) {
                define('BESOIU_IMPORT_PUBLIC_WRAPPER', true);
            }
            if (!defined('BESOIU_IMPORT_WEB_BASE')) {
                define('BESOIU_IMPORT_WEB_BASE', rtrim($standalone, '/') . '/');
            }
            if (!defined('BESOIU_IMPORT_SCRAPER_API')) {
                define('BESOIU_IMPORT_SCRAPER_API', ImportProBridge::scraperProxyUrl());
            }

            require ImportProBridge::root() . '/index.php';

            echo '</div>';
            echo '<script>document.getElementById("import-pro-reload")?.addEventListener("click",function(){location.reload();});</script>';
        } catch (Throwable $e) {
            echo '<div class="admin-panel p-6"><p class="text-red-600">Import Pro: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></div>';
        }
    }

    private static function iconSvg(string $name): string
    {
        $paths = [
            'zap' => '<path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z" fill="currentColor" stroke="none"/>',
        ];
        $inner = $paths[$name] ?? '<circle cx="12" cy="12" r="4"/>';
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">' . $inner . '</svg>';
    }
}
