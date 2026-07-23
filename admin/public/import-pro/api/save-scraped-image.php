<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/ImportScrapedImageStore.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    import_json_response(['success' => false, 'error' => 'JSON invalid'], 400);
}

$imageUrl = trim((string) ($payload['imageUrl'] ?? ''));
$card = is_array($payload['card'] ?? null) ? $payload['card'] : [];

if ($imageUrl === '') {
    import_json_response(['success' => false, 'error' => 'Lipsește imageUrl'], 422);
}

try {
    $result = ImportScrapedImageStore::saveFromCard($card, $imageUrl);
    if (empty($result['success'])) {
        import_json_response([
            'success' => false,
            'error' => (string) ($result['error'] ?? 'Salvare eșuată'),
        ], 422);
    }

    import_json_response([
        'success' => true,
        'message' => 'Imagine salvată în Poze.',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    import_json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
