<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
ApiBootstrap::bootJsonApi();

function supplier_sync_expected_token(): string
{
    return trim((string) ($_ENV['SUPPLIER_SYNC_TOKEN'] ?? getenv('SUPPLIER_SYNC_TOKEN') ?: ''));
}

function supplier_sync_read_token(): string
{
    return trim((string) ($_SERVER['HTTP_X_SUPPLIER_SYNC_TOKEN'] ?? ''));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $expected = supplier_sync_expected_token();
    if ($expected === '') {
        ApiBootstrap::json([
            'success' => false,
            'message' => 'SUPPLIER_SYNC_TOKEN lipseste din .env pe server.',
        ], 503);
    }

    $provided = supplier_sync_read_token();
    if ($provided === '' || !hash_equals($expected, $provided)) {
        ApiBootstrap::json(['success' => false, 'message' => 'Token sync invalid.'], 401);
    }

    define('IMPORT_PRODUCE_SKIP_HTTP', true);
    require_once ApiBootstrap::projectRoot() . '/src/Controllers/Produse/importproduse.php';

    $action = trim((string) ($_POST['action'] ?? 'upload'));

    if ($action === 'ping') {
        ApiBootstrap::json([
            'success' => true,
            'message' => 'OK',
            'import_dir' => import_temp_dir(),
        ]);
    }

    if ($action !== 'upload') {
        ApiBootstrap::json(['success' => false, 'message' => 'Actiune necunoscuta.'], 422);
    }

    if (empty($_POST['original_name'])) {
        ApiBootstrap::json(['success' => false, 'message' => 'Lipseste original_name.'], 422);
    }

    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file((string) $_FILES['file']['tmp_name'])) {
        ApiBootstrap::json(['success' => false, 'message' => 'Fisierul lipseste.'], 422);
    }

    $originalName = (string) $_POST['original_name'];
    $supplierCode = strtoupper(trim((string) ($_POST['supplier_code'] ?? '')));
    $uploadRole = trim((string) ($_POST['upload_role'] ?? 'supplier'));
    if ($uploadRole === '') {
        $uploadRole = 'supplier';
    }

    $fileId = trim((string) ($_POST['file_id'] ?? ''));
    if ($fileId === '') {
        $fileId = 'f_' . time() . '_' . bin2hex(random_bytes(4));
    }

    $chunkIndex = isset($_POST['chunk_index']) ? (int) $_POST['chunk_index'] : 0;
    $totalChunks = isset($_POST['total_chunks']) ? max(1, (int) $_POST['total_chunks']) : 1;

    $meta = save_chunk_upload(
        $fileId,
        $originalName,
        $chunkIndex,
        $totalChunks,
        (string) $_FILES['file']['tmp_name'],
        $uploadRole
    );

    ApiBootstrap::json([
        'success' => true,
        'message' => !empty($meta['completed']) ? 'Fisier primit complet.' : 'Chunk primit.',
        'data' => [
            'file_id' => $fileId,
            'original_name' => $originalName,
            'supplier_code' => $supplierCode,
            'completed' => !empty($meta['completed']),
            'size' => (int) ($meta['size'] ?? 0),
            'file_kind' => (string) ($meta['file_kind'] ?? ''),
        ],
    ]);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('supplier_sync_endpoint', $exception, 500, 'Eroare sync.');
}
