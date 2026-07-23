<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'module' => 'import',
    'endpoint' => 'import_action_endpoint.php',
    'message' => 'Stub din pachet. Producție: /admin/public/api/import_action_endpoint.php',
], JSON_UNESCAPED_UNICODE);
