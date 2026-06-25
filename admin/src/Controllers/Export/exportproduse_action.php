<?php

declare(strict_types=1);

/**
 * tm_105 — Acțiuni export catalog (CSV Piese Autopro).
 */

function exportproduse_action_out_json(array $data, int $code = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $raw = file_get_contents('php://input') ?: '';
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        exportproduse_action_out_json(['success' => false, 'message' => 'JSON invalid.'], 400);
    }

    $action = trim((string) ($input['action'] ?? ''));
    if ($action !== 'export_catalog_autopro_csv') {
        exportproduse_action_out_json(['success' => false, 'message' => 'Actiune invalida.'], 422);
    }

    $root = dirname(__DIR__, 4);
    require_once $root . '/system/catalog-export-autopro.php';

    $pdo = \Config\Database::getDB();
    $rows = catalog_export_autopro_fetch_rows($pdo);
    if ($rows === []) {
        exportproduse_action_out_json([
            'success' => false,
            'message' => 'Nu există produse active în catalog pentru export Piese Autopro.',
        ], 422);
    }

    $csv = import_queue_export_autopro_csv_content($rows);
    $filename = catalog_export_autopro_filename();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF" . $csv;
    exit;
} catch (Throwable $e) {
    exportproduse_action_out_json(['success' => false, 'message' => 'Eroare: ' . $e->getMessage()], 500);
}
