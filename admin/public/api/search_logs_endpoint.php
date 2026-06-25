<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\SearchLogs\SearchLogsService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $service = new SearchLogsService();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $payload = [
            'limit' => $_GET['limit'] ?? 100,
            'not_found_only' => ($_GET['not_found'] ?? '') === '1',
            'query_type' => $_GET['query_type'] ?? '',
            'q' => $_GET['q'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $result = $service->list($payload);
        ApiBootstrap::json([
            'success' => true,
            'count' => count($result['items']),
            'total' => $result['total'],
            'stats' => $result['stats'],
            'top_missing' => $result['top_missing'],
            'top_found' => $result['top_found'],
            'data' => $result['items'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON invalid.');
    }

    $action = (string) ($payload['type_product'] ?? $payload['action'] ?? 'list');

    switch ($action) {
        case 'list':
            $result = $service->list($payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Jurnal încărcat.',
                'count' => count($result['items']),
                'total' => $result['total'],
                'stats' => $result['stats'],
                'top_missing' => $result['top_missing'],
                'top_found' => $result['top_found'],
                'data' => $result['items'],
            ]);

        case 'stats':
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Statistici încărcate.',
                'data' => $service->stats(),
            ]);

        case 'top_missing':
            $limit = max(1, min(500, (int) ($payload['limit'] ?? 100)));
            $result = $service->topMissing($limit);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Coduri negăsite încărcate.',
                'count' => count($result['items']),
                'codes_count' => $result['codes_count'],
                'stats' => $result['stats'],
                'data' => $result['items'],
            ]);

        case 'export':
            $csv = $service->exportCsv($payload);
            $filename = 'search_logs_' . date('Y-m-d_His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo "\xEF\xBB\xBF" . $csv;
            exit;

        default:
            ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 422);
    }
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('search_logs_endpoint', $exception);
}
