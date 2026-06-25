<?php

declare(strict_types=1);

$base = rtrim(getenv('EVASYSTEM_WEB_BASE') ?: 'http://besoiupieseauto.ro.test', '/');
$failures = [];

require_once dirname(__DIR__) . '/vendor/autoload.php';

$service = new Evasystem\Controllers\Categorii\CategoriiService();
if ($service->isTecdocStructureImportEnabled()) {
    $failures[] = 'CategoriiService::isTecdocStructureImportEnabled() trebuie false (import amânat).';
} else {
    echo "OK CategoriiService — import structuri TecDoc dezactivat (tm_021)\n";
}

$categoriiTemplate = dirname(__DIR__) . '/Templates/admin/pages/categorii/categorii.php';
$templateSource = is_file($categoriiTemplate) ? (string) file_get_contents($categoriiTemplate) : '';
$requiredSelectors = [
    'id="tecdoc-structure-deferred-banner"',
    'id="btn-tecdoc-reference"',
    'data-action="open-tecdoc-reference"',
    'id="tecdocModal"',
    'data-tm021="reference-only"',
    'id="tecdoc-reference-notice"',
    'data-tm021="deferred-import"',
    'categorii-admin-page',
    'categorii-overlay-modal',
    'setCategoriiModalOpen',
    'Referință TecDoc',
    'Referință catalog TecDoc',
    'TECDOC_STRUCTURE_IMPORT_ENABLED',
    'openTecdocModal',
    'tdLoadCategories',
    'id="td_marca"',
    'id="td_model"',
    'id="td_motor"',
    'id="td_load_btn"',
    'id="td_categories_list"',
];
$categoriiSelectorFailures = [];
if ($templateSource === '') {
    $categoriiSelectorFailures[] = 'categorii.php — fișier lipsă.';
} else {
    foreach ($requiredSelectors as $selector) {
        if (!str_contains($templateSource, $selector)) {
            $categoriiSelectorFailures[] = 'selector lipsă: ' . $selector;
        }
    }
    if (str_contains($templateSource, 'Adaugă selectate') || str_contains($templateSource, 'tdImportSelected')) {
        $categoriiSelectorFailures[] = 'acțiune import structuri TecDoc încă prezentă în UI';
    }
}
if ($categoriiSelectorFailures !== []) {
    foreach ($categoriiSelectorFailures as $catFail) {
        $failures[] = 'categorii.php — ' . $catFail;
    }
} else {
    echo "OK categorii.php — UI referință TecDoc + selectori tm_021 (fără import în BD)\n";
}

$cssPath = dirname(__DIR__) . '/public/assets/css/admin-pages.css';
$cssSource = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
$requiredCss = [
    'categorii-admin-page',
    '#tecdocModal.categorii-overlay-modal.is-open',
    'categorii-modal-open',
    'pointer-events: auto !important',
    'z-index: 100000',
];
foreach ($requiredCss as $cssNeedle) {
    if ($cssSource === '' || !str_contains($cssSource, $cssNeedle)) {
        $failures[] = 'admin-pages.css — regulă CSS tm_021 lipsă: ' . $cssNeedle;
    }
}
if ($cssSource !== '' && !array_filter($requiredCss, static fn(string $n): bool => !str_contains($cssSource, $n))) {
    echo "OK admin-pages.css — modal TecDoc tm_021 (display/z-index/pointer-events)\n";
}

$cruduSource = (string) file_get_contents(dirname(__DIR__) . '/src/Controllers/Categorii/crudu.php');
if (!str_contains($cruduSource, 'reference_only') || !str_contains($cruduSource, 'isTecdocStructureImportEnabled')) {
    $failures[] = 'crudu.php — guard import_tecdoc lipsă.';
} else {
    echo "OK crudu.php — guard backend import_tecdoc\n";
}

$getCtx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 15,
        'ignore_errors' => true,
        'follow_location' => 1,
    ],
]);

$indexRaw = @file_get_contents($base . '/index.php', false, $getCtx);
$indexStatus = 0;
if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
    $indexStatus = (int) $m[1];
}
if ($indexStatus === 301) {
    $rootRaw = @file_get_contents($base . '/', false, $getCtx);
    $rootStatus = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $rootStatus = (int) $m[1];
    }
    if ($rootStatus === 200 && is_string($rootRaw) && $rootRaw !== '') {
        echo "OK curl index.php HTTP 301 → / HTTP 200\n";
    } else {
        $failures[] = "curl index.php redirecționează, dar / HTTP {$rootStatus}.";
    }
} elseif ($indexStatus !== 200 || $indexRaw === false || $indexRaw === '') {
    $failures[] = "curl index.php HTTP {$indexStatus} — așteptat 200 sau 301→/.";
} else {
    echo "OK curl index.php HTTP {$indexStatus}\n";
}

$importBody = json_encode([
    'type_product' => 'import_tecdoc',
    'items' => [
        ['tecdoc_id' => 123, 'label' => 'Test Deferred'],
    ],
], JSON_UNESCAPED_UNICODE);
$importCtx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $importBody,
        'timeout' => 15,
        'ignore_errors' => true,
        'follow_location' => 1,
    ],
]);
$importRaw = @file_get_contents($base . '/admin/crudcategorii', false, $importCtx);
$importJson = is_string($importRaw) ? json_decode($importRaw, true) : null;
if (is_array($importJson) && ($importJson['message'] ?? '') === 'Autentificare necesară.') {
    echo "SKIP crudcategorii HTTP — autentificare necesară; guard verificat în sursă.\n";
} elseif (!is_array($importJson) || ($importJson['success'] ?? null) !== false || empty($importJson['reference_only'])) {
    $failures[] = 'crudcategorii import_tecdoc — răspuns neașteptat: ' . substr((string) $importRaw, 0, 200);
} else {
    echo "OK crudcategorii import_tecdoc blocat (reference_only)\n";
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo "FAIL {$failure}\n";
    }
    exit(1);
}

echo "ALL TESTS PASSED\n";
exit(0);
