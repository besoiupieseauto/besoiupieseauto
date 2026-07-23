<?php
declare(strict_types=1);

/**
 * Bridge legacy: POST /admin/crudproduse → Controllers/Produse/crudu.php
 * (JSON sau FormData; acceptă type_product din payload).
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once BESOIU_ADMIN . '/vendor/autoload.php';

use Config\Database;
use Dotenv\Dotenv;

try {
    $envDir = is_file(BESOIU_CONFIG . '/.env') ? BESOIU_CONFIG : BESOIU_ADMIN;
    Dotenv::createImmutable($envDir)->safeLoad();

    /** @var array<string, mixed> $applicationConfig */
    $applicationConfig = require BESOIU_ADMIN . '/config/config.php';

    Database::getInstance(
        (string) $applicationConfig['db_host'],
        (string) $applicationConfig['db_name'],
        (string) $applicationConfig['db_user'],
        (string) $applicationConfig['db_pass']
    );

    $sessionBridge = BESOIU_LEGACY . '/session-bridge.php';
    if (is_file($sessionBridge)) {
        require_once $sessionBridge;
        if (function_exists('besoiu_admin_session_start')) {
            besoiu_admin_session_start();
        }
    }
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Bootstrap CRUD produse eșuat: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$crudu = BESOIU_BACKEND . '/src/Controllers/Produse/crudu.php';
if (!is_file($crudu)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Handler produse lipsă.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require $crudu;
