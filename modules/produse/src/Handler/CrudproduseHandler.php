<?php
declare(strict_types=1);

namespace Besoiu\Modules\Produse\Handler;

/**
 * Delegă către crudu.php legacy (suportă JSON + FormData + toate type_product).
 */
final class CrudproduseHandler
{
    public static function handle(): void
    {
        // Handler → src → produse → modules → projectRoot
        $resolved = dirname(__DIR__, 4) . '/app/Backend/src/Controllers/Produse/crudu.php';
        if (!is_file($resolved)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Handler produse (crudu.php) lipsă.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require $resolved;
    }
}
