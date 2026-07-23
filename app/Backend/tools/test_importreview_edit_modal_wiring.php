<?php
declare(strict_types=1);

/**
 * Smoke wiring: edit modal + publish confirm pe importreview.
 * php app/Backend/tools/test_importreview_edit_modal_wiring.php
 */

$view = dirname(__DIR__, 3) . '/modules/coada_import/pages/views/importreview.php';
$guard = dirname(__DIR__, 3) . '/admin/public/assets/js/admin-shell-guard.js';
$html = (string) file_get_contents($view);
$jsGuard = (string) file_get_contents($guard);
$errors = [];

$needles = [
    'importQueueEditParseRowPayload',
    'importQueueEditB64ToJson',
    "classList.add('is-open')",
    'queue-edit-one',
    'data-queue-edit-b64',
    'Publică toate filtrate',
    'publishModeBulk',
    'queueConfirm({',
    "La duplicate:",
];

foreach ($needles as $n) {
    if (!str_contains($html, $n)) {
        $errors[] = "Lipsește în importreview.php: {$n}";
    }
}

if (!str_contains($jsGuard, "classList.remove('is-open')")) {
    $errors[] = 'admin-shell-guard.js nu scoate is-open la close importQueueEditModal';
}

// CSS: modalul cere .is-open pentru display:flex
if (!preg_match('/\.import-queue-edit-modal\.is-open\s*\{[^}]*display:\s*flex/s', $html)) {
    $errors[] = 'CSS .import-queue-edit-modal.is-open fără display:flex';
}

if ($errors === []) {
    echo "OK: wiring Edit modal + Publică toate filtrate + is-open.\n";
    exit(0);
}

echo "FAIL:\n- " . implode("\n- ", $errors) . "\n";
exit(1);
