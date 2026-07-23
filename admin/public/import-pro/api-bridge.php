<?php
declare(strict_types=1);

/**
 * Proxy către app/Import/MatchingPro/api/*.php
 * URL: /admin/public/import-pro/api/{script}.php
 */
$script = basename((string) ($_GET['script'] ?? ''));
if ($script === '' || !preg_match('/^[a-z0-9\-_]+\.php$/i', $script)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Script API invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

$target = dirname(__DIR__, 3) . '/app/Import/MatchingPro/api/' . $script;
if (!is_file($target)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Endpoint inexistent: ' . $script], JSON_UNESCAPED_UNICODE);
    exit;
}

require $target;
