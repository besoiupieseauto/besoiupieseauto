<?php

declare(strict_types=1);

/**
 * Handler export — inclus din public/api/export_action_endpoint.php (bootstrap deja făcut acolo).
 */

use Besoiu\Services\Export\ExportHubService;

function export_action_json(array $data, int $code = 200): never
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
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        export_action_json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $raw = file_get_contents('php://input') ?: '';
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        export_action_json(['success' => false, 'message' => 'JSON invalid.'], 400);
    }

    $action = trim((string) ($input['action'] ?? ''));
    $section = trim((string) ($input['section'] ?? 'catalog'));
    $format = trim((string) ($input['format'] ?? 'csv'));
    $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];

    $hub = new ExportHubService();

    if ($action === 'sections') {
        export_action_json([
            'success' => true,
            'sections' => ExportHubService::sections(),
        ]);
    }

    if ($action === 'preview') {
        $preview = $hub->preview($section, $filters);
        export_action_json([
            'success' => true,
            'section' => $section,
            'count' => $preview['count'],
            'label' => $preview['label'],
        ]);
    }

    if ($action === 'export' || $action === 'export_catalog_autopro_csv') {
        if ($action === 'export_catalog_autopro_csv') {
            $section = 'catalog';
            $format = 'csv_autopro';
        }
        $file = $hub->export($section, $format, $filters);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        header('Content-Type: ' . $file['mime']);
        header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
        echo $file['content'];
        exit;
    }

    export_action_json(['success' => false, 'message' => 'Acțiune invalidă: ' . $action], 422);
} catch (Throwable $e) {
    export_action_json(['success' => false, 'message' => $e->getMessage()], 500);
}
