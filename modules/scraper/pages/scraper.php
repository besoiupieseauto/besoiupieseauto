<?php
declare(strict_types=1);

/**
 * Pagină modul scraper — delegă UI legacy din admin/Templates (mutare treptată).
 */
$legacyPage = dirname(__DIR__, 3)
    . '/admin/Templates/admin/pages/scraper/scraper.php';

if (!is_file($legacyPage)) {
    echo '<div class="admin-panel p-6"><p class="text-slate-600">Pagina scraper legacy lipsește.</p></div>';

    return;
}

require $legacyPage;
