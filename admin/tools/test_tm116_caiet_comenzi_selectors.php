<?php
declare(strict_types=1);

/**
 * Smoke test tm_116 — Portare Caiet de comenzi în tab-ul Comenzi admin (selectori HTML/JS).
 */
require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: {$msg}\n";
};

$comenziFile = $admin . '/Templates/admin/pages/comenzi/comenzi.php';
$stubFile = $admin . '/Templates/admin/pages/caietcomenzi/caietcomenzi.php';
$adminFile = $admin . '/src/Controllers/Admin.php';
$endpointFile = $admin . '/public/api/caiet_comenzi_endpoint.php';

foreach ([$comenziFile, $stubFile, $adminFile, $endpointFile] as $path) {
    if (!is_file($path)) {
        $fail('Lipseste fisier: ' . $path);
    }
}
$ok('Fisiere tm_116 prezente');

$comenziSrc = file_get_contents($comenziFile) ?: '';

$tabSelectors = [
    'class="comenzi-tab-btn',
    'data-tab="tm"',
    'data-tab="utvin"',
    'data-tab="ext"',
    'data-tab="standard"',
    'id="comenzi-tab-tm"',
    'id="comenzi-tab-utvin"',
    'id="comenzi-tab-ext"',
    'id="comenzi-tab-standard"',
    'data-legacy-tab="tm"',
    'data-legacy-tab="utvin"',
    'data-legacy-tab="ext"',
];
foreach ($tabSelectors as $needle) {
    if (!str_contains($comenziSrc, $needle)) {
        $fail("comenzi.php — selector tab lipsa: {$needle}");
    }
}
$ok('comenzi.php — 4 tab-uri (standard + TM/UTVIN/externe)');

$legacySelectors = [
    'class="besoiu-legacy-panel"',
    'data-legacy-panel',
    'legacy-search',
    'legacy-status',
    'legacy-date-from',
    'legacy-date-to',
    'legacy-refresh',
    'legacy-body',
    'legacy-stat-month',
    'legacy-stat-day',
    'legacy-stat-revenue',
    'id="legacy-comenzi-bootstrap"',
    'id="legacy-comenzi-modal"',
    'id="legacy-comenzi-close-modal"',
    'id="legacy-comenzi-save-status"',
    'id="legacy-comenzi-lines-body"',
    'id="legacy-comenzi-new-status"',
    'data-action="legacy-details"',
    'legacy-expand',
    '/admin/api/caiet_comenzi_endpoint.php',
    'legacy_tab',
    'besoiu-toolbar',
    'besoiu-filters',
    'besoiu-kpi-strip',
    'besoiu-data-table',
];
foreach ($legacySelectors as $needle) {
    if (!str_contains($comenziSrc, $needle)) {
        $fail("comenzi.php — selector legacy lipsa: {$needle}");
    }
}
$ok('comenzi.php — panouri legacy + modal + endpoint JS');

$adminSrc = file_get_contents($adminFile) ?: '';
foreach (['caietcomenzi', 'comenzi-tm', 'comenzi-utvin', 'comenzi-externe', 'legacy_tab'] as $needle) {
    if (!str_contains($adminSrc, $needle)) {
        $fail("Admin.php — redirect legacy lipsa: {$needle}");
    }
}
$ok('Admin.php — redirecturi catre /admin/orders?legacy_tab=');

$stubSrc = file_get_contents($stubFile) ?: '';
if (!str_contains($stubSrc, '/admin/orders?legacy_tab=tm') && !str_contains($stubSrc, 'legacy_tab=tm')) {
    $fail('caietcomenzi.php — stub 302 catre orders?legacy_tab=tm lipsa');
}
$ok('caietcomenzi.php — stub redirect 302');

$endpointSrc = file_get_contents($endpointFile) ?: '';
foreach (['list', 'details', 'setstatus', 'stats_location'] as $action) {
    if (!str_contains($endpointSrc, $action)) {
        $fail("caiet_comenzi_endpoint.php — actiune lipsa: {$action}");
    }
}
$ok('caiet_comenzi_endpoint.php — actiuni API legacy');

if (!str_contains($comenziSrc, "btn.dataset.active === '1'") && !str_contains($comenziSrc, 'dataset.active')) {
    $fail('comenzi.php — reloadActiveLegacyTab nu foloseste data-active');
}
$ok('comenzi.php — reload tab activ via data-active/besoiu-tabs__btn--active');

echo "\nTM_116 Caiet comenzi in Comenzi admin: toate verificarile statice au trecut.\n";
