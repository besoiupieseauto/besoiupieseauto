<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'module' => 'import',
    'endpoint' => 'supplier_sync_endpoint.php',
    'message' => 'Stub din pachet. Producție: /admin/public/api/supplier_sync_endpoint.php',
], JSON_UNESCAPED_UNICODE);
